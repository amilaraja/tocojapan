# TOCO Mailer: release and deliverability (Phase 7)

**Covers:** TOC-DLV-001, TOC-DLV-002, TOC-NFR-002, TOC-NFR-003.

## 0. How this server is set up

This repository's working copy **is** the live docroot (`public/`). Files are live as soon as they change on disk.

"Releasing" therefore means:
1. merging `feat/toco-mailer` into `main` on GitHub;
2. making sure this working copy is on `main`;
3. running the steps below.

There is no separate staging copy. Develop and test with `php artisan test` (SQLite in memory), never against the live database.

Run artisan as the site user so files and caches get the right owner:

```bash
cd /home/tocojapan.com/public_html
A="sudo -u tocoj2379 /usr/local/lsws/lsphp83/bin/php artisan"
```

## 1. Code and database

Status on 24 Sep 2026 is shown in brackets.

```bash
git checkout main && git pull                       # after the PR is merged
sudo -u tocoj2379 composer install --no-dev -o      # no new packages needed (see note)
$A migrate --force                                  # mailer_ tables [done 24 Sep]
$A db:seed --class="App\Modules\Mailer\Database\Seeders\MailerPermissionSeeder" --force   # [done 24 Sep]
$A storage:link                                     # already present: public/storage → storage/app/public
$A config:cache && $A route:cache && $A view:cache  # only if the site normally caches these
```

Packages: `google/apiclient` and `tijsverkoyen/css-to-inline-styles` are already in `vendor/`. The first is a direct requirement; the second comes with Laravel. No `composer require` is needed.

## 2. Scheduler and queue worker (OPEN-07) [done 24 Sep]

- Crontab of `tocoj2379` (check with `crontab -l -u tocoj2379`):
  ```
  * * * * * cd /home/tocojapan.com/public_html && /usr/local/lsws/lsphp83/bin/php artisan schedule:run >> /dev/null 2>&1
  ```
- `php artisan schedule:list` must show:
  - `mailer:import` every minute (it only runs when the interval has passed, and stays quiet until Gmail is set up);
  - `mailer:sync-stats` hourly;
  - `mailer:cleanup` daily at 04:10 UTC;
  - `queue:work --queue=mailer,default …` every minute.
- Restarting PHP: recycle only this site's `lsphp` processes. Never restart the whole OpenLiteSpeed server (the box is shared).

## 3. Secrets (TOC-NFR-001)

| Secret | Where | Notes |
|---|---|---|
| Brevo key | Mailer settings (stored encrypted with `APP_KEY`), or `MAILER_BREVO_API_KEY` in `.env` | The screen shows only the last 4 characters |
| Google service account key (JSON) | A file **outside** `public_html`, e.g. `/home/tocojapan.com/secure/toco-gmail-sa.json`, owned by `tocoj2379`, `chmod 600` | Its path goes in Mailer settings. The form rejects paths inside `public/` |
| Mailbox address | Mailer settings, or `MAILER_GMAIL_MAILBOX` | |

```bash
mkdir -p /home/tocojapan.com/secure && chown tocoj2379:tocoj2379 /home/tocojapan.com/secure && chmod 700 /home/tocojapan.com/secure
# copy the key file in, then:
chown tocoj2379:tocoj2379 /home/tocojapan.com/secure/toco-gmail-sa.json && chmod 600 /home/tocojapan.com/secure/toco-gmail-sa.json
```

**Warning:** if `APP_KEY` changes, the saved Brevo key can no longer be read. The settings screen will then show "No key saved yet"; paste the key again.

Optional `.env` keys (all have defaults):

```
MAILER_DISPLAY_TIMEZONE=Asia/Tokyo
MAILER_GOOGLE_SA_KEY_PATH=/home/tocojapan.com/secure/toco-gmail-sa.json
MAILER_GMAIL_MAILBOX=first@toco-iont.com     # OPEN-01: confirm the address
MAILER_BREVO_API_KEY=
MAILER_BREVO_BASE_URL=https://api.brevo.com/v3
MAILER_BREVO_CAMPAIGN_URL=https://app.brevo.com/marketing-campaign/edit/%d   # "Open in Brevo" link; check once with a real draft
```

Remember the memory note on `.env.production`: when `APP_ENV=production`, Laravel loads `.env.production` over `.env` if that file exists.

## 4. Google Workspace (A1, A2)

TOCO's Workspace admin needs to do the following. Send them these steps.

1. In Google Cloud, create a project, enable the **Gmail API**, create a **service account**, and create a **JSON key** for it.
2. In Workspace Admin (Security, Access and data control, API controls, **Domain-wide delegation**), add the service account's **client ID** with exactly one scope:
   `https://www.googleapis.com/auth/gmail.readonly`
3. Send the JSON key to Mobiz over a secure channel. It is stored as described in section 3.

The importer only ever asks for that one scope (hard rule 2, tested). It impersonates only the configured mailbox.

## 5. Brevo (A4, A6)

1. Paste the key in **Mailer settings**, then press **Test Brevo connection**.
2. Run the one-off setup commands:
   ```bash
   $A mailer:brevo:check          # read-only: account, senders, lists, missing attributes
   $A mailer:brevo:setup          # creates SOURCE, TOCO_IMPORTED_AT, TOCO_LAST_ENQUIRY_AT, COUNTRY, PHONE if missing
   $A mailer:brevo:save-template  # TOC-TPL-008: inactive placeholder template in the Brevo library
   ```
3. Add the approved senders (Importer, Approved senders), choosing their lists and consent mode.

## 6. DNS for Brevo (A7, TOC-DLV-001/002)

DNS is hosted at **Linode** (ns1–ns5.linode.com). What `dig` showed on 24 Sep 2026:

| Record | Current value | Status |
|---|---|---|
| `tocojapan.com` TXT (Brevo code) | `brevo-code:a51cd32e027c5da5a1688ca470c672ed` | ✅ present |
| `brevo1._domainkey` CNAME | `b1.tocojapan-com.dkim.brevo.com` | ✅ present |
| `brevo2._domainkey` CNAME | `b2.tocojapan-com.dkim.brevo.com` | ✅ present |
| `_dmarc` TXT | `v=DMARC1; p=none; rua=mailto:dmarc-reports@tocojapan.com,mailto:rua@dmarc.brevo.com; … aspf=s; adkim=s; …` | ✅ p=none with reporting |
| `tocojapan.com` TXT (SPF) | `v=spf1 include:_spf.google.com -all` | ⚠ **Brevo is not included** |

**Change needed (TOCO / DNS owner).** Replace the SPF record. Do not add a second SPF record: a domain may have only one.

```
v=spf1 include:_spf.google.com include:spf.brevo.com -all
```

Notes:
- DMARC uses strict alignment (`aspf=s; adkim=s`). Brevo mail passes DMARC through **DKIM**: the brevo1/brevo2 keys sign with `d=tocojapan.com`. SPF alignment may still fail because Brevo uses its own return path, and that is fine.
- The default Brevo sender must be an address **@tocojapan.com** (TOC-DLV-002). Check this under Brevo, Senders.
- After the change, check that Brevo shows the domain as authenticated (Brevo, Senders & IP, Domains). Then send a Brevo test to a Gmail address and check "Show original": DKIM should be PASS with `tocojapan.com`, and DMARC PASS.

## 7. Checks after release

```bash
$A route:list --path=mailer        # every route starts admin/mailer (also tests/Mailer/Feature/RoutesTest.php)
$A test --testsuite=Mailer         # all green
$A schedule:list | grep -E 'mailer|queue'
curl -sI https://tocojapan.com/storage/email-assets/placeholder-vehicle.jpg | head -1   # 200
```

- **Backups (TOC-NFR-003):** the `mailer_` tables live in the existing application database, so any whole-database backup includes them. Confirm once by restoring the latest backup somewhere safe and running `SHOW TABLES LIKE 'mailer\_%'` (11 tables).
- **Email images:** they are files under `storage/app/public/email-assets/` and must be part of the file backup. Campaigns already sent in Brevo point at them.
