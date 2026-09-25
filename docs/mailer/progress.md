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

## Phases 2 and 3: inbox importer and Brevo contact sync (24 Sep 2026)

**Covers:** TOC-IMP-001 to 009, TOC-EXT-001 to 009, TOC-BRV-001 to 007, TOC-LOG-001 to 004, TOC-GEN-006.

Built and tested against a fake mailbox and a faked Brevo (no live calls):
- `GmailReader`: service account with domain-wide delegation, `gmail.readonly` only (test asserts the scope), 20 s timeout.
  - Uses history ids, with a search fallback when history has expired.
  - Reads only the From header of messages that turn out not to be from approved senders; those leave no record.
- Extraction:
  - Candidates from plain text, HTML (including `mailto:`) and Reply-To (optional); normalised.
  - Excludes TOCO domains, the sender's own address, no-reply-type addresses and the ignore list.
  - MX-or-A check, cached 24 h.
  - Per-sender field rules (name split into first and last) and a per-message limit.
- `BrevoClient` + `BrevoGuard`:
  - Refuses send, test-send, status change, transactional/SMS/WhatsApp, any `scheduledAt`, and any blacklist change. It throws before any HTTP call, and this is tested.
  - 429 and 5xx are retried 5 times with backoff and Retry-After.
- `ContactSync`:
  - Skips "unsubscribed" and "bounced" contacts.
  - SOURCE and TOCO_LAST_ENQUIRY_AT are set every time; TOCO_IMPORTED_AT and names only when empty.
  - Double opt-in for Confirm-first senders.
- `ImportRunner`: atomic lock ("skipped: already running"); each message is one transaction; the checkpoint moves only on success; one alert email to Mailer Admins after 3 failures; resumable backfill in batches of 100.
- `mailer:import` runs every minute but only acts after the configured interval, and stays silent until Gmail is set up. Daily `mailer:cleanup` deletes logs older than 12 months.
- Admin (Mailer Admins only): Importer cluster with Status + Run now, Approved senders, Ignore list, Run log, Contact search (with Retry), Backfill, Rule tester. Mailer settings has **Test Brevo connection**.
- Fixtures: `tests/Mailer/Fixtures/eml/tocojapan-inquiry/` (the site's own inquiry notification format) and `example-portal/` (synthetic).

**Still needed for live acceptance:** A1/A2 Workspace delegation and the key file, A4 Brevo key and lists, A5 the approved sender list with 2 real `.eml` samples per sender (each gets fixture tests, TOC-NFR-004), A6 consent mode per sender.

## Phase 6: Campaign Builder and push (24 Sep 2026)

**Covers:** TOC-BAN-001 to 003, TOC-CB-001 to 008, TOC-CMP-001 to 004, 006, 007.

- Banner library: 1200x440 (±2%), JPG/PNG up to 1 MB. Saved as an optimised JPEG ≤ 150 KB. Archive and restore; archived banners stay on old campaigns.
- Campaign list: search, status filter, newest first, opens and clicks. Duplicate from the list.
- Builder:
  - Details with Brevo sender and list pickers (cached, Refresh).
  - Banner picker.
  - "Add vehicles" search: ref or keyword, make, body type, price, badge; 20 results.
  - 2–12 vehicles, reordered by drag or ↑/↓ buttons.
  - Notices: price changed (the snapshot refreshes) or sold/missing (blocks push).
- Preview modal at 600/375 px. `CampaignPusher`:
  - Draft only; the same render() output as the preview (tested byte-identical).
  - PUT while Brevo still shows a draft; refuses with "Duplicate" once it has been sent.
  - 25 s limit when Brevo is down.
- Changed since push is detected from a hash of the rendered HTML. Hourly `mailer:sync-stats` fetches Sent status, recipients, opens, clicks and unsubscribes.
- The Overview now has a "New campaign" button, and its latest campaigns link to the builder.

**To check with the first real draft:** the "Open in Brevo" link pattern (`MAILER_BREVO_CAMPAIGN_URL`, default `https://app.brevo.com/marketing-campaign/edit/%d`).

## Phase 7: release and deliverability (24 Sep 2026)

- `docs/mailer/deploy.md`: release steps for this server (the working copy is the live docroot), secrets, Google Workspace steps, Brevo set-up commands, and the DNS table.
- DNS checked: Brevo code, DKIM (brevo1/brevo2) and DMARC `p=none` with reporting are **already in place**. **SPF does not include Brevo.** TOCO needs to change it to `v=spf1 include:_spf.google.com include:spf.brevo.com -all` (A7).
- `tests/Mailer/Feature/RoutesTest.php`: all 18 Mailer routes are under `/admin/mailer` with admin auth (TOC-NFR-002). Email images are served by the existing `/storage` link (HTTP 200 checked).

## Phase 8: handover documents (24 Sep 2026)

- `README.md`: configuration, credential rotation, adding a sender, adding a banner, and a module map.
- `staff-guide.md`: one-page plain-English guide for Marketers.
- `qa/uat.md`: UAT-1 to UAT-7 each mapped to a passing automated test, with empty "live result" columns, plus the TOC-TPL-007 screenshot matrix.
- Test suite: **157 Mailer tests** (156 pass, 1 skipped on SQLite). The full app suite shows only the 6 failures that were there before the Mailer (auth/Turnstile and sitemap).

**Blocked on TOCO:** backfill run (OPEN-09 start date, and Gmail access), live UAT walkthrough, TPL-007 screenshots from Brevo test sends, SPF change.

## Access: Google key installed (25 Sep 2026)

- Service account `toco-mailer@toco-internation-1752745648056.iam.gserviceaccount.com` (client ID 115087789854235626774).
- The key file was moved out of the repository to `/home/tocojapan.com/secure/toco-gmail-sa.json` (dir 700, file 600, owner `tocoj2379`). Its path is saved in Mailer settings. `.gitignore` now blocks `docs/email_automation/*.json`.
- Checked: Google issues a token for the key, and the Gmail API is enabled in the project (a call without a mailbox returns FAILED_PRECONDITION, not SERVICE_DISABLED).
- Waiting for: (1) TOCO's Workspace admin to authorise domain-wide delegation for that client ID with only `https://www.googleapis.com/auth/gmail.readonly`; (2) the confirmed mailbox address (OPEN-01).

## Access: mailbox connected (25 Sep 2026)

- OPEN-01 closed. The mailbox is **first@toco-int.com** on Google Workspace (MX smtp.google.com). The proposal's "toco-iont.com" was a typo.
- TOCO authorised domain-wide delegation for client ID 115087789854235626774 with `gmail.readonly`.
- Mailer settings: mailbox saved. Own domains are now `tocojapan.com, toco-int.com`, so these addresses are never imported.
- Live check (read-only): profile opened (25,724 messages, historyId 2244220); a search returned 25 messages from the last 7 days.
- **TOC-IMP-001 acceptance passed live:** trying to add a label was refused by Google with HTTP 403.
- Nothing has been imported: there are no approved senders yet, so scheduled runs process nothing.
