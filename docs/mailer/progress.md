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

Not yet run on the live database: `php artisan migrate --force` and `php artisan db:seed --class="App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder" --force`. The dry run creates only `mailer_` tables.

Deferred to later phases: menu items Campaigns and Banners (Phase 6), Importer (Phases 2 and 3), Brevo "Test connection" (Phase 3), and adding `app/Modules/Mailer` to the admin theme `@source` when custom markup needs Tailwind classes.
