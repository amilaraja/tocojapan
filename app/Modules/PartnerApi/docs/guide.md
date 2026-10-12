# Toco Partner Stock API: partner guide

This guide is for developers at Toco partner companies. It explains how to connect to the Toco Partner Stock API and show Toco International's live vehicle stock on your own website.

The full reference is the OpenAPI 3.1 specification ([openapi.json](openapi.json)). This guide explains how the pieces fit together.

## 1. How the integration works

- Your **server** calls the API. Browsers cannot call it: there is no CORS, and your keys must never appear in web pages or apps.
- Keep your own copy of Toco's stock in your database, and build your pages from that copy.
- Sync every 5 to 15 minutes. Sold or removed cars must leave your site within 15 minutes; this is part of your partner contract.
- Every vehicle carries a link back to its page on tocojapan.com, with your attribution code, so inquiries are credited to you.

**Base URL:** `https://api.tocojapan.com/partner/v1`. This guide stays at `https://tocojapan.com/partner/docs`.

All requests and responses use JSON over HTTPS (TLS 1.2 or later).

## 2. Partner types

| Type | What it does | Holds |
|---|---|---|
| Listing partner | Shows Toco stock and sends buyers to Toco. All sales and payments go through Toco. | Never. The API refuses hold requests. |
| Selling partner | May take a buyer's payment. It must first win a hold on the car. | Required before taking any money. |

Toco sets your type in your contract. `GET /me` shows it.

## 3. Keys

Toco gives you one or more sets of **connection keys**, for example one for production and one for testing. Each set has:

- a **client ID**, such as `tcc_4f1c2a9e0b7d3e5f6a8c`. It is not secret.
- a **client secret**, such as `tcp_live_…` (live) or `tcp_test_…` (testing). It is shown **once**, when Toco creates it. Store it in your server's secret store.

**Testing and live keys.** There is no separate sandbox. Both kinds of key read the same live stock, prices, photos and CIF estimates, through the same base URL.

| | Testing key | Live key |
|---|---|---|
| Secret starts with | `tcp_test_` | `tcp_live_` |
| Stock, prices, photos, CIF | Live data | Live data |
| Holds (selling partners) | Refused with `403 holds_not_available_for_test_keys`, because a hold reserves a real car | Allowed |
| Daily limits | Shared with your live keys | Shared with your testing keys |
| `GET /me` → `client.environment` | `test` | `live` |

Use a testing key while you build, or give it to a contractor. It can be revoked on its own without touching production. Do not publish a live site on a testing key: when you launch, ask Toco for a live key and Toco will revoke the testing key. Selling partners can build and test their whole integration on a testing key without reserving a real car by accident.

If a secret leaks, tell Toco at once and it will be revoked. Toco treats a secret found in browser code or a public repository as leaked.

**Rotation.** Toco can rotate a secret on request or on a schedule. The old secret keeps working for **7 days** after rotation, so you can switch without downtime.

## 4. Authentication

Exchange your client ID and secret for an **access token** (OAuth 2.0 client credentials). The token lasts **15 minutes**. Reuse it until it expires, then request a new one. Do not request a token for every call.

```bash
curl -s https://api.tocojapan.com/partner/v1/oauth/token \
  -u "$TOCO_CLIENT_ID:$TOCO_CLIENT_SECRET" \
  -d grant_type=client_credentials
```

```json
{
  "access_token": "tcat_Jx0…",
  "token_type": "Bearer",
  "expires_in": 900,
  "scope": "stock:read cif:read usage:read"
}
```

Send the token on every other call:

```bash
curl -s https://api.tocojapan.com/partner/v1/me \
  -H "Authorization: Bearer $TOKEN"
```

You can also send `client_id` and `client_secret` as form fields instead of HTTP Basic. Most OAuth client libraries handle all of this for you: give them the token URL, client ID and secret.

**Scopes.** A token gets every scope your partner account allows, unless you ask for fewer with `scope=stock:read`.

| Scope | Allows |
|---|---|
| `stock:read` | Vehicles and reference lists |
| `cif:read` | CIF estimates |
| `usage:read` | Your own usage figures |
| `stock:hold` | Placing and releasing holds (selling partners only) |

**Check your connection.** `GET /me` returns your partner name, type, attribution code, the key in use, the token's scopes and expiry, and your contracted limits.

**Too many failed token requests** from one IP address are refused for a minute (`429`, with `Retry-After`).

## 5. Syncing stock

Keep a copy of Toco's stock in your own database and update it on a schedule. There are two kinds of sync.

**Full sync.** `GET /vehicles` without `updated_since` returns every vehicle in your scope that is available or under offer. Page through it with `next_cursor` until it is `null`. Then delete from your copy anything the full sync did not return. Run a full sync:

- the first time,
- once a day as a safety net,
- whenever `meta.scope_version` changes. Toco changed what you see (your stock scope, prices or photo settings).

**Change sync.** `GET /vehicles?updated_since=<time>` returns only vehicles that changed after that time, oldest change first. Run it every 5 to 15 minutes. Use the time you *started* your previous sync, minus a minute, so nothing slips between runs. A change sync can include:

- vehicles with status `available`, `reserved` or `sold`: update your copy;
- **tombstones**, `{"stock_no": "E02060", "status": "removed", "updated_at": "…"}`. The vehicle was withdrawn, deleted or left your scope: remove it from your site.

`updated_since` can go back at most 30 days. Further back, you get `400 sync_window_exceeded`: do a full sync instead.

```bash
curl -s "https://api.tocojapan.com/partner/v1/vehicles?updated_since=2026-10-10T04:00:00Z&limit=100" \
  -H "Authorization: Bearer $TOKEN"
```

```json
{
  "data": [ { "stock_no": "E02060", "status": "reserved", "…": "…" },
            { "stock_no": "E01987", "status": "removed", "updated_at": "2026-10-10T05:12:44Z" } ],
  "meta": { "limit": 100, "next_cursor": null, "scope_version": 3, "sync": "changes" }
}
```

**Paging.** Pages are at most 100 vehicles (`limit`, default 50). `next_cursor` is opaque: pass it back exactly as `cursor`, with the same filters. Cursors stay correct while stock changes underneath them.

**Filters** on `GET /vehicles`: `make`, `model`, `body_type` (slugs from the catalogue), `year_min`, `year_max`, `price_min`, `price_max` (in *your* prices) and `status` (`available` or `reserved`).

**Save your quota with ETags.** Every list and vehicle response has an `ETag` header. Send it back as `If-None-Match`; if nothing changed you get an empty `304`, which does not count against your daily limit.

## 6. Vehicle status and your obligations

| Status | Meaning | What your site must do |
|---|---|---|
| `available` | On sale. | Show it. |
| `reserved` | Under offer: another buyer is paying or negotiating. | Show it as **"Under offer"**, with the same wording. Take no money and promise it to nobody. If the reservation lapses it becomes `available` again. |
| `sold` | Sold. | Remove it, or mark it sold, **within 15 minutes**. Sold vehicles stay readable for 30 days, then return 404. |
| `removed` | Withdrawn or out of your scope (tombstone). | Remove it within 15 minutes. |

These timings are part of your partner contract. A listing partner never takes payment: every buyer goes to Toco through `detail_url`.

## 7. Vehicle fields

`GET /vehicles/{stock_no}` returns one vehicle. Out of scope and unknown look the same: `404`.

Main fields: `stock_no`, `title`, `status`, `make`, `model`, `body_type` (each `{slug, name}`), `year`, `registration` and `manufacture` (`{year, month}`), `mileage_km`, `engine_cc`, `fuel`, `transmission`, `drive`, `steering`, `exterior_color`, `doors`, `seats`, `grade`, `m3` (shipping volume), `price`, `photos`, `image_mode_applied`, `detail_url` and `updated_at`. Partners on the extended field set also get `chassis_number`, `model_code`, `dimensions_cm`, `interior_color`, `features` and `description`. The OpenAPI file lists every field and its type.

**Link buyers to `detail_url`.** It opens the vehicle on tocojapan.com with your attribution code (`?ref=P-…`), so inquiries and sales are credited to you.

## 8. Prices

`price` is always FOB (free on board, Japan), already adjusted to your contract:

```json
"price": { "amount": 18450, "currency": "USD", "basis": "FOB", "on_request": false, "reason": null }
```

When there is no price to show, `amount` is `null`, `on_request` is `true` and `reason` says why: `price_on_request` (Toco has no public price for this car) or `partner_price_on_request` (your contract shows all prices on request). Show "Price on request" and link to `detail_url`.

## 9. Photos

Each photo has a stable `id` and a signed `url`:

```json
"photos": [ { "id": "ph_3f9c1a07b2d4e6f80a1c", "url": "https://api.tocojapan.com/partner/v1/img/eyJ…", "width": 1280 } ]
```

- Photos carry the Toco watermark (`image_mode_applied: "watermarked"`).
- **Download each photo once and serve it from your own storage** (the default, "re-host" mode). If you already have a photo `id`, you already have the file: skip it. The `url` changes over time; the `id` does not.
- Signed links expire after 1 hour, so never put them in your pages. Partners Toco has set to "Toco-hosted" may embed them; those links last 24 hours and must be refreshed on every sync.
- When a vehicle is sold or removed, delete its photos too.
- Download with `GET` on the `url` as given, with no `Authorization` header: the link itself is the credential. Photos are WebP, at most your contracted width.
- A link that is expired, altered, or belongs to a vehicle that left your scope answers `404` with no detail. A new sync gives you fresh links.
- Each successful download counts against your daily photo allowance. Downloading each photo once keeps you far below it.

## 10. Catalogue and CIF estimates

Reference lists, each with the number of vehicles in your scope where it applies:

| Endpoint | Returns |
|---|---|
| `GET /catalog/makes` | `slug`, `name`, `vehicle_count` |
| `GET /catalog/models?make=toyota` | `slug`, `name`, `make`, `vehicle_count` |
| `GET /catalog/body-types` | `slug`, `name`, `vehicle_count` |
| `GET /catalog/countries` | `code` (ISO 3166-1 alpha-2), `name` |
| `GET /catalog/ports?country=KE` | `id`, `name`, `country`, `unlocode` |

`POST /cif` with `{"stock_no": "E02060", "port_id": 12}` estimates cost, insurance and freight to that port, on your price. It needs the `cif:read` scope. It is an estimate: inspection fees and import duties are not included.

## 11. Change notifications (webhooks)

Instead of waiting for your next scheduled sync, you can get a message the moment something changes. Give Toco an **https** address; Toco gives you a **notification secret** (`whsec_…`) to check the messages.

Each message is a `POST` with a small JSON body:

```json
{ "id": "whd_01k7…", "type": "vehicle.sold", "created_at": "2026-10-10T05:12:44Z",
  "data": { "stock_no": "E02060", "status": "sold", "updated_at": "2026-10-10T05:12:44Z" } }
```

| Type | Meaning | What to do |
|---|---|---|
| `vehicle.created` | A car entered your scope. | Fetch it with `GET /vehicles/{stock_no}`. |
| `vehicle.updated` | Details, price or photos changed. | Fetch it again. |
| `vehicle.status_changed` | Now `available` or `reserved`. | Update the status ("Under offer" for reserved). |
| `vehicle.sold` | Sold. | Remove it or mark it sold within 15 minutes. |
| `vehicle.removed` | Withdrawn or left your scope. | Remove it within 15 minutes. |
| `hold.expiring` | One of your holds ends within 6 hours. | Finish the sale through Toco, or release it. |
| `hold.expired` / `hold.released` | Your hold ended (time ran out, or Toco released it). | Take no payment for that car. |
| `ping` | A test message from Toco. | Nothing. |

Messages carry no vehicle details: always fetch the vehicle through the API. Keep your scheduled sync running anyway as a safety net.

**Answer quickly.** Reply with any `2xx` status within 10 seconds, then do the work. Failed messages are retried with growing gaps (1, 2, 5, 10, 30 minutes, then hourly and longer) for 24 hours. A message can arrive twice: ignore an `id` you have already handled.

**Check every message.** Each one has these headers:

| Header | Value |
|---|---|
| `X-Toco-Event` | The event type |
| `X-Toco-Delivery` | The message `id` |
| `X-Toco-Timestamp` | Unix time it was signed |
| `X-Toco-Signature` | `t=<timestamp>,v1=<signature>` |

The signature is HMAC-SHA256 of `<timestamp>.<raw body>` with your notification secret, in hex. Reject the message if the signature does not match or the timestamp is more than 5 minutes old.

```php
// PHP
$body = file_get_contents('php://input');
preg_match('/t=(\d+),v1=([0-9a-f]+)/', $_SERVER['HTTP_X_TOCO_SIGNATURE'] ?? '', $m);
$expected = hash_hmac('sha256', $m[1].'.'.$body, getenv('TOCO_WEBHOOK_SECRET'));
if (! $m || ! hash_equals($expected, $m[2]) || abs(time() - (int) $m[1]) > 300) {
    http_response_code(400); exit;
}
http_response_code(204);
// … then handle json_decode($body, true)
```

```js
// Node.js (Express: use express.raw({ type: 'application/json' }) for this route)
const crypto = require('crypto');
function verify(req) {
  const m = /t=(\d+),v1=([0-9a-f]+)/.exec(req.get('X-Toco-Signature') || '');
  if (!m || Math.abs(Date.now() / 1000 - Number(m[1])) > 300) return false;
  const expected = crypto.createHmac('sha256', process.env.TOCO_WEBHOOK_SECRET)
    .update(m[1] + '.' + req.body).digest('hex');
  if (expected.length !== m[2].length) return false;
  return crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(m[2]));
}
```

## 12. Holds (selling partners only)

A **selling partner** takes a buyer's money itself, so it must first reserve the car in Toco's system. **Never take payment, or sign a sale, before you receive `201` for a hold.** A sale made without a hold, or after it ended, is at your own risk. Listing partners never hold cars: they get `403 holds_not_available_for_partner`.

```bash
curl -s https://api.tocojapan.com/partner/v1/vehicles/E02060/holds \
  -H "Authorization: Bearer $TOKEN" \
  -H "Idempotency-Key: order-7781" \
  -H "Content-Type: application/json" \
  -d '{"buyer_ref": "order-7781", "destination_country": "KE"}'
```

```json
{ "data": { "hold_id": "hld_01k7…", "stock_no": "E02060", "state": "active", "buyer_ref": "order-7781",
            "destination_country": "KE", "price": { "amount": 18450, "currency": "USD", "basis": "FOB" },
            "created_at": "2026-10-10T05:00:00Z", "expires_at": "2026-10-12T05:00:00Z", "ended_at": null } }
```

- **First request wins.** Everyone else gets `409 vehicle_unavailable`, and the car shows as `reserved` everywhere at once.
- **Retry safely.** Send an `Idempotency-Key` (your order number works). Repeating the request returns the same hold, not a second one. Asking again for a car you already hold returns your hold with `200`.
- **The price is locked** for the life of the hold, at your price when you placed it.
- **Holds end on their own** after your contracted hold length (shown in `GET /me`). You get `hold.expiring` 6 hours before and `hold.expired` when it ends. Once Toco has your buyer's payment, `expires_at` becomes `null` while Toco confirms the sale.
- **Release early** with `DELETE /holds/{hold_id}` when your buyer walks away, so the car goes back on sale.
- **Only Toco marks a car sold**, after the payment reaches Toco. You then see `vehicle.sold`, and your hold's state becomes `converted`.
- `GET /holds` lists your active holds and those from the last 30 days.
- You can hold only as many cars at once as your contract allows (`hold_limit_reached`).
- Test keys (`tcp_test_…`) cannot place holds, because a hold reserves a real car.

## 13. Limits and usage

| Limit | Default | When you go over |
|---|---|---|
| Requests per minute, per key | 120 | `429 too_many_requests` |
| Requests per day, per partner | 20,000 | `429 daily_limit_reached` |
| Photo downloads per day, per partner | 50,000 | `429 image_limit_reached` |

Your contract may set other numbers; `GET /me` shows yours. Days run on Japan time and reset at midnight there. A `429` carries `Retry-After` (seconds): wait that long. Every API response carries `RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset` for the minute window, and `X-Daily-Limit` and `X-Daily-Remaining` for the day. Answers of `304 Not Modified` do not count against the daily limit.

`GET /usage` (scope `usage:read`) shows today's requests and photo downloads against your limits, today's errors, your last successful sync, and the last 30 days.

Toco sees the same figures, and is alerted about unusual patterns, such as a sudden jump in volume or a key used to walk through every stock number.

## 14. Conventions

- **Request IDs.** Every response has an `X-Request-Id` header. Quote it when you contact Toco about a problem.
- **Unknown parameters are rejected** with `400 unknown_parameter`, not ignored. A typo in a filter name will not silently return the wrong stock.
- **Request bodies** are limited to 64 KB.
- **Times** are UTC, in ISO 8601 format (`2026-10-10T04:12:09Z`).

## 15. Errors

Errors use [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) problem details, with content type `application/problem+json`:

```json
{
  "type": "https://tocojapan.com/partner/docs#error-partner-suspended",
  "title": "This partner account is suspended.",
  "status": 403,
  "code": "partner_suspended",
  "detail": "Contact Toco to resolve this.",
  "request_id": "req_01k7a3m9x2d5q8r4t6v0w1y3z5"
}
```

Branch on `code`. `title` and `detail` are for people and may change. Errors from `POST /oauth/token` also carry the standard OAuth `error` member, with the same value as `code`.

| Code | Status | Meaning and what to do |
|---|---|---|
| <a id="error-invalid-request"></a>`invalid_request` | 400 | Token request without client credentials. |
| <a id="error-unsupported-grant-type"></a>`unsupported_grant_type` | 400 | Only `grant_type=client_credentials` is supported. |
| <a id="error-invalid-scope"></a>`invalid_scope` | 400 | You asked for a scope your account does not have. |
| <a id="error-unknown-parameter"></a>`unknown_parameter` | 400 | The request has a parameter this endpoint does not accept. `unknown` lists them. |
| <a id="error-invalid-cursor"></a>`invalid_cursor` | 400 | The cursor was changed or truncated. Pass `next_cursor` back exactly, or start again without it. |
| <a id="error-sync-window-exceeded"></a>`sync_window_exceeded` | 400 | `updated_since` is more than 30 days back. Do a full sync. |
| <a id="error-price-filter-unavailable"></a>`price_filter_unavailable` | 400 | Your prices are on request, so price filters cannot apply. |
| <a id="error-invalid-idempotency-key"></a>`invalid_idempotency_key` | 400 | `Idempotency-Key` must be 1 to 120 characters. |
| <a id="error-invalid-client"></a>`invalid_client` | 401 | Wrong client ID or secret, or the key was revoked. |
| <a id="error-invalid-token"></a>`invalid_token` | 401 | Missing, invalid or expired access token. Get a new token. |
| <a id="error-partner-suspended"></a>`partner_suspended` | 403 | Toco has paused your access. Contact Toco. |
| <a id="error-partner-revoked"></a>`partner_revoked` | 403 | Your partner account is closed. |
| <a id="error-contract-not-started"></a>`contract_not_started` | 403 | Your contract start date has not arrived yet. |
| <a id="error-contract-ended"></a>`contract_ended` | 403 | Your contract end date has passed. |
| <a id="error-ip-not-allowed"></a>`ip_not_allowed` | 403 | Your account only accepts calls from listed server IP addresses, and this one is not listed. |
| <a id="error-insufficient-scope"></a>`insufficient_scope` | 403 | The token lacks the scope this endpoint needs (`required_scope`). |
| <a id="error-holds-not-available-for-partner"></a>`holds_not_available_for_partner` | 403 | Your account is a listing partner. Send buyers to Toco through `detail_url`. |
| <a id="error-holds-not-available-for-test-keys"></a>`holds_not_available_for_test_keys` | 403 | A hold reserves a real car. Use a live key. |
| <a id="error-not-found"></a>`not_found` | 404 | No such endpoint or record, or it is outside your stock scope. |
| <a id="error-method-not-allowed"></a>`method_not_allowed` | 405 | Wrong HTTP method for this endpoint. |
| <a id="error-vehicle-unavailable"></a>`vehicle_unavailable` | 409 | Someone else holds the car, or it was sold. **Take no payment.** |
| <a id="error-hold-limit-reached"></a>`hold_limit_reached` | 409 | You hold as many cars as your contract allows. Release one you no longer need. |
| <a id="error-hold-not-active"></a>`hold_not_active` | 409 | The hold already ended. |
| <a id="error-body-too-large"></a>`body_too_large` | 413 | The request body is over 64 KB. |
| <a id="error-validation-failed"></a>`validation_failed` | 422 | Invalid values. `errors` lists the problems per field. |
| <a id="error-unknown-port"></a>`unknown_port` | 422 | The port id is not an active destination. Use `GET /catalog/ports`. |
| <a id="error-cif-unavailable"></a>`cif_unavailable` | 422 | No CIF estimate for this vehicle: its price is on request or its shipping volume is not known yet. |
| <a id="error-too-many-requests"></a>`too_many_requests` | 429 | Slow down. Retry after `Retry-After` seconds. |
| <a id="error-daily-limit-reached"></a>`daily_limit_reached` | 429 | Your daily request limit is used up. It resets at midnight Japan time (`Retry-After`). |
| <a id="error-image-limit-reached"></a>`image_limit_reached` | 429 | Your daily photo allowance is used up. It resets at midnight Japan time. |
| <a id="error-server-error"></a>`server_error` | 500 | Our fault. Retry later, and send us the `request_id` if it continues. |

## 16. Changelog

| Date | Change |
|---|---|
| 2026-10-12 | Guide: how testing keys differ from live keys (section 3). No API change. |
| 2026-10-10 | API moved to its own host, `https://api.tocojapan.com/partner/v1`. |
| 2026-10-10 | Change notifications (signed webhooks) and holds for selling partners (`POST /vehicles/{stock_no}/holds`, `GET /holds`, `DELETE /holds/{hold_id}`). |
| 2026-10-10 | Photos: `GET /img/{token}`. Limits and `GET /usage`; rate-limit headers on every response. |
| 2026-10-10 | Stock: `GET /vehicles` (full and change sync, tombstones, ETags), `GET /vehicles/{stock_no}`, catalogue lists, `POST /cif`. |
| 2026-10-10 | Draft: authentication, `GET /me`, conventions and errors. |
