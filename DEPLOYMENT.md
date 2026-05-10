# Deploying Toco Japan to CyberPanel (`new.webserverlk.com`)

Target host: `new.webserverlk.com` · Control panel: CyberPanel · Web server: OpenLiteSpeed · DB: MySQL/MariaDB.

> Production credentials live in the local `.env.production` file (gitignored).
> Upload it to the server as `.env` — don't paste them anywhere else.

---

## 1. Create the website in CyberPanel

1. **Websites → Create Website**
   - Domain: `new.webserverlk.com`
   - PHP: **8.3** (or 8.4 if available)
   - SSL: Let's Encrypt (tick the box)
   - Email: your address
2. After creation, **Websites → List Websites → Manage** for `new.webserverlk.com` and grab the **document root path** — typically `/home/new.webserverlk.com/public_html`.
3. In **PHP Selector**, ensure these extensions are enabled (most are default):
   `bcmath, ctype, fileinfo, gd, imagick, intl, mbstring, openssl, pdo_mysql, redis, tokenizer, xml, zip`.

## 2. Upload the codebase

You can use either approach:

**A. Git pull on the box (preferred).** SSH in as the website's user and:
```
cd /home/new.webserverlk.com/public_html
git clone https://github.com/<your-org>/<repo>.git .   # or rsync from this machine
```

**B. SFTP upload.** Compress the project locally (`tar --exclude=node_modules --exclude=vendor -czf toco.tgz .`) and upload via CyberPanel's File Manager, then extract.

## 3. Configure the document root for OpenLiteSpeed

CyberPanel's default doc root is the public_html folder. Laravel needs the doc root to point at the **`public/` subdirectory**.

1. **Websites → List Websites → Manage → vHost Conf** (top-right).
2. Find the `docRoot` line and change it to:
   ```
   docRoot                   /home/new.webserverlk.com/public_html/public
   ```
3. **Save** and **Restart LiteSpeed**.

(Alternative: leave the docRoot as `public_html` and add a `.htaccess` redirect, but the doc-root method is cleaner.)

## 4. Drop in `.env`

Upload the local `.env.production` you have to `/home/new.webserverlk.com/public_html/.env`, then:

```
cd /home/new.webserverlk.com/public_html
php8.3 artisan key:generate
```

Confirm `DB_HOST=127.0.0.1` is correct (CyberPanel default).

## 5. Composer + node build

```
cd /home/new.webserverlk.com/public_html
composer install --no-dev --optimize-autoloader --no-interaction
npm ci --omit=dev
npm run build
```

If the box doesn't have node, build assets locally and upload the `public/build/` folder.

## 6. Migrate + seed

```
php8.3 artisan migrate --force
php8.3 artisan db:seed --force
```

The seeders create:
- 4 roles (super_admin, admin, sales, customer)
- 23 countries + 25 ports + 30 makes + 130 vehicle models + 15 body types
- The default super-admin `admin@tocojapan.com / password` — **change this immediately** via `/admin/login` → profile menu.
- 30 sample vehicles + 5 CMS pages (delete these before going live with real WP data: `php artisan tinker` → `\App\Models\Vehicle::truncate()` and `\App\Models\Page::query()->whereIn('slug', ['about','faq','contact','sri-lanka','tanzania'])->delete()`).

## 7. Storage symlink + permissions

```
php8.3 artisan storage:link
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

(CyberPanel's user is sometimes `lscpd` or the website's named user — adjust accordingly.)

## 8. Cache configs (production)

```
php8.3 artisan config:cache
php8.3 artisan route:cache
php8.3 artisan view:cache
php8.3 artisan event:cache
```

## 9. Queue worker

We use the `database` queue driver. CyberPanel can host a long-running PHP process via supervisor; the simpler path is a CyberPanel cron:

**Cron Jobs → Add Cron**
```
* * * * *  cd /home/new.webserverlk.com/public_html && php8.3 artisan queue:work --queue=default --max-time=55 --stop-when-empty
```

(Alternative: install supervisor + add `/etc/supervisor/conf.d/toco.conf` with the standard Laravel template.)

## 10. Scheduled tasks

Add another cron:
```
* * * * *  cd /home/new.webserverlk.com/public_html && php8.3 artisan schedule:run >> /dev/null 2>&1
```

(Currently no scheduled tasks are defined, but this is safe to add now so future ones land for free.)

## 11. WordPress data migration (when ready)

Edit `.env` and add the live WP DB creds:
```
WP_DB_HOST=...
WP_DB_DATABASE=...
WP_DB_USERNAME=...
WP_DB_PASSWORD=...
WP_DB_PREFIX=wp_
```

Then **dry-run** first, in this order:
```
php8.3 artisan migrate:wp-all --dry-run
```

Reports land in `storage/migration-reports/*.json`. Eyeball them. When happy:
```
php8.3 artisan migrate:wp-all
```

The orchestrator runs makes/models → vehicles → pages → customers. The auction inventory is deliberately **excluded** (per the `--exclude-auction` default) — only the ~134 owner-stock products migrate. Customers all get a random password and must use the *Forgot password* flow on first login.

## 12. Cutover

When UAT passes:
1. Lower DNS TTL for `tocojapan.com` 24h ahead.
2. Final delta-migration: `php artisan migrate:wp-all` once more so any new WP data since the staging migration is captured.
3. Switch DNS A record for `tocojapan.com` to the new server.
4. Watch logs for 24h: `tail -f storage/logs/laravel.log`.

---

# UAT smoke checklist

Run through these once `https://new.webserverlk.com/` resolves.

## Public, unauthenticated
- [ ] `/` — homepage renders, hero carousel rotates, search panel tabs work, 8 featured vehicles show with photos, browse-by-make and browse-by-body-type sidebars populate with counts.
- [ ] `/vehicles` — listing returns 30 sample vehicles, filters narrow results (e.g. `?make=toyota`, `?year_from=2020`).
- [ ] `/vehicles/{any-slug}` — detail page renders spec table + CIF widget; widget calculates against any port.
- [ ] `/cif` — both tabs ("From stock" + "Manual entry") return a CIF breakdown with port + insurance + total.
- [ ] `/about`, `/faq`, `/contact`, `/sri-lanka`, `/tanzania` — all 200 with correct content.
- [ ] `/sitemap.xml` — XML response, includes home + vehicle URLs + page URLs.
- [ ] `/robots.txt` — disallows `/admin`, points to sitemap.

## Auth flows
- [ ] `/register` → create a customer account → land on `/dashboard`.
- [ ] Heart icon on a vehicle card saves to `/favorites` and shows filled state.
- [ ] On a vehicle detail page, "Request a quote" form submits → redirects to `/quotes/{id}` with the message in the thread.
- [ ] Reply on the customer side lands in the thread and bumps `last_customer_reply_at`.
- [ ] Logout → /login → log back in.

## Admin (super_admin only)
- [ ] `/admin/login` → admin@tocojapan.com → dashboard shows 4 stat cards + Latest quotes widget.
- [ ] `/admin/vehicles` → create a new vehicle, upload 2 photos, set status published, save → URL is reachable on the public site.
- [ ] `/admin/quotes/{id}` → reply via the Conversation panel → customer sees it on `/quotes/{id}`.
- [ ] `/admin/users` → search by email, change a customer's role to `sales`, send password reset.
- [ ] `/admin/pages` → edit /about, save → public `/about` reflects the change.
- [ ] `/admin/settings` → change CIF insurance % to 2.0, save → CIF widget on a vehicle now uses 2%.
- [ ] `/admin/activity-log` → shows recent updates with diffs.
- [ ] Header search → typing "toyota" returns vehicles matching across resources.
- [ ] CSV exports on Vehicles/Quotes/Users download non-empty `.csv` files.

## API for the future Expo app
- [ ] `POST /api/v1/auth/register` returns `{ data: { user, token } }`.
- [ ] `POST /api/v1/auth/login` returns a token; `GET /api/v1/auth/me` with that token returns the user.
- [ ] `GET /api/v1/vehicles` paginated; `?make=toyota` narrows.
- [ ] `POST /api/v1/cif/calculate` with port_id + price_fob + m3 returns the breakdown.
- [ ] `POST /api/v1/favorites/{slug}` toggles for the bearer user.
- [ ] `POST /api/v1/quotes` creates a quote; `GET /api/v1/quotes/{id}` returns it; `POST /api/v1/quotes/{id}/messages` posts a reply.

## Performance / hardening
- [ ] `php artisan optimize` — no warnings.
- [ ] `php artisan about` — APP_ENV=production, APP_DEBUG=false, all caches enabled.
- [ ] Hit homepage twice in a row — second response should be sub-100ms (LiteSpeed cache).
- [ ] Try `/admin/login` over HTTP — should redirect to HTTPS.
- [ ] Try a known-bad password 6× → rate-limit kicks in (Laravel default).
- [ ] Confirm `storage/logs/laravel.log` has no `ERROR` or `EMERGENCY` entries after the smoke pass.

---

# Rollback plan

If UAT fails or production cutover misbehaves:

1. Switch `tocojapan.com` DNS A record back to the old server (TTL was lowered to ~5 min ahead of cutover).
2. The Laravel app at `new.webserverlk.com` keeps running for diagnosis — no destructive cleanup needed.
3. Capture `storage/logs/laravel.log` and the relevant `storage/migration-reports/*.json` before any retry.

The new platform never writes to the WP database (the `wp` connection is read-only by convention). Even a botched migration only mangles `new_newdb`, which can be dropped + recreated and re-run from scratch via `php artisan migrate:fresh --seed --force` then `migrate:wp-all`.
