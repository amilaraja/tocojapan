# tocojapan.com

Laravel 13 / PHP 8.3 / Filament 5 admin at /admin. Tests: Pest (`php artisan test`, SQLite in memory).
This working copy is the live docroot: file changes are live immediately. Lint before wiring anything in.

## TOCO Mailer module (app/Modules/Mailer)
Spec: docs/email_automation/SRS-TOC-01_TOCO_Mailer.docx (requirement IDs TOC-AREA-NNN). Plan: docs/email_automation/TOC-DEV-PLAN_Claude_Code.md.
Integration notes: docs/mailer/integration-notes.md. Progress: docs/mailer/progress.md.
Three parts: Gmail to Brevo contact importer, Brevo email template, Campaign Builder that pushes DRAFT campaigns to Brevo.
Built inside tocojapan.com: reuse the Filament admin panel, spatie roles/permissions and the Vehicle model.
Admin screens are Filament Pages/Resources registered via MailerServiceProvider::filamentPages()/filamentResources(); do not introduce a second UI framework.
Access: MailerAccess::canUse() (mailer.admin or mailer.marketer) and MailerAccess::isAdmin(), granted through the mailer_admin / mailer_marketer roles.

### Hard rules (never break)
1. NEVER send or schedule email. BrevoClient must reject sendNow, sendTest to lists, scheduledAt,
   and any POST to /emailCampaigns/{id}/sendNow or /sendTest. Covered by tests/Mailer/Unit/BrevoGuardTest.php. (TOC-CMP-002)
2. Gmail scope is gmail.readonly ONLY. Never request modify, labels or send scopes. (TOC-IMP-001)
3. The Mailer module never creates, updates or deletes vehicle records. VehicleSource uses read queries only. (TOC-VEH-001)
4. Never store email bodies or attachments. Keep message ID, sender, received time and extracted fields only. (TOC-LOG-003)
5. Never change a Brevo contact's blacklisted status; skip blacklisted and hard-bounced contacts. (TOC-BRV-002/003)
6. Never overwrite existing Brevo FIRSTNAME/LASTNAME/COUNTRY/PHONE or TOCO_IMPORTED_AT. (TOC-BRV-004)
7. Preview HTML and pushed HTML come from the same CampaignRenderer call. (TOC-CB-003)
8. Secrets only via encrypted mailer_settings (MailerSettings::SECRETS) or .env; never log API keys or email bodies.
9. Do not modify existing app code except admin menu registration, permission seeding and service provider registration.
   Any other change to shared code must be listed in docs/mailer/integration-notes.md section 9.

### Conventions
- All Mailer tables prefixed mailer_. All Mailer routes are Filament admin routes; the only public files are
  storage/app/public/email-assets (served via the existing /storage link).
- Marketer-facing text in plain English: no "API", "JSON", "token". (TOC-GEN-007)
- Store UTC, display Asia/Tokyo (config mailer.display_timezone).
- Timeouts: Gmail and Brevo 20s. (TOC-NFR-005)
- Jobs run on the `mailer` queue; the worker is started every minute by the scheduler (routes/console.php).
- Filament injects closure arguments by name: use $query (not $q) in modifyQueryUsing/filter closures.
- Tests: fake Gmail with Tests\Mailer\Support\FakeMailbox, DNS and Brevo via Tests\Mailer\Support\MailerTest.
- Filament builds navigation once per app instance: in tests, use one acting user per test when asserting menus,
  and call Filament::setCurrentPanel('admin') before Livewire::test() on a page.
- Commit messages reference requirement IDs, e.g. "TOC-EXT-004 exclude system local parts".
- Add or update tests (tests/Mailer) with every change; run the test suite before finishing a task.

### Email HTML rules (TOC-TPL-*)
- 600px table layout, inline CSS, Arial/Helvetica, no JS, no web fonts, no external CSS.
- Three-column grid of compact 176px cards (client change 2026-09-29, was two columns), stacks under 480px; unused cells in the last row stay empty.
- Brevo tags {{ mirror }} and {{ unsubscribe }} stay literal in output (escape in Blade: @{{ mirror }}).
- Total HTML for 12 vehicles < 90 KB. Brand red #E30613 (site colour, OPEN-10).
- All tocojapan.com links get utm_source=brevo, utm_medium=email, utm_campaign={slug}, utm_content={stock_ref|banner|cta}.

### Commands
- php artisan mailer:import --now
- php artisan mailer:backfill --from=YYYY-MM-DD
- php artisan mailer:render-sample {n}     # storage/app/private/mailer/sample-{n}.html
- php artisan mailer:brevo:check           # read-only check of key, senders, lists
- php artisan mailer:brevo:setup           # creates missing contact attributes in Brevo
- php artisan mailer:brevo:save-template   # inactive placeholder in the Brevo template library (TOC-TPL-008)
- php artisan mailer:sync-stats            # hourly; status + stats for campaigns pushed in the last 60 days
- php artisan mailer:cleanup               # daily; deletes run logs/audit older than 12 months
- php artisan mailer:backfill --pause | --resume | --status

## Supplier stock (OnePrice, JWT …)
Supplier-feed vehicles live in the same `vehicles` table, linked by `supplier_id` (+ `supplier_ref` = the supplier's id).
`suppliers` row with `is_own_stock` = Toco's yard; feed settings (pricing, publishing, delist guard) are JSON on the supplier (Admin → Catalogue → Suppliers).
- Sync key is (supplier_id, supplier_ref); slugs are created once and never rewritten. Missing vehicles in a *full* file become status `delisted` (never deleted); `/vehicles/{slug}` of a gone vehicle 301s to the make/model listing; old WP URLs `/vehicle/{id}` and `/one-price` redirect.
- Imports are staged: upload → preview (Admin → Catalogue → Stock imports) → approve → applied by `App\Jobs\RunSupplierImport` in 35 s slices (scheduler worker has a 50 s timeout). Engine: `App\Suppliers\SupplierImporter`; feed parsers in `App\Suppliers\Feeds` (OnePrice = 29 positional columns, no header, SJIS ok).
- Supplier stock is quote-only unless `allow_online_checkout`; hotlinked photos (`external_photos`) are used while no photos are uploaded; Mailer, homepage "latest", new-arrival badges and sitemap are own stock unless the supplier setting allows.
- Web upload limit is 2 MB (php.ini) — big files go zipped or via the inbox `storage/app/private/supplier-inbox/{supplier}/`, or the CLI.
- New Filament resources/pages only appear after `php artisan filament:cache-components` (panel component cache in bootstrap/cache/filament); run `php artisan optimize` after deploying.
- php artisan suppliers:import oneprice /path/file.csv[.gz|.zip] [--partial] [--apply [--force]]   # same pipeline; without --apply it stops at preview
- php artisan suppliers:reprice [supplier]   # daily 03:15 after currency:fetch-rates
- php artisan suppliers:purge [--dry-run]    # weekly; delisted > N days with no orders/quotes/favourites
- Run tests with the config cache bypassed (bootstrap/cache/config.php otherwise points tests at production MySQL):
  APP_CONFIG_CACHE=/tmp/x.php APP_ROUTES_CACHE=/tmp/y.php APP_EVENTS_CACHE=/tmp/z.php APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
