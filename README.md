# Toco Japan — Laravel platform

Replacement for the WordPress install at tocojapan.com (owner-stock vehicles, CIF
calculator, customer accounts, admin tooling, CMS). The "One Price" auction
inventory remains on WordPress and is **not** migrated.

See `../new_design/MIGRATION_PLAN.md` for the full plan and sprint breakdown.

## Local dev

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev      # in one terminal
php artisan serve --port=8000
```

The default dev DB is sqlite at `database/database.sqlite`. To use the production
schema set `DB_CONNECTION=mysql` and the `DB_*` env vars.

## Tooling

- **Tests** — `./vendor/bin/pest`
- **Static analysis** — `./vendor/bin/phpstan analyse`
- **Code style** — `./vendor/bin/pint` (`--test` for dry-run)
- **Build** — `npm run build`

CI runs all of the above on push / PR (`.github/workflows/ci.yml`).

## Where things live

- `resources/css/app.css` — design tokens (Toco palette + Montserrat / JetBrains Mono).
- `resources/views/welcome.blade.php` — Sprint 0 placeholder; replaced by the v5 home in Sprint 3.
- `config/database.php` — adds a read-only `wp` connection used only by `app/Services/MigrationFromWp/*` once Sprint 8 lands.

## Sprint status

- **Sprint 0** — scaffold, tooling, tokens. Done.
- **Sprint 1** — schema, models, seeders, Filament admin shell at `/admin`,
  Breeze customer auth (Blade), Sanctum-backed JSON API at `/api/v1` for the
  Expo mobile client (auth + Expo push token registry). Done.
- **Sprint 2** — vehicle CRUD in Filament (tabbed form + media library photos),
  public listings + filters at `/vehicles`, vehicle detail at `/vehicles/{slug}`,
  public read API (`/api/v1/vehicles`, `/makes`, `/makes/{slug}/models`,
  `/body-types`, `/countries`), reusable Alpine search widget. Done.
- **Sprint 3** — v5 homepage + listing/detail redesign in Blade. New site shell
  (notice bar, sticky header, 5-col footer), hero with 3-col promo tiles + auto
  carousel, overlapping search panel (4 tabs), featured grid with browse-by-make
  / browse-by-body-type sidebars + counts, Why Toco / How it works strips, CTA
  band. v5 assets in `public/img/v5/`. Done.
- **Sprint 4** — CIF calculator (CifCalculator service, formula
  `price_fob + m3*rate + (price+freight)*pct`), country→port cascade public
  /cif page, embedded CIF widget on vehicle detail, POST /api/v1/cif/calculate
  for Expo, Filament Site settings page (`/admin/settings`) tabbed
  General / CIF (insurance % editable, persisted via spatie/laravel-settings).
  Done.
- **Sprint 5** — customer accounts (My Toco dashboard), saved vehicles
  (heart toggle), quote requests with messaging thread (customer + admin reply),
  Filament QuoteResource with Conversation relation manager, and authenticated
  API endpoints for the same so Expo can save vehicles, list/create/reply to
  quotes. Done.
- **Sprint 6** — admin operations: UserResource (role management, password
  reset action, Quotes/Favorites relation managers), dashboard widgets
  (StatsOverview + Latest quotes), global header search across vehicles +
  quotes + users, bulk actions (publish/unpublish vehicles, archive quotes),
  CSV exports for vehicles/quotes/users, Activity log viewer (Spatie
  activitylog rendered via a custom Filament page). Done.
- **Sprint 7** — ACF-style templated CMS. PageTemplate registry with 4
  templates (Default, FAQ, Contact, CountryLanding); Filament PageResource
  with dynamic schema based on selected template; public catch-all route
  `/{slug}` rendering published pages with SEO meta + canonical + OG tags;
  hand-rolled `/sitemap.xml` (cached 1h) listing home + listings + CIF +
  pages + every published vehicle; dynamic `/robots.txt`. Sample seed:
  /about, /faq, /contact, /sri-lanka, /tanzania. Done.
- **Sprint 8** — data migration from WordPress, UAT, cutover. (pending)

## Default credentials (dev)

After `php artisan migrate:fresh --seed` the super-admin is:
`admin@tocojapan.com` / `password` (change before any non-local deployment).

## WordPress coexistence

This app is meant to run at `new.tocojapan.com` (or `tocojapan.com/new`) during
development. The WP install at the parent docroot continues to serve traffic,
including the One Price auction module, until cutover.
