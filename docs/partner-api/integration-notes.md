# Partner Stock API: integration notes

Spec: /home/tocojapan.com/docs/partner-api/ (SRS-TOC-PAPI docx, Amendment 01 partner types, TOC-PAPI-UI-01 rev 2 Filament brief, TOC-PAPI-DEV-01 build plan).
Module: `app/Modules/PartnerApi`. Tests: `tests/PartnerApi` (testsuite PartnerApi). Partner docs: `app/Modules/PartnerApi/docs/` (guide.md, openapi.json), updated with every endpoint change.

## 1. Switches

| Setting | Default | Effect |
|---|---|---|
| `PARTNER_API_ENABLED` | false | While false every `/partner/v1/*` route answers 404 problem+json. |
| `PARTNER_API_HOST` | unset | When set (api.tocojapan.com), routes bind to that host only. Rebuild the route cache after changing it. |
| `HOLDS_ENFORCED` | false | When true, PayPal, bank transfer and proforma must win a hold before taking an order (FR-HLD-03). Admin holds and "Under offer" work either way. |

## 2. Decisions

- Own OAuth client-credentials endpoint, no Passport. Secrets are Argon2id; access tokens are opaque, 15 minutes, stored as SHA-256. The token is looked up on every call, with no cache, so suspend and revoke act on the next request (stricter than SRS §8.7's 60 s).
- Spec format is JSON (`openapi.json`): symfony/yaml is not installed and adding packages to the live docroot was avoided.
- Error codes are stable strings in a `code` member, next to RFC 9457 fields. OAuth errors also carry `error`.
- Partner responses carry `X-LiteSpeed-Cache-Control: no-cache` and `Cache-Control: no-store, private`, so LiteSpeed's page cache never stores a partner response.
- Contract dates are compared in Asia/Tokyo (`partner_api.timezone`).
- `GET /me` is added beyond the SRS so partners can check their keys while integrating.
- Holds live in core (`app/Services/Holds`, `App\Models\VehicleHold`, `config/holds.php`), not in the module: the website uses them without the partner API. Only `HoldService` changes holds or the `reserved` status.
- One active hold per car: row lock on the vehicle plus a unique `active_vehicle_id` (= vehicle_id while active, null after). Proven with 16 parallel processes against MariaDB (`tests/Concurrency`, isolated database `toco_holds_test`, credentials in /home/tocojapan.com/secure/holds-test-db.env).
- Hold lengths: PayPal 3 h (approval links lapse), bank transfer, proforma and admin 48 h. A paid order's hold has no expiry until staff confirm the sale (client decision 2026-10-10: staff confirm).
- PayPal: before capturing (browser return and webhook), the order must still hold the car or win it back; otherwise the order is cancelled and nothing is captured. A paid order whose car is held elsewhere gets a loud admin note.
- `reserved` cars are now in `Vehicle::published()` (listed, "Under offer"); buy and proforma buttons show only to the buyer holding the car.
- Every hold status change purges the LiteSpeed page cache (existing `LiteSpeedCache` mechanism).
- Change sync is not narrowed by scope: a car that moves out of a partner's scope still reaches it as a tombstone (stock number only). Scope, price and photo setting changes bump `partners.scope_version`; partners do a full sync when it changes.
- Photo add, delete or reorder touches the vehicle (Media model hook in PartnerApiServiceProvider) so change syncs pick it up.
- Photo `id` is stable per partner and photo; signed URLs are aligned to the hour so list bodies and ETags stay stable within an hour.
- Image quota exhaustion answers 429 `image_limit_reached` (not a bare 404 as SRS §9.2 suggests) so partners can tell a limit from a dead link; it leaks nothing.
- Limits use Laravel's RateLimiter on the database cache store (no Redis on this server).
- Variants are rendered from the 1280 px watermarked "gallery" file (phase 1) and cached in storage/app/private/partner-variants/{media id}/; deleted with the photo. Stale variants from old profiles are left until the photo goes.
- PRE-EXISTING BUG found 2026-10-10 (not fixed, reported to the user): ConvertVehiclePhotoOnUpload is registered twice (Laravel listener auto-discovery + Event::listen in AppServiceProvider), so every uploaded photo is resized and watermarked twice. The master capture is guarded against it.
- Webhook payloads are thin (event, stock_no, status); partners fetch details through the API, so a notice can never carry out-of-scope data. Events are judged per partner with `PartnerScope::matches()` (PHP twin of the SQL scope; a test keeps them identical): entering scope = vehicle.created, leaving = vehicle.removed.
- Unsent notices about the same car are coalesced into the latest. Deliveries pin the DNS answer checked as public (CURLOPT_RESOLVE) so a rebind cannot reach Toco's network; redirects are not followed.
- Test keys cannot place holds (a hold reserves a real car). Listing partners get 403 `holds_not_available_for_partner` even with a forged scope.
- Follow-up outside this project: the Mailer's "include reserved" option (TOC-VEH-003, docs/mailer/integration-notes.md section 4) can now map to `reserved`. Not changed here (Mailer is a separate contract).

## 3. Shared-code change register

Every edit outside `app/Modules/PartnerApi` is listed here.

| Date | File | Change | Why |
|---|---|---|---|
| 2026-10-10 | bootstrap/providers.php | Registers PartnerApiServiceProvider | Module registration |
| 2026-10-10 | phpunit.xml, tests/Pest.php | PartnerApi testsuite; Pest TestCase + RefreshDatabase for tests/PartnerApi | Tests |
| 2026-10-10 | app/Http/Middleware/HandleNotFound.php | Skips `partner/*` paths: no managed redirect, no 404 log | Partner 404s are deliberate (out of scope, dead image links) and must stay problem+json |
| 2026-10-10 | database/migrations/2026_10_10_200000_create_vehicle_holds_table.php, config/holds.php, app/Models/VehicleHold.php, app/Services/Holds/*, app/Console/Commands/ExpireHolds.php | New core hold system | FR-HLD-01..08 |
| 2026-10-10 | app/Models/Vehicle.php | `published()` includes `reserved`; `sold_at` stamped whenever status becomes sold; `isReserved()`, `holds()`, `activeHold()` | Under offer listing; the edit form used to drop sold cars at once |
| 2026-10-10 | app/Providers/AppServiceProvider.php | Order::updated and Vehicle::updated hooks call HoldService | Paid keeps / cancelled frees the hold; form status edits keep holds in step |
| 2026-10-10 | routes/console.php | `holds:expire` every minute | FR-HLD-04 |
| 2026-10-10 | app/Services/CheckoutFlow.php, app/Http/Controllers/CheckoutController.php, BankTransferController.php, PayPalWebhookController.php | Order + hold in one transaction; reserved cars only for their holder; capture guard; race redirect | FR-HLD-03 |
| 2026-10-10 | app/Services/ProformaInvoiceService.php, app/Http/Controllers/ProformaInvoiceController.php | Proforma claims a hold; rivals refused | FR-HLD-03 |
| 2026-10-10 | app/Filament/Admin/Resources/Vehicles/* (HoldActions new, VehiclesTable, EditVehicle, VehicleForm) | Hold for buyer, Extend, Release, hold-aware Mark sold; Under offer subheading; manual "Reserved" option disabled | UI brief 5, Amendment 01 |
| 2026-10-10 | app/Listeners/ConvertVehiclePhotoOnUpload.php | Copies the clean original to storage/app/private/media-masters/{media id}/ before watermarking (first pass only) | FR-IMG-01 |
| 2026-10-10 | app/Providers/Filament/AdminPanelProvider.php | "Partner Network" navigation group; registers PartnerApiServiceProvider::filamentPages()/filamentResources()/filamentWidgets(); `databaseNotifications()` with 60 s polling | Menu registration; UI brief 6.1 bell |
| 2026-10-10 | database/migrations/2026_10_10_600000_create_notifications_table.php | Laravel's standard notifications table | Filament database notifications |
| 2026-10-10 | .env | `PARTNER_API_ENABLED=true`, `HOLDS_ENFORCED=true`, `PARTNER_API_HOST=api.tocojapan.com` | Go-live |
| 2026-10-10 | Server (outside git) | Cloudflare A record api → 200.234.45.127, DNS only (by the user). LiteSpeed: `api.$VH_NAME` alias in vhosts/tocojapan.com/vhost.conf; `api.tocojapan.com` added to both tocojapan.com listener maps in httpd_config.conf (backups in /root/migration/*before-api-20261010). New Let's Encrypt cert for tocojapan.com, www, api issued and renewed by acme.sh (/root/.acme.sh/tocojapan.com, installs to /etc/letsencrypt/live/tocojapan.com, reloads LiteSpeed); the old migrated cert had no renewal. | Dedicated API host (SRS section 6) |
| 2026-10-10 | database/seeders/DatabaseSeeder.php | Calls PartnerPermissionSeeder | Permission seeding |
| 2026-10-10 | resources/views/components/vehicle-card, vehicle-row, vehicles/show, vehicles/quick-view, partials/vehicle-buy-actions | "Under offer" badge and banner, buy buttons off, race message | UI brief 7 |
| 2026-10-12 | resources/views/components/layouts/site.blade.php | Footer "Other" column: "API Developer Guide" link to /partner/docs | Client request: partners find the docs from the site |

## 4. Progress

| Phase | Status | Notes |
|---|---|---|
| P1 Foundation | Done 2026-10-10 | Tables partners, partner_clients, partner_access_tokens, partner_audit_logs (migrated on live, empty). Token endpoint, `GET /me`, problem+json, request IDs, 64 KB cap, unknown-parameter rejection, IP allow-list, audit log. 30 tests. |
| P2 Holds + own channels | Done 2026-10-10 | MIS task #204. Built and live in the admin; checkout enforcement waits for `HOLDS_ENFORCED=true`. 24 feature tests + MariaDB race test. |
| P3 Read API | Done 2026-10-10 | `GET /vehicles` (full + change sync, tombstones, cursor, ETag/304), `GET /vehicles/{stock_no}`, `/catalog/*`, `POST /cif`. Whitelist resource with contract test. Signed photo links (endpoint in P4). Partner tests also pass on MariaDB. |
| P4 Images, limits, usage | Done 2026-10-10 | `GET /img/{token}` (signed, partner-bound, bare 404s, variants cached privately), per-minute/per-day/photo limits with RateLimit headers (304 free), request log + `GET /usage`, clean masters kept for new uploads. |
| P5 Admin + docs page | Done 2026-10-10 | Partner Network group (partners.admin, seeded for super_admin + admin): Partners (wizard/tabs, suspend/reactivate/revoke with typed name), connection keys (one-time reveal, rotate, revoke), Holds (tabs, 30 s countdown, extend/release/mark sold). Docs at /partner/docs (+ openapi.json), admins only until go-live. Live smoke test in a rolled-back transaction: token, 71 vehicles, photo 1280 px WebP, catalogue, usage; no supplier data leaked. First real partner waits on the JWT pull/push answer. |
| P6 Webhooks, partner holds, usage page | Done 2026-10-10 | Signed change notifications (outbox, coalescing, backoff 24 h, HTTPS + public-IP pinning, `partners:webhooks` every minute), hold.expiring/expired/released; holds API for selling partners (idempotent, limits, listing/test-key refusals); admin Usage page, View usage, Send test event, notification secret. 85 partner tests on SQLite and MariaDB. |
| P7 Notifications, dashboard, hardening, go-live | Done 2026-10-10 | Panel bell (database notifications): hold placed, ending in 6 h, ran out unused (daily), 80% of allowance, notifications failing over 1 h, contract ending in 14 days, unusual traffic (stock-number walking, 5x volume). Dashboard widgets: Partner network stats, Needs attention (incl. FR-HLD-07). Field set added to the partner form. Runbook: docs/partner-api/runbook.md. **Go-live 2026-10-10: PARTNER_API_ENABLED=true and HOLDS_ENFORCED=true** (.env backup /root/migration/env-before-partner-golive-20261010); live rolled-back checkout dry run: first buyer holds, rival refused. |

## 5. Acceptance checklist (UI brief section 10)

| Item | Result |
|---|---|
| Every SRS section 4 setting reachable in the partner form, plain words, helper text | Yes. Not shown by phase 1 scope: image mode (watermarked only), supplier stock, allowed origins (only for browser-side image use, not built). |
| Listing partner: no hold settings, keys cannot hold, Holds page never lists it | Yes (403 `holds_not_available_for_partner`; staff holds credited to a listing partner show as "Admin, for <partner>"). |
| Secret shown once, never retrievable | Yes (only the hash is stored; reveal modal needs "I have copied the key"). |
| Revoke needs typed name; suspend effective within a minute | Yes (on the next request). |
| Holds countdown updates without refresh; expiring holds stand out | Yes (30 s poll, amber under 6 h, red under 1 h). |
| Mark sold impossible against another channel's hold without release; can credit a listing partner | Yes. |
| Reserved visible on admin table, vehicle page, public card and page, partner surfaces | Yes (API status `reserved`, guide tells partners to show "Under offer"). |
| All partner-network actions in the audit log with actor, before and after | Partner and key changes: `partner_audit_logs`. Holds: `vehicle_holds` + activity log (log name `holds`). |
| No trace of the Partner Network without `partners.admin` | Yes (pages 403, menu group and widgets hidden). |

## 6. Open items

- JWT Japan: pull (this API, nothing to build) or push (separate connector quote)? Needed before connecting JWT.
- Set `PARTNER_API_IMAGE_KEYS` to a dedicated key (today derived from APP_KEY).
- Pre-existing bug, not fixed: the photo upload listener runs twice (double processing). One-line fix offered.
- Mailer "include reserved" option can now map to `reserved` (Mailer contract).

Known unrelated failures in the full suite (they fail without this module too): Auth AuthenticationTest, PasswordResetTest x3, RegistrationTest, CmsTest sitemap.
