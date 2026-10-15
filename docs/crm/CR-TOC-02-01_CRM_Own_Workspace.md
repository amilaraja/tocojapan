# Change Request CR-TOC-02-01 — TOCO CRM in its own workspace at /crm

| | |
|---|---|
| Project | TOCO CRM (proposal WEB-TOC-02, SRS-TOC-02) |
| Client | Toco International Co. Ltd. |
| Supplier | Mobiz International (Pvt) Ltd |
| Date | 15 October 2026 |
| Status | Done, live 15 October 2026 |

## 1. Request

Move the TOCO CRM out of the website admin (`/admin`) into its own workspace at **tocojapan.com/crm**, so the sales team works in the CRM only and cannot open the website admin.

## 2. Before the change

- The CRM screens (Dashboard, My Work, Pipeline, Cases, Customers, Tasks, Queues, Team Overview, SLA Monitor, Reports, Steps, Email Templates, CRM Settings, CRM Help, My CRM Profile) live inside the admin panel under the "CRM" menu.
- Sales staff log in at `/admin`. CRM-only staff are sent on to the CRM Dashboard, but they are still inside the admin area, and every admin screen decides for itself whether to hide from them.
- CRM case pages link to admin records (quotes, orders, proforma invoices, contact and spare-part enquiries); admin order and quote pages link back to the CRM case.

## 3. What changes

1. **New CRM workspace at /crm** with its own login page (with the same bot protection as the admin login), TOCO CRM branding, colours and notification bell.
2. **All CRM screens move to /crm** and are removed from the admin menu. Behaviour, data, roles and permissions inside the CRM stay exactly as they are now.
3. **Sales team isolated:** users whose only roles are CRM roles (crm_agent, crm_team_lead, crm_operations, crm_admin) can sign in to /crm only. If they open /admin they are sent to /crm.
4. **Site admins keep both:** super admins and admins can use /admin and /crm with one sign-in, with an "Open CRM" link in the admin and a "Website admin" link in the CRM.
5. **Old links keep working:** every old `/admin/crm/...` address (bookmarks, earlier staff emails, digests) redirects to the same screen under `/crm`.
6. **Staff emails and digests** (new case, reassignment, SLA alerts, daily digest) link to `/crm`.
7. **Linked records for sales staff:** quotes, orders, proformas and enquiries linked to a case stay listed on the CRM case page with their reference and status. Only site admins get the link that opens the record in the admin.
8. **Admin side:** the "CRM Case" link on admin order / quote pages opens the case in /crm.
9. **Help and guides updated:** CRM Help, staff guide, demo test script.
10. **Tests updated** and the full CRM test suite re-run; deployed with a restore point.

## 4. Not included

- Changes to CRM behaviour, stages, emails to buyers, reports or the buyer tracking pages (`/track/...` stay as they are).
- A separate sub-domain (e.g. crm.tocojapan.com). Can be quoted separately if wanted.
- Editing orders, quotes or proformas from inside the CRM (they stay admin tasks).

## 5. Impact

- Sales staff sign in at **tocojapan.com/crm** from the go-live day; their user names and passwords do not change.
- Short notice to staff is needed (new address). Old addresses redirect, so nothing breaks.
- No downtime expected; the move is done in one deployment with a restore point.

## 6. Cost

No charge: covered by the TOCO CRM project (WEB-TOC-02).

## 7. Approval

Approved by Mobiz on behalf of the CRM project; delivered 15 October 2026.
