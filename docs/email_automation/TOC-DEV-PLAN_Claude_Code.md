# TOCO Mailer: Claude Code development plan

**Document:** TOC-DEV-PLAN v1.1 (module inside the tocojapan.com Laravel app) | **Project:** WEB-TOC-01 (approved) | **Spec:** SRS-TOC-01 v1.1 | **Date:** 23 September 2026
**Delivery window:** 10 working days from the day the access in Section 1 is available.

This plan is written to be used directly with Claude Code, working in the existing tocojapan.com repository (Laravel, Mobiz CMS pattern, auto trading edition). TOCO Mailer is built as a module inside that app. It reuses the app's admin login, admin layout, permission mechanism, vehicle models, database, scheduler and queue.

Section 3 is the Mailer section to append to the repository's `CLAUDE.md`. Section 5 gives one session prompt per phase, each with its requirement IDs and a definition of done. Work phase by phase, and do not start a phase until the previous one passes its checks.

---

## 1. Before day 1 (access checklist)

| # | Item | From | SRS ref | Needed by |
|---|---|---|---|---|
| A1 | Confirm Mailbox address and that it is Google Workspace | TOCO | OPEN-01 | Phase 2 |
| A2 | Google Cloud project, service account, domain-wide delegation approved for `gmail.readonly` on the Mailbox | TOCO Workspace admin (we send exact steps) | OPEN-01 | Phase 2 |
| A3 | Laravel/PHP versions, vehicle model fields and CMS permission mechanism (Phase 1 documents these from the codebase) | Mobiz | OPEN-02 | Phase 1 |
| A4 | Brevo API key, verified sender, target lists created | TOCO | OPEN-03 | Phase 3 |
| A5 | Approved sender list plus 2 sample `.eml` files per sender | TOCO | OPEN-04 | Phase 2 |
| A6 | Consent mode per sender (Direct / Confirm first) | TOCO | OPEN-05 | Phase 3 |
| A7 | DNS access for SPF, DKIM and DMARC | TOCO | OPEN-06 | Phase 7 |
| A8 | Approved email and admin designs from Claude Design | Mobiz | TOC-DESIGN-BRIEF | Phase 5 |
| A9 | Scheduler cron and queue worker confirmed on the tocojapan.com server | Mobiz | OPEN-07 | Phase 3 |

Phases 1, 4 and 5 need no client access and can start immediately. Nothing is released to production until A1 to A7 are done.

---

## 2. Architecture decisions

| Decision | Choice | Reason |
|---|---|---|
| Placement | Module inside the existing tocojapan.com Laravel app, following the Mobiz CMS module conventions already in the codebase | Vehicle data, admin auth and layout already exist; one codebase to maintain |
| Code location | The CMS module folder convention (for example `app/Modules/Mailer`), with its own service provider, routes, migrations, views and config | Isolated and removable |
| Routes | Under the existing admin prefix and admin middleware; only `/email-assets/...` files are public | TOC-GEN-001, TOC-NFR-002 |
| Permissions | Mailer Admin and Mailer Marketer, registered through the CMS's existing role/permission mechanism | TOC-GEN-002, TOC-GEN-003 |
| Admin UI | Existing admin layout, Blade components and front-end stack. Add Alpine.js and SortableJS only if missing. Use Livewire only if the admin already uses it | TOC-GEN-005; no second UI system |
| Database | Existing app database, new tables prefixed `mailer_` | Covered by current backups |
| Vehicles | `VehicleSource` service wrapping the existing Vehicle Eloquent model and photo relation, read methods only | TOC-VEH-001 |
| Queue and scheduler | App's existing queue connection and scheduler; add cron entry and worker if missing | OPEN-07 |
| Gmail | `google/apiclient`, service account with domain-wide delegation, subject = Mailbox, scope `gmail.readonly` | Unattended, read-only, one mailbox only |
| Brevo | Thin in-house client over Laravel `Http` (no SDK) | Full control of the no-send guard and retries |
| Images | Image library already used by the app if present, otherwise `intervention/image` v3; files in `storage/app/public/email-assets` | Controlled size, served from tocojapan.com |
| Email HTML | Blade components producing table-based markup; `tijsverkoyen/css-to-inline-styles` as a final inline pass | Same renderer for preview and push (TOC-CB-003) |
| Tests | The app's existing test runner (PHPUnit or Pest), `Http::fake()`, Gmail fixtures from TOCO sample `.eml` files | Deterministic, no live calls |

### Module layout (adapt names to the CMS convention found in Phase 1)

```
app/Modules/Mailer/
  MailerServiceProvider.php
  routes/admin.php
  config/mailer.php
  database/migrations/        mailer_* tables (SRS 8.1)
  Domain/
    Importer/   GmailReader, MessageParser, AddressExtractor, AddressValidator,
                FieldRuleEngine, ImportRunner, ImportState
    Brevo/      BrevoClient, BrevoGuard, ContactSync, CampaignPusher, StatsSync, DTOs
    Vehicles/   VehicleSource (wraps existing Vehicle model), VehicleDTO, EmailImageService
    Campaigns/  CampaignRenderer, UtmTagger, VehicleRecheck
  Jobs/         RunImport, RunBackfillBatch, PushCampaign, SyncCampaignStats, CleanupLogs
  Http/Controllers/Admin/   Overview, Campaigns, Banners, Senders, IgnoreList, RunLog,
                            Audit, RuleTester, Backfill, Settings
  Models/       Mailer* models
  Policies/
  resources/views/admin/    screens extending the existing admin layout
  resources/views/email/    campaign.blade.php + components
tests/Mailer/
  Fixtures/eml/             one folder per approved sender
docs/mailer/                SRS, integration notes, design exports, QA evidence, README, staff guide
```

---

## 3. CLAUDE.md section (append to the repository's existing CLAUDE.md)

```markdown
## TOCO Mailer module (app/Modules/Mailer)
Spec: docs/mailer/SRS-TOC-01.md (requirement IDs TOC-AREA-NNN). Integration notes: docs/mailer/integration-notes.md.
Three parts: Gmail to Brevo contact importer, Brevo email template, Campaign Builder that pushes DRAFT campaigns to Brevo.
Built inside tocojapan.com: reuse the admin auth, admin layout, permission mechanism and Vehicle models.
Follow the existing Mobiz CMS module conventions; do not introduce a second UI framework.

### Hard rules (never break)
1. NEVER send or schedule email. BrevoClient must reject sendNow, sendTest to lists, scheduledAt,
   and any POST to /emailCampaigns/{id}/sendNow or /sendTest. Covered by tests/Mailer/Unit/BrevoGuardTest.php. (TOC-CMP-002)
2. Gmail scope is gmail.readonly ONLY. Never request modify, labels or send scopes. (TOC-IMP-001)
3. The Mailer module never creates, updates or deletes vehicle records. VehicleSource uses read queries only. (TOC-VEH-001)
4. Never store email bodies or attachments. Keep message ID, sender, received time and extracted fields only. (TOC-LOG-003)
5. Never change a Brevo contact's blacklisted status; skip blacklisted and hard-bounced contacts. (TOC-BRV-002/003)
6. Never overwrite existing Brevo FIRSTNAME/LASTNAME/COUNTRY/PHONE or TOCO_IMPORTED_AT. (TOC-BRV-004)
7. Preview HTML and pushed HTML come from the same CampaignRenderer call. (TOC-CB-003)
8. Secrets only via encrypted mailer_settings or .env; never log API keys or email bodies.
9. Do not modify existing CMS code except admin menu registration, permission seeding and service provider registration.
   Any other change to shared code must be listed in docs/mailer/integration-notes.md.

### Conventions
- All Mailer tables prefixed mailer_. All Mailer routes under the admin prefix and admin middleware, except /email-assets files.
- Marketer-facing text in plain English: no "API", "JSON", "token". (TOC-GEN-007)
- Store UTC, display Asia/Tokyo.
- Timeouts: Gmail and Brevo 20s. (TOC-NFR-005)
- Commit messages reference requirement IDs, e.g. "TOC-EXT-004 exclude system local parts".
- Add or update tests with every change; run the test suite before finishing a task.

### Email HTML rules (TOC-TPL-*)
- 600px table layout, inline CSS, Arial/Helvetica, no JS, no web fonts, no external CSS.
- Two-column vehicle grid, stacks under 480px; odd last card left, right cell empty.
- Brevo tags {{ mirror }} and {{ unsubscribe }} stay literal in output (escape in Blade: @{{ mirror }}).
- Total HTML for 12 vehicles < 90 KB.
- All tocojapan.com links get utm_source=brevo, utm_medium=email, utm_campaign={slug}, utm_content={stock_ref|banner|cta}.

### Commands
- php artisan mailer:import --now
- php artisan mailer:backfill --from=YYYY-MM-DD
- php artisan mailer:render-sample {n}     # storage/app/mailer/sample-{n}.html
- php artisan mailer:brevo:check           # read-only check of key, senders, lists
- php artisan mailer:brevo:setup           # creates missing contact attributes in Brevo
```

---

## 4. Environment keys (added to the existing tocojapan.com .env)

```
MAILER_DISPLAY_TIMEZONE=Asia/Tokyo
MAILER_GOOGLE_SA_KEY_PATH=/home/<user>/secure/toco-gmail-sa.json   # outside web root, chmod 600
MAILER_GMAIL_MAILBOX=first@...        # OPEN-01
MAILER_BREVO_API_KEY=                 # or saved encrypted via Mailer Settings
MAILER_BREVO_BASE_URL=https://api.brevo.com/v3
```

Database, queue connection and APP_URL are the existing tocojapan.com settings.

---

## 5. Phases and Claude Code session prompts

Timeline is 10 working days. Each phase ends with the test suite green and a short demo note in `docs/mailer/progress.md`. Develop on a branch and a staging copy of tocojapan.com, never directly on production.

### Phase 1 (Day 1): Codebase study, module scaffold, permissions, settings
**Covers:** TOC-GEN-001 to 007, TOC-NFR-001, TOC-NFR-002. Closes OPEN-02 and OPEN-07.

> **Prompt:** Read CLAUDE.md and docs/mailer/SRS-TOC-01.md sections 2, 3, 5 and 7. First study the tocojapan.com codebase and write docs/mailer/integration-notes.md covering: Laravel and PHP versions; how existing CMS modules are structured, registered and routed; the admin layout, menu registration and UI components; the role/permission mechanism; the Vehicle model, its fields and relations (map every field in TOC-VEH-002, noting badge, previous price, status values and where photos are stored); the image library in use; the queue connection and whether the scheduler and a worker are configured on the server. Stop and show me the notes before writing any code.
>
> After approval, scaffold the Mailer module following that convention: service provider, admin routes under the existing admin prefix and middleware, config, all mailer_ migrations from SRS 8.1, Mailer Admin and Mailer Marketer permissions via the existing mechanism with a seeder, the Mailer admin menu section (TOC-GEN-004), an Overview page placeholder, and a Mailer Settings screen in the admin layout with encrypted values (Brevo key shown as last 4 characters only). Write tests for TOC-GEN-001 to 004.

**Done when:** integration notes approved; Mailer menu visible only to permitted users; a Marketer gets 403 on settings; migrations run clean on staging; tests pass.

### Phase 2 (Days 2 to 3): Gmail reader and extraction
**Covers:** TOC-IMP-001, 004 to 007, TOC-EXT-001 to 009, TOC-LOG-002, 003

> **Prompt:** Implement `GmailReader` using google/apiclient with a service account, domain-wide delegation (subject = MAILER_GMAIL_MAILBOX) and scope gmail.readonly only. Build the Gmail query from active approved senders (`from:(a OR b OR @domain)`). Use `users.history.list` from the stored history ID when available, otherwise `messages.list` with an `after:` timestamp. Fetch with `format=full`, decode base64url parts, and never persist bodies.
>
> Implement `AddressExtractor` (plain text, HTML including mailto, optional Reply-To), normalisation (TOC-EXT-003), exclusions (TOC-EXT-004), `AddressValidator` with syntax plus MX/A check cached 24h (TOC-EXT-005), `FieldRuleEngine` for per-sender patterns (TOC-EXT-006) and the per-message limit (TOC-EXT-007). Build Approved Senders CRUD, Ignore List and the Rule Tester (no side effects) as admin screens in the existing layout. Enforce a unique `mailer_processed_messages.gmail_message_id` and an atomic lock for runs. Use the .eml files in tests/Mailer/Fixtures/eml; every sender must have at least one passing extraction test. Fake all Brevo calls in this phase.

**Done when:** fixture tests for every sender pass; running the importer twice over the fixtures processes each message once; the Rule Tester makes no Brevo request.

### Phase 3 (Day 4): Brevo contact sync, runs, logs
**Covers:** TOC-IMP-002, 003, 008, 009, TOC-BRV-001 to 007, TOC-LOG-001, 004, TOC-GEN-006

> **Prompt:** Implement `BrevoClient` (Http, 20s timeout, retries on 429/5xx with exponential backoff up to 5, respects Retry-After) and `BrevoGuard`, which throws on any send or schedule operation, with a unit test proving no HTTP request is made.
>
> Implement `ContactSync`:
> - GET the contact first; skip if emailBlacklisted or hard-bounced.
> - Otherwise POST /contacts with updateEnabled=true and listIds.
> - Set SOURCE and TOCO_LAST_ENQUIRY_AT every time, TOCO_IMPORTED_AT only on create, and name/country/phone only when empty in Brevo.
> - For consent mode "Confirm first", use POST /contacts/doubleOptinConfirmation with templateId, redirectionUrl and includeListIds.
>
> Add `mailer:brevo:setup` to create the contact attributes if missing. Wire the `RunImport` job into the app's existing scheduler, adding the cron entry and a queue worker on the server if Phase 1 found them missing. Include:
> - the configurable interval and a Run now button;
> - the backfill command and screen (batches of 100, resumable cursor);
> - a checkpoint that only advances on success;
> - an alert email to Mailer Admins after 3 consecutive failures.
>
> Build the Run Log, Audit search and Overview counters, plus the daily clean-up of records older than 12 months. Use `Http::fake()` in all tests.

**Done when:** UAT-1 to UAT-3 pass against a Brevo test list; a forced-failure test shows no lost messages.

### Phase 4 (Day 5): Vehicle source and images
**Covers:** TOC-VEH-001 to 007

> **Prompt:** Using the field map in docs/mailer/integration-notes.md, implement `VehicleSource` (search, find, findMany) as a read-only wrapper over the existing Vehicle model and its photo relation, returning `VehicleDTO`. Rules:
> - Available vehicles only by default; reserved vehicles optional for Mailer Admins; sold vehicles never selectable.
> - Filters from TOC-VEH-004, pagination of 20.
> - Reuse existing query scopes rather than duplicating their logic.
> - Add a test proving VehicleSource performs no writes.
>
> Implement `EmailImageService`. It takes the primary photo from existing storage and centre-crops it to 540x310 as a JPEG under 70 KB. It caches files by vehicle ID and source hash under storage/app/public/email-assets, served from tocojapan.com. It uses a branded placeholder when a photo is missing.

**Done when:** 10 real vehicles match their tocojapan.com pages field by field; searching E02056 returns it first in under 2 seconds; image files are under 70 KB.

### Phase 5 (Days 5 to 6): Email template
**Covers:** TOC-TPL-001 to 009, TOC-CMP-005

> **Prompt:** Implement the approved Claude Design email (docs/mailer/design/email/) as Blade components in the module's resources/views/email, following the Email HTML rules in CLAUDE.md exactly. `CampaignRenderer::render(Campaign): string` builds the full HTML from mailer_settings, the campaign and its vehicle snapshots. It runs `UtmTagger` on every tocojapan.com href, then the CSS inliner. Keep `@{{ mirror }}` and `@{{ unsubscribe }}` literal.
>
> Add tests for:
> - no script, link or style-import tags;
> - a 600px main table;
> - section order;
> - both Brevo tags present;
> - UTM parameters on all site links;
> - 12-vehicle HTML under 90 KB;
> - odd-count layout 2/2/1.
>
> Add the `mailer:render-sample {n}` command. Save a placeholder version to the TOCO Brevo template library via POST /smtp/templates (isActive false).

**Done when:** the sample HTML passes all tests and has been sent from Brevo to test accounts in Gmail, Outlook and Apple Mail, with screenshots in docs/mailer/qa/.

### Phase 6 (Days 7 to 8): Campaign Builder and push
**Covers:** TOC-BAN-001 to 003, TOC-CB-001 to 008, TOC-CMP-001 to 004, 006, 007

> **Prompt:** Build the Campaign Builder inside the existing admin layout from the approved admin design (docs/mailer/design/admin/). Use the admin's existing UI components and front-end stack, adding Alpine and SortableJS only if missing.
>
> **Campaign details and banners:**
> - Campaign fields with the validation limits from TOC-CB-001.
> - Sender and list pickers loaded from Brevo (cached, with a Refresh button).
> - Banner library: upload validation of 1200x440 within 2 percent and max 1 MB, optimised to under 150 KB, with archive.
>
> **Vehicles:**
> - Vehicle search panel using VehicleSource.
> - Add 2 to 12 vehicles; SortableJS drag reorder plus keyboard up/down buttons; snapshot on add.
> - `VehicleRecheck` on open and before push: show a price-changed notice, and block push for sold or missing vehicles.
> - Preview modal rendering CampaignRenderer output in an iframe at 600 and 375 widths.
>
> **Pushing to Brevo:**
> - `PushCampaign` job: POST /emailCampaigns (name, subject, previewText, sender, htmlContent, recipients.listIds, no scheduledAt). Use PUT instead when brevo_campaign_id exists and GET shows the status is draft; otherwise refuse with "duplicate this campaign".
> - Store pushed_html_hash; editing after a push sets status Changed since push.
> - Open in Brevo button and a Duplicate action.
> - Hourly `SyncCampaignStats` for campaigns pushed in the last 60 days.
> - Test that preview HTML equals pushed HTML.

**Done when:** UAT-4, UAT-5 and UAT-7 pass against the TOCO Brevo account (draft only, nothing sent).

### Phase 7 (Day 9): Release to production and deliverability
**Covers:** TOC-DLV-001, 002, TOC-NFR-002, 003

> **Prompt:** Write docs/mailer/deploy.md for releasing the module through the normal tocojapan.com deployment:
> - composer install (new packages: google/apiclient and the CSS inliner), then migrate --force and the permission seeder;
> - storage:link check, then config, route and view cache;
> - confirm the scheduler cron and queue worker are running;
> - place the Google service account key outside the web root with 600 permissions;
> - add the MAILER_ .env keys;
> - confirm the mailer_ tables are in the existing backup.
>
> List the DNS records to add for Brevo: Brevo code TXT, DKIM, SPF include:spf.brevo.com merged into the existing SPF record, and DMARC p=none with rua. Run `php artisan route:list` and confirm every Mailer route is admin-only except /email-assets.

**Done when:** the module is live in the tocojapan.com admin; Brevo shows the domain as authenticated; a Brevo test send passes DKIM and SPF.

### Phase 8 (Day 10): Backfill, UAT, handover
**Covers:** UAT-1 to UAT-7, TOC-TPL-007, TOC-NFR-004, 006, 007

> **Prompt:** Run the full test suite on production config. Run the backfill from the agreed date (OPEN-09) and summarise the results. Walk through UAT-1 to UAT-7 and record evidence in docs/mailer/qa/uat.md. Complete the client screenshot matrix for TOC-TPL-007. Write docs/mailer/README.md covering configuration, credential rotation for the Brevo and Google keys, how to add a sender and how to add a banner. Also write a one-page plain-English staff guide, docs/mailer/staff-guide.md, for TOCO Marketers.

**Done when:** all UAT scenarios pass, the README and staff guide are delivered, the walkthrough call is held and the second payment is invoiced.

---

## 6. Test matrix (minimum)

| Area | Tests |
|---|---|
| Access | Mailer routes behind admin auth; permission checks; menu visibility |
| Extraction | One fixture test per approved sender; exclusions; normalisation; MX failure; per-message limit; field rules |
| Importer | Idempotency; lock; checkpoint on failure; backfill resume; alert after 3 failures |
| Brevo | Create vs update; blacklisted skip; bounced skip; attribute non-overwrite; retries; double opt-in mode; guard rejects send and schedule |
| Vehicles | Mapping; available-only; sold never selectable; no writes; image size; placeholder |
| Email | Structure; tags; UTM; size under 90 KB; odd count layout |
| Builder | 2 to 12 limits; recheck blocks push; preview equals pushed HTML; re-push updates the same draft; duplicate |

## 7. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Mailbox is personal Gmail, not Workspace | Raise at A1. Unattended read-only access needs Workspace. Fallback: a Gmail forwarding rule from the personal mailbox into a Workspace mailbox, agreed before Phase 2 |
| Module changes affect the live site | Isolated module, mailer_ tables, admin-only routes, hard rule 9, branch plus staging release |
| Sender email formats change | Rule Tester and audit reasons make failures visible; field rules editable without code |
| Scheduler or worker not running on the server | Checked in Phase 1 (OPEN-07); Overview shows last run time so a stalled importer is obvious |
| Gmail clipping or Outlook rendering | Size test in CI; test sends in Phase 5 before builder work |
| Accidental send | BrevoGuard plus test; no send control anywhere in the admin; draft only |
| Consent questions | Confirm first mode per sender; SOURCE and dates recorded on every contact |
