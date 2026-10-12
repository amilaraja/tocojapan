# TOC-CRM-DEV-01 · Build plan (TOCO CRM)

**Implements:** SRS-TOC-02 v1.0 + proposal WEB-TOC-02 (7 Oct 2026, Rs 150,000 fixed)
**Prepared by:** Mobiz International (Pvt) Ltd · 13 October 2026 · draft 1
**Builds in:** `/home/tocojapan.com/public_html` (the live docroot: every saved file is live)

---

## 1. Decisions (13 Oct 2026, Amila)

| Topic | Decision |
|---|---|
| Client answers (proposal p.8: staff list, hours, checklists, reservation days, agent name) | Not in yet. Build with the proposed defaults; each one is an admin setting, so TOCO's answers are typed in, not coded. |
| Codebase | MobizCRM runs on mis.mobiz.lk and is not on this server. The TOCO edition is built natively as `app/Modules/Crm`, following the SRS and MobizCRM's concepts. |
| Scope beyond SRS phase 1 | **In:** buyer email replies coming back into the Case (the proposal promises it; the SRS had it as phase 2, OPEN-07); payment slip upload by buyers (proposal); saved list views per staff member (SRS DSH-006). **Out:** 2FA (SRS NFR-006 "should"), mobile app push (optional extra), WhatsApp API. |
| Rollout | Live as each part lands. There is no master switch. Customer-facing emails each have an on/off rule (NTF-002), so they can be held back per event. |
| TOCO's open items (13 Oct) | Use the defaults below for all of them. |
| Buyer replies mailbox | The Mailer's read-only Gmail mailbox. |
| Old order-status emails | Replaced by the CRM customer emails (`email.replace_order_emails`). `Order::transitionTo()` skips `OrderStatusChanged` while it is on (shared-code change). |
| Staff nicknames | Each staff member has a nickname in the CRM (crm_agent_status.nickname). Buyers see only the nickname: email sign-off, sender name "Ken · TOCO Japan", the portal's "Your TOCO contact". No nickname = "TOCO Sales Team". Real names never reach buyers; Reply-To is still the owner's own address. |
| Billing | WEB-TOC-02 fixed price. Not a billing-tab item. Note: the proposal lists "saved list views for each staff member" inside the Rs 45,000 optional extra; it is built here at the user's request. |

### Proposed defaults used until TOCO answers

| Item | Default | Where to change |
|---|---|---|
| Business hours | Mon–Fri 09:00–18:00 Asia/Tokyo; Japanese public holidays 2026–2027 | CRM → Settings → Business hours |
| First reply | 2 business hours | CRM → Settings → Service levels |
| Reply to customer message | 1 business day | same |
| Quote sent (stage 01) | 1 business day | same |
| Payment due (reservation) | 3 days | same |
| Vessel booked after payment | 10 days | same |
| Documents couriered after B/L | 5 days | same |
| Stale | 3 days (agent), 5 days (admins) | same |
| Unassigned escalation | 30 business minutes | same |
| Open-case cap per agent | 30 | Queues |
| Mini steps | SRS §3.2 table | CRM → Settings → Stages and steps |
| Lost reasons | Price, Bought elsewhere, No reply, Vehicle sold to someone else, Shipping cost, Other | CRM → Settings → Lost reasons |
| Close reasons | Duplicate, Spam, Not a buyer | same |
| Name buyers see | The owner's nickname (none set = "TOCO Sales Team"); Reply-To = the owner | CRM → My profile (nickname), Settings → Customer emails |
| Review request | 3 days after "Car received", link to the testimonials page | Templates |
| Queues | Sales (vehicle sale), Spare parts, General | Queues |

## 2. Ground rules

1. **Own module** `app/Modules/Crm`, laid out like the Mailer and the Partner API: provider, `config/crm.php`, Domain, Filament, Http, Console, resources. Tables use the `crm_` prefix. Tests go in `tests/Crm` with their own testsuite.
2. **Shared-code register.** Every edit outside the module is listed in `docs/crm/integration-notes.md` (providers.php, AdminPanelProvider, User::canAccessPanel, the account menu, the vehicle page, the order screens).
3. **Hooks, not rewrites.** Intake listens to Eloquent `created` / `updated` events on the existing models (Quote, QuoteMessage, ProformaInvoice, Order, OrderMessage, ContactInquiry, SparePartInquiry, Vehicle, VehicleHold). Listeners are wrapped so a CRM failure never breaks a customer submission (NFR-004).
4. **Notifications** are queued on the database queue. The worker already runs every minute; a `crm` queue is added to its list. In-app notifications use the panel's existing bell (database notifications).
5. **Times** are stored in UTC. SLA clocks use Asia/Tokyo business hours. Each staff member picks a display time zone (NFR-005).
6. **Requirement IDs** go in commit messages and test names (`CRM-STG-003` …).
7. **Git:** the work commits to the branch the live docroot runs (`feat/partner-api`), one commit per phase or less.
8. **Tests:** Pest/SQLite with the config-cache bypass from CLAUDE.md, then `chown -R tocoj9771:tocoj9771 storage bootstrap/cache`. The Mailer and PartnerApi suites must still pass (INT-005).

## 3. Data model (SRS §11.3, as built)

| Table | Notes |
|---|---|
| crm_customers | email (unique, lower-case), name, phone, whatsapp, country_id, user_id (nullable), merged_into_id, notes |
| crm_cases | reference C-YYYY-NNNNN, type (vehicle_sale / spare_parts / general), source, customer_id, vehicle_id, dest_port_id, dest_country_id, payment_mode, queue_id, owner_id, stage (1–5, null for support types), status (support types), state, value_usd, value_basis, lost/close reason, next_follow_up_at, last_customer_message_at, last_staff_response_at, last_activity_at, first_response_at, stale_flagged_at, merged_into_id, portal_token, closed_at |
| crm_case_links | case_id + morph (quote, proforma, order, contact, spare part) |
| crm_stage_templates / crm_step_templates | 5 fixed stages; steps with key, label, required, customer_visible, responsible_role, condition (lc / inspection / supplier_stock), data fields, auto key, sort |
| crm_case_steps | step status per Case: done_at, done_by, data JSON, skipped (condition not applicable) |
| crm_stage_history | entered_at / left_at per stage, reason (for moves back) |
| crm_activities | timeline: type (email_out, email_in, customer_message, note, call, whatsapp, meeting, task, stage, step, assignment, document, state, system), user_id, body, meta JSON, visible_to_customer |
| crm_tasks | case_id, assignee_id, title, due_at, reminded_at, done_at |
| crm_queues / crm_queue_members | case types and sources per queue; members with rotation pointer and open-case cap |
| crm_agent_status | away_from / away_until, timezone, notify_mode (immediate / digest), allow_pick |
| crm_sla_policies / crm_business_calendars / crm_holidays | targets in minutes; hours per weekday; holiday dates |
| crm_sla_timers | per Case per measure: started_at, due_at, paused_at, paused_minutes, warned_at, breached_at, met_at |
| crm_email_templates | key, name, audience (customer / staff), subject, body with {placeholders} |
| crm_notification_rules / crm_notification_log | event × channel on/off; sent / failed log |
| crm_reasons | lost and close reasons |
| crm_saved_views | user, list, name, filters JSON |
| crm_documents | via spatie medialibrary on CrmCase (collection `documents`, custom property visible_to_customer, doc_type) |
| crm_inbound_messages | Gmail message id (dedupe), case_id, from, subject, received_at |

The CRM settings (sender name, show owner name, review link, Gmail inbound on/off) live in `crm_settings` (key/value) under the module, not in spatie settings, so the module stays self-contained.

## 4. Phases

The proposal's day anchors: days 1–2 for logins and intake from every website source; days 3–4 for checklists, emails, reminders, buyer pages and the owner overview; day 5 for the import, training and go-live. Payment milestone 2 (Rs 45,000) is "five-step tracking shown working", at the end of P3.

### P1 · Foundation and intake (days 1–2)
1. Module skeleton, config, migrations, seeders (stages, steps, queues, SLA policy, calendar with JP holidays, reasons, templates, rules).
2. Roles `crm_admin`, `crm_agent`, `crm_operations`, and permission `crm.team_lead`. super_admin and admin act as CRM Admin. `canAccessPanel` admits the CRM roles. Policy: an agent sees only own Cases (GEN-002 → 403).
3. Customer matching by email (+ link to user; guest records), CUS-001.
4. Intake from Quote, ProformaInvoice, Order, ContactInquiry, SparePartInquiry, QuoteMessage, OrderMessage. Join an open Case on the same customer + vehicle (CAS-002). The reference generator. Manual Case creation (CAS-003).
5. Assignment: queues, round robin skipping away members and those at the cap, returning customer → previous owner, unassigned queue + admin alert (ASN-001..004, 006).
6. Filament: the Cases list (stage bar, SLA colours, search on ref / name / email / phone / stock no / chassis / B/L), the Case view (header, linked records, timeline), Customers. "CRM Case C-…" links on the order, quote, proforma and enquiry screens (INT-002).

### P2 · Stages, mini steps and order sync (day 3)
1. Stage engine: gating with "Complete: …" (STG-003), data capture on tick (STG-004), conditional steps (STG-006), move back with reason (STG-007), history and time in stage (STG-008).
2. Auto-completion from events (STG-005): first reply sent, quote priced/sent, proforma issued, order created, PayPal capture, bank transfer confirmed, vehicle reserved/sold (hold service), shipped (B/L, vessel, ETA), delivered.
3. Order status kept in step both ways (STG-009, §3.3).
4. Support statuses for spare parts / general; conversion of a general enquiry to a vehicle sale (§3.4). States, with lost/close reasons (CAS-007) and a required follow-up date (ACT-005).
5. Double-promise warning: when a car linked to open Cases is reserved or sold for someone else, the other Cases' owners are warned (proposal p.2).

### P3 · Follow-up tools and notifications (day 3–4)
1. Timeline with filters, notes, call/WhatsApp/meeting logs, click-to-chat (ACT-001, 003, 006).
2. Email from the Case with templates and placeholders, Reply-To the owner, the reference in the subject, stored on the timeline (ACT-002). Bank details come from Payment settings only and are not editable in emails (proposal p.6).
3. Tasks and reminders (ACT-004), documents with customer visibility (ACT-007).
4. Notification matrix §8.1: in-app + email; customer emails with the five-stage tracker; rules on/off per channel (NTF-002); digest mode (NTF-004); log (NTF-005); never to spam/duplicate (NTF-006). Replaces StaffMail routing for new enquiries (INT-004): unassigned → CRM admins.

### P4 · SLA engine (day 4)
1. Business-time calculator (calendar + holidays), timers per measure, pause/resume on Waiting/On hold (SLA-001, 002).
2. `crm:tick` every minute: 75 % warnings, breaches, stale (3 / 5 days), unassigned escalation, task reminders, payment-due reminders, the review request (SLA-003, 004, ASN-006).
3. Daily digest at 08:30 JST: agents (overdue, due today, stale) and admins (team summary, breaches, unassigned).
4. SLA results kept after close (SLA-006).

### P5 · Customer view (day 4)
1. My account → "My purchases": list and detail with the five-stage tracker, customer-visible steps with dates, vessel/B/L/ETA, documents, the next action (POR-001..003).
2. Actions: accept quote, pay (existing flow), "Documents received", "Car received", message TOCO, **upload payment slip** (POR-004, 005, proposal).
3. Guest secure link (signed token) and claim-on-register by email (POR-006). Vehicle page badge (POR-007). 404 on others' Cases (NFR-002).

### P6 · Dashboards, reports and saved views (day 4–5)
1. My work (DSH-001), pipeline board with filters (DSH-002), team overview (DSH-003), SLA monitor with 60 s polling (DSH-004).
2. Reports with date range and CSV/Excel export: funnel, lost reasons, sources, time in stage, response times, won value by month and by country (DSH-005).
3. Saved views per user on the Cases, Customers and Tasks lists (DSH-006).
4. Settings screens: queues and members, business hours and holidays, SLA targets, stages and steps, email templates, notification rules, reasons, CRM settings; agent Away status with at-risk list and bulk reassign (GEN-004, ASN-005, 007).

### P7 · Buyer email replies (day 5)
Reuse the Mailer's read-only Gmail reader (gmail.readonly scope only, no change to the Mailer). `crm:inbound` every 5 minutes reads new mail in the configured mailbox. A message matches a Case by the `[C-YYYY-NNNNN]` reference in its subject, else by sender email → that customer's latest open Case. It is added to the timeline as a customer message (plain text, trimmed, quoted history stripped), notifies the owner and restarts the reply clock. Unmatched mail is ignored. Dedupe on the Gmail message id.

### P8 · Import, Mailer handshake, go-live (day 5)
1. `crm:import` (INT-001): open quotes, unexpired proformas, orders not delivered/cancelled, unhandled contact and spare-part enquiries, plus the last 6 months of closed records for history (OPEN-09 default). Dry-run first.
2. Mailer handshake (proposal p.6): customers with an open vehicle-sale Case at stage 02–05 are excluded from campaign pushes. Done through a read-only exclusion hook, without changing the Mailer's rules (INT-005); the Mailer test suite must stay green.
3. UAT-01..12 as automated tests where possible; the runbook and staff guide `docs/crm/staff-guide.md`; the training checklist.

## 5. Open with TOCO (unchanged from SRS §12.1)
OPEN-01 staff list (no personal logins exist yet: only admin@tocojapan.com). OPEN-02 hours/SLA. OPEN-03 steps. OPEN-04 LC steps. OPEN-05 email wording/owner name. OPEN-06 reasons. OPEN-08 dealer availability. OPEN-10 payment due. OPEN-12 review platform. Until staff exist, every new Case lands in Unassigned and alerts the admins.
