# TOCO Mailer: progress

## Phase 1: codebase study, scaffold, permissions, settings (24 Sep 2026)

**Covers:** TOC-GEN-001 to 004, TOC-GEN-006 (Overview counters), TOC-NFR-001 (Brevo key encrypted, last 4 shown). Closes OPEN-02 and OPEN-07.

Done:
- `docs/mailer/integration-notes.md` (approved 24 Sep).
- Scheduler cron added for the site user. The queue worker is started by the scheduler every minute.
- Module `app/Modules/Mailer`: provider, config, all 11 `mailer_` tables from SRS 8.1, models, `MailerSettings` (encrypted secrets), `MailerAccess`.
- Permissions `mailer.admin` / `mailer.marketer`, roles `mailer_admin` / `mailer_marketer`, `MailerPermissionSeeder` (also grants super_admin).
- Admin: "Mailer" menu group. Overview (last import, contacts imported today/7/30 days, 5 latest campaigns) and Mailer Settings (admins only).
- Tests: `tests/Mailer/Feature` has 20 tests, all passing. The full suite shows 6 failures that were there before (auth/Turnstile and sitemap tests); none come from the Mailer.

Demo:
1. Give a user the `mailer_marketer` role on Users. They see Mailer > Overview; /admin/mailer/settings returns 403.
2. Give `mailer_admin`. Mailer settings appears; saving a Brevo key afterwards shows only `••••1234`.

Live database: migration and `MailerPermissionSeeder` run 24 Sep (approved). Only `mailer_` tables were created.

Deferred to later phases: menu items Campaigns and Banners (Phase 6), Importer (Phases 2 and 3), Brevo "Test connection" (Phase 3), and adding `app/Modules/Mailer` to the admin theme `@source` when custom markup needs Tailwind classes.

## Phase 4: vehicle source and images (24 Sep 2026)

**Covers:** TOC-VEH-001 to 007.

Done:
- `Domain/Vehicles/VehicleSource`: `search` (20 per page), `find` and `findMany`, returning `VehicleDTO`. Read-only; it reuses `Vehicle::scopeFilter`.
  - Available means status `published`. Sold, draft and deleted vehicles never show in search. The site has no "reserved" status, so the admin option has no effect for now.
  - `find` and `findMany` include sold and deleted vehicles, so re-checks can tell "sold" from "no longer available".
  - Badge: HOT DEAL = `is_featured`, NEW = one of the 7 latest arrivals. Previous price = `price_fob` when discounted.
- `Domain/Vehicles/EmailImageService`: centre-crops the primary photo to 540x310 and steps JPEG quality down until the file is 70 KB or less.
  - Files are cached by vehicle and source-file hash in `storage/app/public/email-assets/vehicles`, with a row in `mailer_email_images`.
  - A branded placeholder is used when there's no photo or it can't be read.
- Tests: 39 in `tests/Mailer`. 38 pass; 1 is skipped on SQLite (the price range test, since the shared scope binds prices as floats) and was checked on MySQL below.

Checked on live data (MySQL, 24 Sep):
- Search `E02056` returns it first: 8 ms average and 28 ms max over 10 runs (69 available vehicles).
- Price range 5,000 to 8,000: every result is in range. Hot deal: 22, New: 7.
- 10 vehicles (5 discounted or hot deal, 5 latest) match their tocojapan.com pages on title, stock ref, FOB, previous price, mileage, year, transmission and make.
- Their email images are all 540x310 and 38 to 53 KB, and the sample URL returns HTTP 200.

## Phase 5: email template (24 Sep 2026)

**Covers:** TOC-TPL-001 to 006, 009, TOC-CMP-005. TPL-007 (the client screenshot matrix) is scheduled for Phase 8. TPL-008 (Brevo template library) is saved by `mailer:brevo:save-template` once the Brevo key is in place (Phase 3 client).

Done:
- `resources/views/email/campaign.blade.php` plus the `<x-mailer-email::vehicle-card>` component, ported from the approved Round 1 export. Red is #E30613, as approved.
- `Domain/Campaigns/CampaignRenderer`: `render(Campaign)` builds from vehicle snapshots, `renderWith(Campaign, vehicles)` does the rendering.
  - Order of work: Blade, then `UtmTagger` (reads each link's `data-utm` for utm_content), then the CSS inliner.
  - Brevo tags are shielded while the inliner runs, so `{{ mirror }}` and `{{ unsubscribe }}` stay literal.
- Footer, fraud text, top bar, nav links, request block and logo all come from Mailer Settings. The default logo is copied to `storage/app/public/email-assets/branding`.
- `php artisan mailer:render-sample {n}` uses real available vehicles and writes `storage/app/private/mailer/sample-{n}.html`.
- Tests: 12 renderer and UTM tests. The Mailer suite has 51 tests: 50 pass, 1 is skipped on SQLite.

Results with real stock: 2 vehicles 13.2 KB, 5 vehicles 21.6 KB, 6 vehicles 24.1 KB, 12 vehicles 39.4 KB (limit 90 KB).
- Checked in headless Chrome at 600 px and at 375 px (in an iframe): 2 columns on desktop, 1 card per row on mobile, 5 vehicles lay out 2/2/1.
- Test send: the 6-vehicle sample went to amilaraja@gmail.com through the site SMTP (24 Sep). It wasn't sent through Brevo, so "View in browser" and "Unsubscribe" show the literal Brevo tags.
