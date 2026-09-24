# TOCO Mailer

A module inside the tocojapan.com Laravel app (`app/Modules/Mailer`). It has three parts:

1. **Inbox importer:** reads the TOCO Google mailbox (read-only), finds buyer email addresses in messages from approved senders, and adds them to Brevo lists.
2. **Email template:** a Brevo-ready HTML email in the tocojapan.com red and black style.
3. **Campaign Builder:** staff pick vehicles and a banner, preview the email, and push it to Brevo **as a draft**. Testing, approval and sending happen **only inside Brevo**. TOCO Mailer can never send or schedule email.

Spec: `docs/email_automation/SRS-TOC-01_TOCO_Mailer.docx`. Build notes: `integration-notes.md`. Release: `deploy.md`. Progress and evidence: `progress.md`, `qa/`.

## Who can do what

Access is granted on the existing **Users** screen by adding a role:

| Role | Can |
|---|---|
| `mailer_marketer` | Mailer Overview, Campaigns, Banners, Preview, Push to Brevo as draft |
| `mailer_admin` | Everything above, plus the Importer (senders, ignore list, run log, contact search, backfill, rule tester) and Mailer settings |
| `super_admin` | Both, automatically |

A user needs one of these roles (or admin/sales/super_admin) to log in at /admin.

## Configuration

Everything staff might change is in **Mailer, Mailer settings** (admins only):

- **Connections:** the Brevo key (stored encrypted, only the last 4 characters shown), the mailbox address, where the Google key file is, the check interval (5–1440 minutes, default 15), and TOCO's own email domains (never imported).
- **Email header:** logo, top bar text, up to 3 header links.
- **Email footer:** the request block, the fraud warning, and company details (address, phone, WhatsApp, email, "why you get this email").

Server-side set-up (cron, key file location, `.env` keys, DNS) is in `deploy.md`.

## Rotating credentials

**Brevo key**
1. In Brevo (Settings, SMTP & API, API keys), create a new key.
2. In Mailer settings, paste it into **Brevo key**, press **Save settings**, then **Test Brevo connection**.
3. Delete the old key in Brevo.

**Google service account key**
1. In Google Cloud, create a new JSON key for the same service account. The domain-wide delegation stays as it is.
2. Copy it to the server outside `public_html`, e.g. `/home/tocojapan.com/secure/toco-gmail-sa-2027.json`, then `chown tocoj2379:tocoj2379` and `chmod 600` it.
3. Update **Google key file location** in Mailer settings. Press **Run now** under Importer, Status, then check the run log shows OK.
4. Delete the old key in Google Cloud and the old file on the server.

**APP_KEY:** if the app key is ever rotated, the stored Brevo key can't be decrypted. Paste it again in Mailer settings.

## Adding an approved sender

1. Importer, **Approved senders**, **Add sender**.
2. **Messages from:** an exact address (`leads@portal.com`) or a whole domain (`@portal.com`).
3. Choose the **Brevo lists** (press **Refresh** if a new list is missing) and the consent mode:
   - **Direct:** contacts are added straight to the lists.
   - **Confirm first:** Brevo emails the contact a confirmation link, and they join the lists only after clicking it. You need the Brevo confirmation template number and a "thank you" page link.
4. Optional **field rules** pick up details from the message text. Put `( )` around the part to keep, e.g. `Name: (.+)`, `Country: (.+)`, `\b(E\d{5})\b` for a stock number.
5. Paste a real message into the **Rule tester** to check the result before relying on it. The tester saves nothing and sends nothing to Brevo.
6. Add 2 sample `.eml` files to `tests/Mailer/Fixtures/eml/<sender>/` with a test in `tests/Mailer/Feature/ExtractionTest.php` (TOC-NFR-004).

## Adding a banner

1. Mailer, **Banners**, **Upload banner**.
2. Use a JPG or PNG of **1200 × 440 pixels** (within 2%), up to 1 MB. Any other size is refused with a message giving the size.
3. Give it a name, the link to open when clicked (empty means the stock list), and the text shown when images are off.
4. It is saved as an optimised JPEG under 150 KB. **Archive** hides a banner from new campaigns, but campaigns that used it keep it.

## How it works (for developers)

| Piece | Where |
|---|---|
| Gmail (read-only) | `Domain/Importer/GmailReader` (`gmail.readonly` only) behind `MailboxReader` |
| Extraction | `Domain/Importer/Extraction` (+ `AddressExtractor`, `AddressValidator`, `FieldRuleEngine`), shared with the Rule tester |
| Import runs | `Domain/Importer/ImportRunner`: atomic lock, one DB transaction per message, checkpoint only on success, alert after 3 failures, backfill batches of 100 |
| Brevo | `Domain/Brevo/BrevoClient`. **`BrevoGuard` rejects every send, schedule or unsubscribe change before any HTTP call** |
| Contacts | `Domain/Brevo/ContactSync`: never touches blacklisted contacts, never overwrites names or `TOCO_IMPORTED_AT` |
| Vehicles | `Domain/Vehicles/VehicleSource` (read-only), `EmailImageService` (540×310 JPEG ≤ 70 KB) |
| Email | `resources/views/email/*` → `CampaignRenderer` (Blade → UTM → CSS inliner). The preview and the push use the same call |
| Push | `Domain/Campaigns/CampaignPusher`: draft only, updates the same draft, refuses once Brevo has sent it |

Commands: `mailer:import [--now]`, `mailer:backfill --from= | --pause | --resume | --status`, `mailer:render-sample {n}`, `mailer:sync-stats`, `mailer:cleanup`, `mailer:brevo:check`, `mailer:brevo:setup`, `mailer:brevo:save-template`.

Tests: `php artisan test --testsuite=Mailer`. Nothing live is called: Gmail, Brevo and DNS are all faked.

To remove the module: delete `app/Modules/Mailer`, `tests/Mailer`, and the hooks listed in `integration-notes.md` section 9, then drop the `mailer_` tables.
