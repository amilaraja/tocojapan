# TOCO Mailer: UAT record

**Status 24 Sep 2026:** every scenario is covered by an automated test (Gmail and Brevo faked). The **live** run needs TOCO access: A1/A2 (Workspace delegation), A4 (Brevo key and lists), A5 (`.eml` samples), A7 (SPF change). Fill in the live columns during the Phase 8 walkthrough.

| # | Scenario | Automated evidence (passing) | Live result | Date / by |
|---|---|---|---|---|
| UAT-1 | Website inquiry reaches the Mailbox from an approved sender, and the buyer is in the Brevo list with SOURCE and dates; run log and audit show it | `ImportRunnerTest` "imports from the fixture and writes the run log and audit"; `ContactSyncTest` "creates a new contact…" | ☐ | |
| UAT-2 | Same buyer enquires a week later: no duplicate, TOCO_LAST_ENQUIRY_AT updated, TOCO_IMPORTED_AT unchanged | `ContactSyncTest` "updates an existing contact without overwriting…" | ☐ | |
| UAT-3 | Unsubscribed buyer enquires again: skipped "unsubscribed" and stays unsubscribed | `ContactSyncTest` "skips blacklisted contacts…"; `BrevoGuardTest` (blacklist flag can't be written) | ☐ | |
| UAT-4 | 6-vehicle campaign with a new banner, previewed on mobile, pushed: a Brevo draft with the same content, lists and sender, and no schedule | `CampaignBuilderTest` "pushes a draft with no schedule, and preview HTML equals pushed HTML"; `BannerTest` | ☐ | |
| UAT-5 | One vehicle marked sold: builder flags it, push blocked until removed; re-push updates the same draft | `CampaignBuilderTest` "blocks push for a sold vehicle…" + "updates the same Brevo draft on re-push" | ☐ | |
| UAT-6 | Approver sends in Brevo: within 1 hour Mailer shows Sent with statistics; links carry UTM | `CampaignBuilderTest` "syncs status and statistics"; `CampaignRendererTest` "tags every tocojapan.com link…" | ☐ | |
| UAT-7 | Duplicate the sent campaign: new Draft, same vehicles and banner, no Brevo link | `CampaignBuilderTest` "duplicates a sent campaign…" | ☐ | |

## TOC-TPL-007 client screenshot matrix

Send the 6-vehicle sample from **Brevo** (Brevo, Campaigns, the pushed draft, Send a test) to test inboxes, then save screenshots here as `qa/tpl007-<client>-<light|dark>.png`.

| Client | Light | Dark |
|---|---|---|
| Gmail web | ☐ | ☐ |
| Gmail Android | ☐ | ☐ |
| Gmail iOS | ☐ | ☐ |
| Outlook 365 desktop | ☐ | ☐ |
| Outlook web | ☐ | ☐ |
| Apple Mail macOS | ☐ | ☐ |
| Apple Mail iOS | ☐ | ☐ |

Done so far:
- Headless Chrome renders at 600 px and 375 px: 2 columns on desktop, 1 per row on mobile, 5 vehicles laid out 2/2/1 (Phase 5).
- One SMTP test send of the 6-vehicle sample to amilaraja@gmail.com (24 Sep). Not sent through Brevo, so the `{{ mirror }}` and `{{ unsubscribe }}` links are still literal.
