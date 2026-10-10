# Partner Stock API: runbook

For Toco staff and Mobiz support. Admin screens are under **Partner Network** (needs the `partners.admin` permission: super_admin and admin roles).
Commands run from `/home/tocojapan.com/public_html` as the site user: `sudo -u tocoj9771 php artisan …`.

## Switches (.env)

| Setting | Effect | After changing |
|---|---|---|
| `PARTNER_API_ENABLED=true` | API under `/partner/v1` answers; `/partner/docs` is public. False: every partner URL is 404, docs for admins only. | `php artisan config:cache` |
| `HOLDS_ENFORCED=true` | PayPal, bank transfer and proforma must win a hold before taking an order. Admin holds and "Under offer" work either way. | `php artisan config:cache` |
| `PARTNER_API_HOST=api.tocojapan.com` | Binds the API to that host (DNS + SSL + LiteSpeed vhost alias first). | `php artisan optimize`, then `systemctl restart lshttpd` |
| `PARTNER_API_IMAGE_KEYS=k2:<secret>,k1:<old>` + `PARTNER_API_IMAGE_KID=k2` | Photo link signing keys. Unset = a key derived from APP_KEY (`d1`). | `php artisan config:cache` |

Rollback of either switch: set it back to `false` and `php artisan config:cache`. Nothing else changes.

## Onboard a partner

1. Partner Network → Partners → **Add partner**. Pick **Listing** unless the partner takes buyers' money. Set scope, prices, photo size, limits, optional server addresses and notification address.
2. On the partner page → **Connection keys** → **Create key**. Copy the client ID and secret from the one-time window and send them securely (phone, password manager). Never by plain email.
3. If they gave a notification address: **Notification secret** (top of the partner page) → send it the same way. **Send test event** shows whether their site answers.
4. Send them the guide: `https://tocojapan.com/partner/docs`.
5. Watch **Usage** for their first successful sync.

## Rotate a partner's key

Partner page → Connection keys → **Rotate**. The old secret keeps working for 7 days. Send the new one securely. To cut the old one off at once, use **Revoke** on the key instead and issue a new one.

## Leaked key or misbehaving partner

- **Suspend** (Partners → row action): every key and photo link stops on the next request. Reversible with **Reactivate**.
- **Revoke key** (Connection keys): that key only, immediately.
- **Revoke partner**: permanent; all keys and photo links die. The partner stays in the list (filter Status: Revoked) for the audit trail.
- Every change is in the partner audit log (`partner_audit_logs`, append-only) with who, when, before and after.

## Offboarding a partner

1. Revoke the partner (typed name + reason).
2. Remind them in writing of the contract duty to delete re-hosted photos and vehicle data.
3. Nothing to purge at Toco: photo links are partner-bound and die with the revocation; cached photo variants are shared by profile, not by partner.

## Rotate the photo signing key (yearly, or after a leak)

1. Generate a secret: `openssl rand -base64 32`.
2. `.env`: `PARTNER_API_IMAGE_KEYS=k2:<new>,k1:<current>` and `PARTNER_API_IMAGE_KID=k2` (keep the old key listed so links already issued keep working until they expire, at most 24 hours).
3. `php artisan config:cache`.
4. After 24 hours, remove the old key and cache the config again. After a leak, remove it at once (partners' next sync gets new links).

## Holds

- **Hold for buyer** (vehicle page or list): reserves a car, for example while a JWT Japan buyer negotiates. Pick the partner under "Where the buyer came from" so the sale is credited.
- **Holds** page: every active hold with a countdown; Extend, Release, Mark sold.
- Holds lapse automatically (`holds:expire`, every minute). A paid website order keeps its hold with no expiry until staff confirm the sale with **Mark as sold**.
- A paid order whose car was held by someone else gets an admin note starting "PAID BUT THE CAR IS HELD BY ANOTHER CHANNEL": refund or release the other hold.

## Scheduled jobs (cron runs `schedule:run` every minute)

| Command | When | Does |
|---|---|---|
| `holds:expire` | every minute | Lapses ended holds; cars return to sale |
| `partners:webhooks` | every minute | Hold-ending warnings to selling partners; sends due change notifications (40 s budget) |
| `partners:monitor` | every 10 minutes | Bell alerts: holds ending in 6 h, notifications failing over 1 h, unusual traffic |
| `partners:monitor --daily` | 00:05 Japan time | Bell alerts: holds that ran out unused yesterday, contracts ending in 14 days |
| prune tokens / request logs | daily | Expired tokens; request logs older than 12 months |

## Checks when something is wrong

- Partner says "401 invalid_client": key revoked or mistyped. Look at Connection keys (status, last used).
- Partner says "403 ip_not_allowed": their server address changed. Edit the partner, Limits tab.
- Partner says photos 404: links expired (they must sync again), or their photo settings changed (links signed before the change die by design).
- Notifications failing: Usage page shows the last error. Their scheduled sync still keeps them correct.
- Admin bell silent: the panel polls every 60 s; alerts go only to users with `partners.admin`.

## Tests

```
APP_CONFIG_CACHE=/tmp/x.php APP_ROUTES_CACHE=/tmp/y.php APP_EVENTS_CACHE=/tmp/z.php VIEW_COMPILED_PATH=/tmp/toco-test-views \
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --testsuite=PartnerApi
```

The hold race test (16 parallel buyers, MariaDB) runs against the isolated `toco_holds_test` database only; see the header of `tests/Concurrency/HoldConcurrencyTest.php`. Afterwards: `chown -R tocoj9771:tocoj9771 storage bootstrap/cache`.
