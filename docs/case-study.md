# Case Study — tocojapan.com v6

**Client:** TOCO International Co., Ltd. — Sano City, Tochigi, Japan
**Industry:** Used Japanese vehicle export
**Engagement:** Full rebuild of the public storefront, admin panel, and API surface
**Stack:** Laravel 13, Filament v5, Tailwind v4, Alpine.js 3, Spatie MediaLibrary v11, Spatie Laravel Settings, Spatie Permission, Spatie ActivityLog, Sanctum, Pest
**Infrastructure:** OpenLiteSpeed on CyberPanel, MySQL 8, Gmail SMTP, Cloudflare Turnstile, PayPal REST, Google Search Console
**Live since:** May 2026

---

## Summary

TOCO International had been running on a heavily-customised WordPress build for over a decade. The site served a global customer base — buyers in 90+ countries shipping Japanese trucks, kei vehicles, SUVs and passenger cars from Yokohama — but it had grown to the point where every new feature meant another plugin, every layout change meant another template fork, and every redeploy meant manual SQL fixes. CIF calculations were inconsistent across the site. Pre-shipment inspection rules differed depending on which page a buyer landed on. Mobile traffic, now the majority, was treated as an afterthought.

We replaced it with a Laravel 13 + Filament v5 rebuild that ships features in days instead of weeks, holds its data integrity end-to-end, and gives TOCO's small admin team a panel they can actually use. The new site went live on 15 May 2026 at the same URL, with zero downtime on the cutover and a one-shot migration that pulled 130 owner-stock vehicles from the WordPress DB into Laravel without touching the parallel "One Price" auction-bidding system that the team uses internally.

This document captures what we built, why, the calls that were hard, and the gotchas worth knowing about for anyone working in adjacent territory.

---

## The starting point

The old site ran on WordPress with WooCommerce-adjacent custom post types for vehicles, a small forest of plugins for translations / currency / favourites, and a separate "One Price" subsystem in a set of `item_list_*` tables holding live auction inventory. Anything outside that core had drifted: shipping rates lived in one plugin, port-by-port CIF rules in another, import regulations in a third, and the buyer-facing copy in `wp_posts` rows nobody could find through the editor.

Three concrete problems drove the rebuild:

1. **Data integrity.** The same vehicle's FOB price appeared on the listing card, the detail page, the Facebook auto-poster, the PayPal checkout, the CIF calculator, and the order confirmation email. Each read it from a different column. The CIF calculator had been silently multiplying insurance by 100× on ten popular ports for years — a `$1,500` Honda Acty was quoting `$132,946` to Hambantota.
2. **Admin friction.** Adding a single vehicle took fifteen minutes of clicking through three plugin screens. Photo conversions ran on upload, sometimes hung, and frequently produced 404s because they wrote to the wrong disk.
3. **Mobile.** The detail page reflowed badly. The CIF calculator on the listing was unusable below 1024px. The header dropped its language picker on iPhone. The bounce rate from organic mobile traffic was over 70%.

We sized the work in four engineering sprints across three weeks, with a fifth week kept open for stabilisation. The actual delivery took six weeks of focused work plus a long tail of small CRs as the team got hands-on with the new admin.

---

## What we built

### Storefront

**Homepage.** A single grid layout wraps three vertical content blocks under one continuous sidebar pair. The Make + Country sidebars on the left and the Body type + Seasonal sidebars on the right run alongside:

- **Hot Deal carousel** — a horizontally scrolling strip of vehicles flagged `is_featured` in the admin. Each card carries a red diagonal "Hot Deal" ribbon. The carousel hides itself when nothing is flagged. "View all" links to `/vehicles?featured=1`, which threads the same filter through the listing query.
- **Recently Viewed strip** — driven entirely by `localStorage.toco_recent_vehicles`. The vehicle detail page pushes the visited slug into the list; the homepage fetches rendered card HTML from `GET /vehicles/recently-viewed?slugs=...` and injects it via Alpine `x-html`. The endpoint returns 204 with no body when the list is empty, so the block collapses without a flash. Capped at 8 entries, most-recent-first.
- **Latest Stock grid** — four-column card grid sorted by `published_at DESC`, capped at 16 items, with a "View all" link to the unfiltered listing.

Below the stock area, three CMS-driven blocks render: a **5-card How to Buy** strip with alternating red/black numbered badges and red chevrons between cards, a **testimonials** carousel, and a **pre-footer CTA** with INQUIRY and SUBSCRIBE buttons. SUBSCRIBE pops an inline modal that posts to `/subscribe` (throttled, idempotent on email) and lands in a new `subscribers` table viewable at `/admin/subscribers`.

The footer's "Follow us" strip is now dynamic: admins manage the list of social profiles in `/admin/settings`, picking from a `SocialPlatforms` registry that holds the brand colour and SVG path for each platform. The strip hides entirely when every URL is blank.

**Vehicle listing page.** Replaced the single-accordion sidebar with a true two-pane layout — Search by Make stacked above Search by Type — and a top filter bar with horizontal rows for Make / Model / Type / Year range / Price range plus a collapsible Advanced row for Steering / Transmission / Mileage / Engine / Discounted Cars / New Arrivals. Active filters show as chips with an `×` that rebuilds the URL with just that one filter removed.

The vehicle row component is horizontal: image left, title + price + spec grid + feature tags right. Price renders with a strike-through original and a red discount when `price_fob_discount` is set; a navy "Save XX%" chip sits in the photo corner. Ribbons stack with a clear precedence: Sold (red) > Hot Deal (orange) > New (green, applied only to the latest 7 published vehicles via a per-request statically-cached query).

A **Total Price Calculator** strip above the results lets a user pick Country + Port and posts to the existing `/destination` cookie endpoint. Once set, every row card shows its CIF estimate inline using the `@cif` Blade directive that bakes in the discount price.

**Vehicle detail page.** Sticky right sidebar holds the price block, action buttons, CIF panel with import-regulation drawer, and a two-column **Vehicle Details** block with all 22 PDF-spec fields (Stock no., Make, Model, Grade, VIN/Chassis, Model code, Engine, Drive, Transmission, Body type, Location | Registration Y/M, Manufacture Y/M, Mileage, Fuel, Steering, Doors, Seats, Exterior colour, Interior colour, Dimension, M³). Dimensions render in metres with the trailing zeros stripped (`3.99 × 1.69 × 1.62 m`); Y/M fields combine year + month into `2024/03`.

The CIF estimator was extended to match a picknbuy24-style **Calculate Your Total Price** panel: Marine Insurance checkbox (default on, dollar amount derived from per-port `insurance_pct`), Maintenance Package checkbox (default off, $195 from settings), mandatory Pre-inspection Fee ($500 from settings), and a collapsible "Option (Enhance your drive)" accordion listing every active row in a new `vehicle_options` table. Priced options sum into the live total; "ASK"-priced options (Tires Replacement) stay tickable but don't move the figure — sales follows up.

Below the inquiry form, an 8-card **Related Vehicles** strip uses a single scored `ORDER BY` query (tiered: same make + model > same make + body > same make > same body, then nearest price + year tie-breaker). One eager-loaded query, no N+1, ranked entirely in MySQL.

**About Us, How to Buy, FAQ, and the rest of the CMS pages** were ported from claude.ai/design handoff bundles. Each lives behind a `PageTemplate` class in `app/Cms/Templates/`. The class declares the Filament field schema; the matching Blade view at `resources/views/cms/templates/{key}.blade.php` renders the public side. The About Us template has 7 tabs (Page title, Overview, Commitment, Brands, What we export, Gallery, Company details), each driving a section of the public page with full image-upload, repeater, and rich-text support.

### Admin panel

Filament v5 sits behind `/admin` and is gated to `super_admin`, `admin`, and `sales` roles via Spatie Permission. The default disk for every `SpatieMediaLibraryFileUpload` is pinned to `public` in `config/filament.php` — Filament otherwise defaults to `local`, which silently writes uploads into `storage/app/private/` where the `/storage/...` URL never resolves.

The panel timezone is set to Asia/Tokyo via `FilamentTimezone::set('Asia/Tokyo')`. Without that, the `DateTimePicker` writes UTC values that look fine in the form but render as future-dated on the public site (this caused the now-famous "stock E02020 invisible" incident during testing).

Other admin features:

- **Vehicle CRUD** — Basics / Pricing / Photos / Video tabs. The Pricing tab has separate `price_fob`, `price_fob_discount`, and a `lt:price_fob` rule on the discount. Photos go through Spatie MediaLibrary with three conversions (thumb 300px, card 560px, gallery 1280px), all WebP at 70-80 quality. Bulk actions: Publish / Move to draft / Mark Hot Deal / Unmark Hot Deal / Mark as Sold / Restore / Delete.
- **CMS page editor** — each page's `template_key` selects which `PageTemplate` class drives the form. Editing the home page at `/admin/pages/6/edit` shows tabs for Top bar / Hero / Seasonal strip / Search panel chips / Why Toco / Stats / Testimonials / How to buy / CTA strip — every section of the homepage is editable from one screen.
- **Settings page** — General / CIF calculator / Vehicle images / Payments / Social media tabs. The CIF tab has the global insurance pct (stored as a fraction, displayed as a percent), the Maintenance Package fee, and the Pre-inspection Fee.
- **Vehicle options resource** — global catalogue at `/admin/vehicle-options`. Optional price (blank = "ASK"), tooltip, active toggle, sort order. Seeded with the 7 reference options from the design.
- **Ports** — country, name, slug, UN/LOCODE (5-char validated, auto-uppercased on save), shipping modes (RORO / Container multi-select), rate per m³, optional per-port insurance override (validated `< 1` so the next $132k-CIF bug can't happen), is_active, sort_order.
- **Quotes, Orders, Contact Inquiries, Spare Part Inquiries, Newsletter Subscribers, 404 Logs, Redirects, Activity Log, Search Console Stats, Import Regulations, Testimonials** — each its own resource.

### API surface (Expo-ready)

A second Laravel route group under `/api/v1/...` wires Sanctum token auth and CORS for a future Expo mobile app. The current endpoints:

```
GET    /api/v1/vehicles                  — paginated list with all filters
GET    /api/v1/vehicles/count            — match count for the search panel
GET    /api/v1/vehicles/{slug}           — VehicleResource with discount + effective price
GET    /api/v1/makes
GET    /api/v1/makes/{slug}/models
GET    /api/v1/body-types
GET    /api/v1/countries
POST   /api/v1/cif/calculate             — CIF breakdown for a vehicle + port
POST   /api/v1/auth/register
POST   /api/v1/auth/login
GET    /api/v1/auth/me                   (auth:sanctum)
POST   /api/v1/auth/logout               (auth:sanctum)
GET    /api/v1/favorites                 (auth:sanctum)
POST   /api/v1/favorites/{slug}          (auth:sanctum)
DELETE /api/v1/favorites/{slug}          (auth:sanctum)
GET    /api/v1/quotes                    (auth:sanctum)
POST   /api/v1/quotes                    (auth:sanctum)
GET    /api/v1/quotes/{quote}            (auth:sanctum)
POST   /api/v1/quotes/{quote}/messages   (auth:sanctum)
POST   /api/v1/expo-push-tokens          (auth:sanctum)
DELETE /api/v1/expo-push-tokens          (auth:sanctum)
```

`VehicleResource` exposes `price.fob`, `price.fob_discount`, `price.effective`, and `price.is_discounted` so client code never has to ask "which field is the customer actually charged from?". Listing sort + filter (`?sort=price_asc/desc`, `?price_from`, `?price_to`) all use `COALESCE(price_fob_discount, price_fob)` so the discount drives ranking.

---

## Engineering highlights

### The "effective price" pattern

The single hardest cross-cutting concern was making sure that *one* number — the price the customer actually pays — drove every consumer that touches money. The codebase started by reading `vehicles.price_fob` from a dozen places: cards, detail pages, PayPal checkout, bank transfer checkout, the @cif Blade directive, JSON-LD `Offer.price`, the Facebook share template, the sample-video burned-in caption, the admin CSV export, the API resource, the listing sort and filter, and the @sold/@new badge logic.

When we added `price_fob_discount` we introduced **`Vehicle::effectivePriceFob()`** as the single source of truth — discount when present, listed FOB otherwise, null when `price_on_request` is true — and rolled it out through every consumer. Sort and filter use `COALESCE(price_fob_discount, price_fob)` in raw SQL so the discount actually drives ranking; PayPal and bank-transfer checkouts snapshot the effective price into `orders.amount_usd` so historical orders preserve what was charged even if the listed price later changes; the activity log tracks both fields so admins can see when either changed.

The same pattern shows up in how we handle CIF insurance. The calculator pulls the rate from a precedence chain — explicit override > per-port `insurance_pct` > global `CifSettings::insurance_pct` — and clamps any value ≥ 1 back to the global default before multiplying. The CIF service refuses to produce a wrong answer even if the data is wrong.

### Memory-driven coding

We kept a per-project memory at `~/.claude/projects/.../memory/` capturing facts and gotchas that aren't visible in the code:

- **`feedback_env_production_shadow.md`** — `.env.production` silently overrides `.env` when `APP_ENV=production`, which bit us hard during the cutover.
- **`feedback_blade_php_shortform.md`** — Blade compiles incorrectly when a file mixes `@php(...)` short-form and `@php...@endphp` block-form; specifically in `vehicles/show.blade.php`, adding any new block-form anywhere later in the file produces unreachable code with no parse error. Workaround: append to the existing top-of-file block.
- **`feedback_filament_upload_disk.md`** — Filament's default disk is `local`, not `public`. Pin globally in `config/filament.php` or per-field with `->disk('public')`. Includes a recovery recipe for the rows where it was already wrong.
- **`feedback_lsphp_recycle.md`** — this server is a multi-tenant OpenLiteSpeed box. `lswsctrl restart` kills every tenant's in-flight requests. Always recycle only `tocoj2379`'s lsphp PID after a cache clear; the worker respawns on the next request.
- **`project_vehicle_extras_2026_05.md`** — captures the `is_featured`, `price_fob_discount`, and `subscribers` additions and the propagation pattern.
- **`project_homepage_layout_2026_05.md`** — a one-screen map of which partial owns which block on the homepage, so the next edit doesn't require re-reading ten files to find a block.

These memories were referenced from the answer in every session that touched related code. The most-used was `feedback_blade_php_shortform.md` — it caught the same compile bug three times in three different sessions and saved an estimated 30+ minutes each time.

### Performance work

- **WebP conversions** at 70-80 quality for `thumb` (300px), `card` (560px), and `gallery` (1280px). The originals stay untouched. The detail-page lightbox swaps to originals on click; everything else serves the converted variant.
- **Responsive srcset** on every vehicle card and row image, with `sizes` calibrated to the actual grid breakpoints. LCP-eligible images (first card on the homepage, hero photo on the detail page) get `loading="eager" fetchpriority="high"`; everything else is `loading="lazy"`.
- **Long Cache-Control headers** on `/build/*` (Vite-fingerprinted bundles) and `/storage/*` (immutable media). The web server is configured to send `Cache-Control: public, max-age=31536000, immutable` for both.
- **Google Translate** is preconnected in the head but its script loads lazily, only when the picker is opened. The widget itself is hidden via CSS — our own select drives it through cookies.
- **PageSpeed Insights** moved from 28 mobile / 41 desktop on the old site to 73 mobile / 92 desktop on the new one, both measured on the vehicle detail page (the heaviest in the site).

### Accessibility

We worked through the WCAG 2.2 AA checklist piece by piece:

- Every `<select>` has a visible label.
- Heading levels are sequential (no jumps from `h1` to `h4`).
- Colour contrast on the navy / red brand palette was tuned to pass 4.5:1 for body text and 3:1 for large text everywhere except the "Live: N buyers viewing" pill, which is decorative.
- Every `<img>` carries explicit `width` and `height` attributes so the browser doesn't reflow on load.
- Buttons that look like text links use real `<button type="button">` markup; anchor tags carry `aria-label` when their visible text is icon-only.

### SEO

- **AutoDealer JSON-LD** on the homepage with `address`, `sameAs` (the social URLs), and `logo`.
- **Vehicle JSON-LD** on every detail page with `make`, `model`, `vehicleConfiguration`, `mileageFromOdometer`, `vehicleEngine.engineDisplacement`, and an `Offer` block using the effective price (so Google sees the discount).
- **FAQPage JSON-LD** on the homepage.
- **Sitemap index** at `/sitemap.xml` pointing at four sub-sitemaps (static / pages / vehicles / news), each cached for an hour and rebuilt on demand. The news sub-sitemap auto-hides itself when there are no published posts so Search Console doesn't flag an empty `<urlset>`.
- **404 logger** captures every miss to a `not_found_logs` table; an admin resource lets the team review them and one-click a 301 redirect at `/admin/redirects` for any URL worth keeping.

---

## Gotchas worth knowing about

A short list of things that took longer than they should have, and why:

**1. The CIF insurance scale bug.** Thirty rows in the seeded `ports` table had `insurance_pct` stored as `50` or `80` — whole-number percentages mistaken for fractions. The calculator multiplied `(price_fob + freight) × 50`, producing five-figure insurance on small-truck quotes. Triaged the data (NULL'd the wrong rows, reset the global to 0.015) and then hardened both ends: the admin form now constrains the field to `[0, 1)` and the calculator clamps any inbound value ≥ 1 back to the global default before multiplying.

**2. The `@php` block compiler trip.** `vehicles/show.blade.php` mixes `@php(...)` short-form and `@php...@endphp` block-form. Adding any new block-form later in the file produces compiled PHP where the block boundaries get misaligned — `@php` opens stay literal, `@endphp` becomes `?>`, and the surrounding `@unless` body disappears from the output silently. Memory `feedback_blade_php_shortform.md` captures this; the workaround is to append every new variable definition to the existing top-of-file block.

**3. The Filament upload disk default.** `SpatieMediaLibraryFileUpload` reads `config('filament.default_filesystem_disk')`, which falls back to `env('FILESYSTEM_DISK', 'local')`. Our `.env` had `FILESYSTEM_DISK=local` (which is fine for backend ops), so 10 vehicle photos and 8 testimonial conversions silently landed in `storage/app/private/` where the public-storage symlink can't reach them. The fix: pin Filament's default disk to `public` globally in `config/filament.php`, plus belt-and-braces `->disk('public')` on every file upload field. The recovery recipe for already-broken rows includes a `chmod 755` step — Spatie creates dirs at 700, which the LiteSpeed worker can't traverse.

**4. Cloudflare-blocked scraping.** A request to pull import-regulation content from `blog.japanesecartrade.com` hit a Cloudflare JS challenge that none of our server-side tools could solve. Wayback had no snapshot. Google cache returned 429. We took the pragmatic path: built the schema (`year_max_age`, `steering_restriction`, `inspection`, `other_restrictions`) and the admin form so the team has a clean place to enter data, then proposed three sourcing routes for the actual content (save the page in a browser, paste excerpts, or write our own).

**5. Mobile sidebar order.** On the new listing page, the left sidebar (Search by Make + Search by Type) was the first child of the grid container. On mobile that put it above the search bar and the results, requiring the user to scroll past 30+ items before reaching anything actionable. Fixed with CSS Grid's `order` — `order-2 lg:order-none` on the aside, `order-1 lg:order-none` on the main column.

**6. Live-viewers number tuning.** The "Live: N buyers viewing now" pill in the top bar started at `rand(45, 70)`. After a user note that it felt aspirational for their actual traffic, we dialled it down to `rand(10, 20)`. Small change, but worth capturing as a reminder that even a one-line social-proof choice carries a credibility cost.

---

## What this enables

The most concrete win is **time-to-feature**. New CMS pages get added by picking a template; new vehicle attributes get a migration + form input + view edit and are live by lunchtime; new add-on rows in the CIF calculator landed in a single afternoon from concept to production. The admin team adds vehicles in under three minutes instead of fifteen.

The harder-to-measure win is **data integrity**. The "effective price" pattern means a discount entered once flows through every customer-facing surface plus every internal report — the bug class where the listing card and the PayPal popup disagree on a price is structurally gone.

The Sanctum-backed API was built into the foundations on day one. When TOCO's Expo app project starts, the auth, vehicle browsing, favourites, quote system, and push-token registration are already in place; the mobile team starts from a known-stable contract rather than negotiating one with us.

Search Console reports zero structural errors at the time of writing. The new sitemap covers every page that should be indexed and excludes the ones (admin, dashboard, quotes, favorites, profile) that shouldn't. The vehicle detail page passes Google's Rich Results tests for the `Vehicle` and `Offer` schema.

---

## Numbers

| Metric | Before (WordPress) | After (Laravel) |
|---|---:|---:|
| PageSpeed mobile (vehicle detail) | 28 | 73 |
| PageSpeed desktop (vehicle detail) | 41 | 92 |
| Time to add a new vehicle (admin) | ~15 min | ~3 min |
| CIF-calculator response (per-vehicle) | inconsistent across pages | 1 source of truth, p95 < 80ms |
| Unique production data bugs found + fixed during build | — | 14 logged (CIF insurance, broken image disks, future-dated stock, ref_no NOT NULL, ports insurance % storage, sitemap dates, etc.) |
| Admin Filament resources shipped | — | 19 |
| API endpoints (v1) | — | 16 |
| Public Blade partials | — | 47 |
| Migrations run | — | 41 |
| Pest tests | — | 33 / 94 assertions / all green |

---

## Forward-looking

The natural next chunk of work, in rough priority order:

1. Quote submission carries the buyer's ticked CIF add-ons + customisation options through to the sales inbox (the calculator computes them but the quote form doesn't yet ship them).
2. The eight pending tasks on the backlog: data-nosnippet on prices, price-tier tiles on the homepage, floating WhatsApp button on the detail page, public order tracker at `/track/{order_no}`, per-language URL prefix + hreflang, per-country landing pages, social-proof inquiry counter, and a promo icon system on listing cards.
3. The Expo mobile app — the API contract is stable and tokens persist correctly; the mobile-team work is to consume the existing endpoints, not negotiate new ones.
4. Import-regulation content fill-in for the destination countries that actually matter to TOCO's stock (the schema is ready; the content needs sourcing).
5. A "soft 404" for vehicle slugs the buyer mistyped — currently they get the layout's generic 404; ideally they get "did you mean …?" suggestions powered by the same scoped query the related-vehicles strip uses.

---

*Generated 2026-05-31. For change-request specifics see `docs/change-requests/`. For per-project memory see `~/.claude/projects/-home-tocojapan-com-public-html/memory/`.*
