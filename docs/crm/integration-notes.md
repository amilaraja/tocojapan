# TOCO CRM: integration notes

Module `app/Modules/Crm` (SRS-TOC-02, proposal WEB-TOC-02). Plan: `docs/crm/TOC-CRM-DEV-01_Build_Plan.md`.
Operating guide: admin **CRM → CRM Help** (source `app/Modules/Crm/docs/help.md`).

## 1. How it runs

- **Switch:** `CRM_ENABLED=true` in `.env` (on since 2026-10-13). Off = no intake, routes, menus or schedule; the old staff and order emails return.
- **Intake:** Eloquent events on Quote, QuoteMessage, ProformaInvoice, Order, OrderMessage, ContactInquiry, SparePartInquiry, VehicleHold, Vehicle (sold) and User (created). Each runs after the customer's transaction commits (`DB::afterCommit`) and swallows its own errors (`Intake::safely`), so the CRM can never fail or roll back a submission.
- **Schedule:**
  - `crm:tick` every minute: SLA warnings and breaches, stale Cases, unassigned alerts, task reminders, payment reminders, end of on-hold, review requests.
  - `crm:inbound` every 5 minutes: buyer email replies, read through the Mailer's read-only Gmail reader.
  - `crm:mailer-sync` hourly at :07: keeps the Brevo "buying now" list.
  - `crm:digest` at 08:30 JST: the morning summary.
- **Queue:** emails go on the `crm` queue (job `SendCrmEmail`, 3 tries), and every send or failure is logged in `crm_notification_log`. The bell uses Filament database notifications.
- **Settings:** business settings live in `crm_settings` (CRM → Settings); defaults are in `Support/CrmSettings::DEFAULTS`. `alerts.extra_admin_emails` = first@toco-int.com (the inbox the old "Staff notification emails" used).
- **Commands:**
  - `crm:import [--apply] [--skip=…]`: go-live import. Run 2026-10-13: 20 records became 16 Cases, with internal test senders skipped.
  - `crm:mailer-sync`
  - `crm:inbound [--days=2]`
  - `crm:digest [--dry]`

## 2. Decisions (13 Oct 2026)

- Defaults from the plan for all of TOCO's open items. Buyer replies come from the Mailer's Gmail mailbox.
- The CRM emails replace the old `OrderStatusChanged` emails and the StaffMail enquiry alerts.
- Buyers see only the owner's nickname (CRM → My profile), never real names.
- Deviation: a cancelled or refunded order does not close the Case as Lost. It is logged and the owner is told, because an abandoned checkout is often a buyer still deciding.
- Deviation: a buy-now order ticks "Enquiry acknowledged" (the website confirmation), but the first-reply clock keeps running until staff write personally.

## 3. Shared code touched (register)

| Date | File | Change | Why |
|---|---|---|---|
| 2026-10-13 | `bootstrap/providers.php` | registers `CrmServiceProvider` | module |
| 2026-10-13 | `app/Providers/Filament/AdminPanelProvider.php` | "CRM" navigation group first; CRM pages and resources | GEN-001 |
| 2026-10-13 | `app/Models/User.php` | `canAccessPanel` admits crm_admin, crm_agent, crm_operations | GEN-002 |
| 2026-10-13 | `app/Models/Order.php` | `transitionTo()` skips `OrderStatusChanged` while the CRM is on and `email.replace_order_emails` is set | client decision: CRM emails replace them |
| 2026-10-13 | `app/Support/StaffMail.php` | `send()` hands over to the CRM while it is on | INT-004 |
| 2026-10-13 | `routes/console.php` | queue worker serves `mailer,crm,default` | CRM emails |
| 2026-10-13 | `resources/views/components/layouts/account.blade.php` | "My Purchases" in the account menu (only when the route exists) | POR-001 |
| 2026-10-13 | `resources/views/components/layouts/site.blade.php` | "My purchases" in the mobile menu (only when the route exists) | POR-001 |
| 2026-10-13 | `resources/views/vehicles/show.blade.php` | logged-in buyers see "Your enquiry: Stage …" (partial `crm.portal.partials.vehicle-badge`) | POR-007 |
| 2026-10-13 | `resources/views/crm/portal/*` | buyer pages, kept under resources/ so the site's Tailwind build sees their classes | POR |
| 2026-10-13 | `app/Modules/Mailer/Domain/Campaigns/CampaignPusher.php` | exclusion list on pushed campaigns (also in the Mailer register §9) | proposal p.6 |
| 2026-10-13 | `phpunit.xml`, `tests/Pest.php` | Crm testsuite; `CRM_ENABLED=true` for tests | NFR-007 |
| 2026-10-13 | `tests/Feature/{ProformaInvoice,StaffMail,PayPalWebhook}Test.php` | the three legacy-email tests run with the CRM off | old path kept testable |

The "CRM Case C-…" links on the order, quote, contact and spare-part screens (INT-002) come from a Filament render hook in `CrmServiceProvider`. Those resource files are unchanged.

## 4. Requirement status (phase 1 + agreed extras)

Built and tested: GEN-001..005 (GEN-005 by layout, 390 px), CAS-001..009, CUS-001..004, STG-001..009, ASN-001..008, SLA-001..006, ACT-001..008, NTF-001..006, POR-001..007, DSH-001..006, NFR-002, 004, 005, 007, INT-001..005, inbound replies (OPEN-07), payment slips, saved views.
Not built: NFR-006 2FA (decision: out). NFR-001 load at 20k Cases is not benchmarked yet; the indexes are in place. NFR-003 24-month audit uses the timeline (no pruning job deletes it).

## 5. Tests

`--testsuite=Crm` (47 tests). Helpers: `Tests\Crm\Support\CrmData`. The Mailer, PartnerApi and JctFeed suites stay green. Feature has 6 failures that existed before this work (auth, password reset, sitemap).
