# TOCO Mailer: Claude Design brief

**Document:** TOC-DESIGN-BRIEF v1.1 | **Project:** WEB-TOC-01 | **Spec:** SRS-TOC-01 v1.1 | **Date:** 23 September 2026

Use this brief in Claude Design. Attach with it: the tocojapan.com homepage screenshot, the TOCO logo files, page 4 (email preview) and page 5 (Campaign Builder screen) of proposal WEB-TOC-01, and 3 to 4 screenshots of the existing tocojapan.com admin area (a list page, an edit form, the menu and a modal if one exists). TOCO Mailer is a new section inside that admin, so the admin screens must look like they were always part of it. Those proposal images are the approved direction; refine them, do not start from a blank page.

Work in three rounds, one per section below. Get TOCO's approval on Round 1 before starting Round 2.

---

## Paste this as the opening prompt

> You are designing for TOCO International, a Japanese used-vehicle exporter (tocojapan.com) that sells to buyers in 90+ countries, mostly in Africa, the UK, the US, Canada and Australia. Most buyers read email on a phone.
>
> I need three things, in this order:
> 1. A promotional stock email (Brevo campaign) that looks like tocojapan.com: red, black and white, bold and trustworthy.
> 2. A banner master template (1200 x 440) the TOCO team can reuse each campaign.
> 3. Admin screens for a new "Mailer" section inside the existing tocojapan.com admin (screenshots attached), where non-technical staff pick vehicles and push an email draft to Brevo, plus settings screens for an inbox contact importer. These screens must reuse the existing admin's layout, menu, typography, tables, forms and buttons; add new components only where the admin has nothing suitable.
>
> Follow the brand tokens, constraints and screen lists in this brief exactly. The email will be hand-coded as table-based HTML, so design only what email clients can render. Staff using the admin are not technical: use short, plain-English labels and never show terms like API, JSON or token.
>
> Start with Round 1: the email, at desktop 600px and mobile 375px.

---

## Brand tokens

| Token | Value | Use |
|---|---|---|
| `red` | `#E10613` (verify against tocojapan.com CSS, OPEN-10) | Prices, badges (Hot deal), primary buttons, accent rules, kickers |
| `black` | `#0D0D0F` | Hero/banner backgrounds, footer, dark blocks |
| `ink` | `#111114` | Headings, secondary buttons, body emphasis |
| `grey-700` | `#55555C` | Body text |
| `grey-500` | `#6B6B73` | Meta text (year, km, transmission) |
| `grey-200` | `#E4E4E8` | Card borders, dividers |
| `grey-50` | `#F4F4F6` | Page background in admin, email outer background `#ECECEF` |
| `alert-bg` / `alert-text` | `#FDECEC` / `#A3000A` | Fraud warning, blocking errors |
| `success` | `#1A8A3A` | "Added", "In Brevo" states |

**Typography**
- Email: Arial, Helvetica, sans-serif only. Headline 22px bold, card title 15px bold uppercase, price 21px bold red, meta 10 to 11px, body 13px/20px. Stock refs in a monospace style (Courier New) in red, as on the website.
- Admin: use the existing tocojapan.com admin fonts and sizes exactly as in the attached screenshots.

**Visual language from tocojapan.com:** white vehicle cards with thin grey borders, square corners or 4px radius, red price, small coloured badges top-left (NEW dark, HOT DEAL red, SOLD grey), strong black bands, diagonal red and white slashes on banners.

---

## Round 1: Promotional email

### Frames to produce
1. Desktop 600px, 6 vehicles (standard case).
2. Mobile 375px, same content, cards stacked one per row.
3. Desktop with 5 vehicles (odd count: last card in left column, right cell empty).
4. Desktop with 2 vehicles and with 12 vehicles (min and max).
5. Images-off view (alt text and backgrounds only).
6. Dark-mode check (logo must stay readable; keep the logo on a white header).

### Section order (fixed, SRS TOC-TPL-002)
1. Hidden preheader text (note it on the frame).
2. Top bar, black: left "Japanese used vehicles for export", right "View in browser" link.
3. Header, white: TOCO logo left (150px wide), 3 text links right: STOCK LIST, HOW TO BUY, CONTACT (last one red). 3px red bottom rule.
4. Banner: full width 600 x 220 (image supplied at 1200 x 440). Whole banner is one link.
5. Intro: red kicker (for example THIS WEEK'S PICKS), headline, one short paragraph.
6. Vehicle grid: 2 columns, 16px gutter.
7. Call-to-action block, black: "Looking for something specific?", one line of text, red button SEND A REQUEST.
8. Fraud warning line on light red: "Beware of fraudsters. Always verify our company bank details before sending any payment."
9. Footer, black: TOCO INTERNATIONAL, address, phone, WhatsApp, email, one line explaining why they get this email, Unsubscribe link.

### Vehicle card
- Photo 266 x 153 (supplied at 540 x 310), no rounded corners.
- Meta line: `2022 · 50,002 km · Automatic`.
- Badge (NEW / HOT DEAL) plus stock ref `#E02059`.
- Title, uppercase, up to 2 lines. Design one card with a long title (for example "2001 SUBARU SAMBAR SUPER CHARGER") to prove wrapping.
- "FOB" label, price in red; when there is a previous price, show it struck through in grey before the new price.
- Full-width black button "VIEW VEHICLE ›".
- Equal card heights within a row are nice but not required (email clients may not honour them).

### Email constraints (the build must be able to match your design)
- Single column container 600px; only simple tables, no overlapping layers, no absolute positioning, no CSS grid or flexbox, no background images except on the banner area (and even that must work as a plain image).
- No web fonts, no icons from icon fonts, no SVG. Use text characters (› · ×) or small PNGs.
- Buttons are solid colour blocks with text, minimum 40px tall on mobile.
- Minimum text size 10px desktop, 12px mobile.
- Text contrast WCAG AA on all backgrounds.
- Keep decoration light so a 12-vehicle email stays under 90 KB of HTML.

### Deliverable format
- Frames as above plus a spec sheet: spacing (8px grid), colours, font sizes per element, button specs.
- Export a static HTML version of the 6-vehicle desktop email using tables and inline styles, with the vehicle card wrapped in `<!-- VEHICLE_CARD_START -->` and `<!-- VEHICLE_CARD_END -->` comments and editable text wrapped in comments naming the field (`<!-- FIELD:headline -->`). Claude Code converts this into Blade components.
- Keep `{{ mirror }}` and `{{ unsubscribe }}` as literal text in the links.

---

## Round 2: Banner master (1200 x 440)

Produce one master and three variants the TOCO team can re-use by swapping text and photo.

- Layout: left 50 percent black panel with text, right 50 percent vehicle photo, separated by the TOCO diagonal red slash with a thin white slash beside it (as on the tocojapan.com Summer Sale and "Customize your vehicle" banners).
- Text zones: kicker (red, uppercase, letter-spaced), headline (2 lines max, italic heavy, white), one sub-line (grey), a red button-shaped label.
- Safe zone: keep all text inside the left 540 x 360 area, 60px from edges, readable when scaled to 600 x 220 on a phone.
- Variants: (a) HOT DEALS, (b) NEW ARRIVALS, (c) KEI TRUCKS or another category special.
- Must look right with average dealer-yard photos (grey sky, parked cars), so include a darkening gradient on the photo edge.
- Export: editable master plus PNG exports at 1200 x 440, and a short "how to make the next banner" note for staff.

---

## Round 3: Mailer section in the tocojapan.com admin

Desktop 1280px primary, tablet 1024px check. Match the existing admin exactly: same shell, sidebar or top menu, page header pattern, tables, form fields, buttons and colours. The brand tokens above apply only where the admin has no existing equivalent (for example vehicle cards and status pills). Every screen has one obvious main action.

### Navigation
A new "Mailer" group in the existing admin menu with: Overview, Campaigns, Banners, and for Mailer Admins only: Importer, Mailer Settings. Login and user management are the existing admin screens and are not redesigned; staff get Mailer access through the existing user and role screens.

### Screens to design

| # | Screen | Role | Key content | States to show |
|---|---|---|---|---|
| S2 | Mailer Overview | All | Last import (time, result), contacts added today / 7 days / 30 days, 5 latest campaigns with status, button "New campaign" | Healthy, last import failed (red notice) |
| S3 | Campaign list | All | Search, status filter, table: name, status pill, vehicles count, lists, pushed date, sent date, opens and clicks | Empty state with friendly "Create your first campaign" |
| S4 | Campaign editor (Campaign Builder) | All | Left: campaign details (name, subject, preview text, kicker, headline, intro, sender, lists), banner picker, selected vehicles (drag handle, thumbnail, title, ref, price, remove, up/down buttons). Right: vehicle search (ref/keyword, make, body type, price range, badge), results with Add buttons. Top bar actions: Preview, Push to Brevo as draft | 0 vehicles, 1 vehicle (push disabled with reason), 6 vehicles, 12 vehicles (add disabled), price changed notice, sold vehicle blocking push |
| S5 | Preview | All | Modal with Desktop / Mobile toggle showing the real email | Loading |
| S6 | Push confirmation and result | All | Confirm dialog explaining in plain words: "This creates a draft in Brevo. Nothing is sent. Open Brevo to send a test, check it, then send." Result panel with "Open in Brevo" button | Success, Brevo not reachable, campaign already sent in Brevo (offer Duplicate) |
| S7 | Campaign status pills | All | Draft, In Brevo (draft), Changed since push, Sent, Archived | Colour and icon for each |
| S8 | Banner library | All | Grid of banners with name, link, used-in count; Upload (drag and drop) with size rules shown up front; Archive | Upload wrong size error |
| S9 | Importer overview | Admin | Status card (next run, last run), Run now button, interval setting, tabs: Senders, Ignore list, Run log, Contact search, Backfill | Running, failed 3 times (alert) |
| S10 | Approved senders | Admin | Table: label, matches, target lists, confirm mode, active toggle; Add/Edit drawer with fields and a "Field rules" section (label + pattern rows) | Empty, editing |
| S11 | Rule tester | Admin | Paste sample email, pick sender, "Test" shows found addresses with keep/skip reason and captured fields; banner "Test only, nothing is saved" | Result with mixed outcomes |
| S12 | Run log and Contact search | Admin | Run table with counts by outcome; Contact search by email showing each import with reason (Added, Updated, Skipped: unsubscribed, bounced, ignored, no mail domain, over limit) | Filtered view |
| S13 | Backfill | Admin | Start date, Start button, progress bar with batches done, Pause/Resume | In progress |
| S14 | Mailer Settings | Admin | Brevo connection (key shown as ••••1234, Test connection), mailbox address, TOCO own domains, email footer details, logo, nav links, fraud text | Connection OK, connection failed |

### Copy tone (examples to use)
- Button: "Push to Brevo as draft", never "Sync" or "Deploy".
- Sold warning: "E02056 was sold. Remove it before pushing."
- Price change: "Price changed from $2,750 to $2,650. The email will show the new price."
- After push: "Draft created in Brevo. Nothing has been sent yet."
- Skipped reason labels in plain words: "Unsubscribed earlier", "Email address bounced", "On your ignore list", "Email domain doesn't exist".

### Components to define once
Only the components the existing admin lacks: status pills, vehicle badges (NEW, HOT DEAL, SOLD), selected-vehicle row with drag handle, vehicle search result row, notices (price changed, sold blocking, draft created), and the preview modal if the admin has no modal. Everything else (buttons, fields, tables, drawers, empty states) uses the existing admin components.

### Accessibility
WCAG 2.1 AA contrast, visible focus rings (red 2px), keyboard alternative for drag reorder (up and down buttons), error messages next to the field, never colour alone for status (pill text plus colour).

### Deliverable format
- Screens as listed with the states noted, plus a component sheet.
- Export as HTML using the existing admin's CSS classes where visible in the screenshots, or a clear spec (spacing, colours, sizes) so Claude Code can rebuild each screen as Blade views in the existing admin layout.
- File names: `S04-campaign-editor-6-vehicles.png` style, delivered into `docs/mailer/design/admin/` and email files into `docs/mailer/design/email/`.

---

## Review checklist before handing to Claude Code
- [ ] TOCO approved the email (Round 1) and banner master (Round 2).
- [ ] Email frames cover 2, 5, 6 and 12 vehicles, mobile, images off, dark mode.
- [ ] Email HTML export uses tables and inline styles only, with the card and field comments.
- [ ] Mailer screens S2 to S14 with states, visually consistent with the existing admin; component sheet for new components only.
- [ ] No terms like API, JSON or token on Marketer screens.
- [ ] Colours verified against tocojapan.com (OPEN-10).
