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
- Filament builds navigation once per app instance: in tests, use one acting user per test when asserting menus,
  and call Filament::setCurrentPanel('admin') before Livewire::test() on a page.
- Commit messages reference requirement IDs, e.g. "TOC-EXT-004 exclude system local parts".
- Add or update tests (tests/Mailer) with every change; run the test suite before finishing a task.

### Email HTML rules (TOC-TPL-*)
- 600px table layout, inline CSS, Arial/Helvetica, no JS, no web fonts, no external CSS.
- Two-column vehicle grid, stacks under 480px; odd last card left, right cell empty.
- Brevo tags {{ mirror }} and {{ unsubscribe }} stay literal in output (escape in Blade: @{{ mirror }}).
- Total HTML for 12 vehicles < 90 KB. Brand red #E30613 (site colour, OPEN-10).
- All tocojapan.com links get utm_source=brevo, utm_medium=email, utm_campaign={slug}, utm_content={stock_ref|banner|cta}.

### Commands
- php artisan mailer:import --now
- php artisan mailer:backfill --from=YYYY-MM-DD
- php artisan mailer:render-sample {n}     # storage/app/mailer/sample-{n}.html
- php artisan mailer:brevo:check           # read-only check of key, senders, lists
- php artisan mailer:brevo:setup           # creates missing contact attributes in Brevo
