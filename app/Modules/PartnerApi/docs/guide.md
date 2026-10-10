# Toco Partner Stock API: partner guide

This guide is for developers at Toco partner companies. It explains how to connect to the Toco Partner Stock API and show Toco International's live vehicle stock on your own website.

The full reference is the OpenAPI 3.1 specification ([openapi.json](openapi.json)). This guide explains how the pieces fit together.

## 1. How the integration works

- Your **server** calls the API. Browsers cannot call it: there is no CORS, and your keys must never appear in web pages or apps.
- Keep your own copy of Toco's stock in your database, and build your pages from that copy.
- Sync every 5 to 15 minutes. Sold or removed cars must leave your site within 15 minutes; this is part of your partner contract.
- Every vehicle carries a link back to its page on tocojapan.com, with your attribution code, so inquiries are credited to you.

**Base URL:** `https://tocojapan.com/partner/v1`. This moves to `https://api.tocojapan.com/partner/v1` when the dedicated API host is ready. Toco will give you notice, and both will work during the switch.

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

If a secret leaks, tell Toco at once and it will be revoked. Toco treats a secret found in browser code or a public repository as leaked.

**Rotation.** Toco can rotate a secret on request or on a schedule. The old secret keeps working for **7 days** after rotation, so you can switch without downtime.

## 4. Authentication

Exchange your client ID and secret for an **access token** (OAuth 2.0 client credentials). The token lasts **15 minutes**. Reuse it until it expires, then request a new one. Do not request a token for every call.

```bash
curl -s https://tocojapan.com/partner/v1/oauth/token \
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
curl -s https://tocojapan.com/partner/v1/me \
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
curl -s "https://tocojapan.com/partner/v1/vehicles?updated_since=2026-10-10T04:00:00Z&limit=100" \
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
"photos": [ { "id": "ph_3f9c1a07b2d4e6f80a1c", "url": "https://tocojapan.com/partner/v1/img/eyJ…", "width": 1280 } ]
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

## 11. Limits and usage

| Limit | Default | When you go over |
|---|---|---|
| Requests per minute, per key | 120 | `429 too_many_requests` |
| Requests per day, per partner | 20,000 | `429 daily_limit_reached` |
| Photo downloads per day, per partner | 50,000 | `429 image_limit_reached` |

Your contract may set other numbers; `GET /me` shows yours. Days run on Japan time and reset at midnight there. A `429` carries `Retry-After` (seconds): wait that long. Every API response carries `RateLimit-Limit`, `RateLimit-Remaining` and `RateLimit-Reset` for the minute window, and `X-Daily-Limit` and `X-Daily-Remaining` for the day. Answers of `304 Not Modified` do not count against the daily limit.

`GET /usage` (scope `usage:read`) shows today's requests and photo downloads against your limits, today's errors, your last successful sync, and the last 30 days.

Toco sees the same figures, and is alerted about unusual patterns, such as a sudden jump in volume or a key used to walk through every stock number.

## 12. Conventions

- **Request IDs.** Every response has an `X-Request-Id` header. Quote it when you contact Toco about a problem.
- **Unknown parameters are rejected** with `400 unknown_parameter`, not ignored. A typo in a filter name will not silently return the wrong stock.
- **Request bodies** are limited to 64 KB.
- **Times** are UTC, in ISO 8601 format (`2026-10-10T04:12:09Z`).

## 13. Errors

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
| <a id="error-invalid-client"></a>`invalid_client` | 401 | Wrong client ID or secret, or the key was revoked. |
| <a id="error-invalid-token"></a>`invalid_token` | 401 | Missing, invalid or expired access token. Get a new token. |
| <a id="error-partner-suspended"></a>`partner_suspended` | 403 | Toco has paused your access. Contact Toco. |
| <a id="error-partner-revoked"></a>`partner_revoked` | 403 | Your partner account is closed. |
| <a id="error-contract-not-started"></a>`contract_not_started` | 403 | Your contract start date has not arrived yet. |
| <a id="error-contract-ended"></a>`contract_ended` | 403 | Your contract end date has passed. |
| <a id="error-ip-not-allowed"></a>`ip_not_allowed` | 403 | Your account only accepts calls from listed server IP addresses, and this one is not listed. |
| <a id="error-insufficient-scope"></a>`insufficient_scope` | 403 | The token lacks the scope this endpoint needs (`required_scope`). |
| <a id="error-not-found"></a>`not_found` | 404 | No such endpoint or record, or it is outside your stock scope. |
| <a id="error-method-not-allowed"></a>`method_not_allowed` | 405 | Wrong HTTP method for this endpoint. |
| <a id="error-body-too-large"></a>`body_too_large` | 413 | The request body is over 64 KB. |
| <a id="error-validation-failed"></a>`validation_failed` | 422 | Invalid values. `errors` lists the problems per field. |
| <a id="error-unknown-port"></a>`unknown_port` | 422 | The port id is not an active destination. Use `GET /catalog/ports`. |
| <a id="error-cif-unavailable"></a>`cif_unavailable` | 422 | No CIF estimate for this vehicle: its price is on request or its shipping volume is not known yet. |
| <a id="error-too-many-requests"></a>`too_many_requests` | 429 | Slow down. Retry after `Retry-After` seconds. |
| <a id="error-daily-limit-reached"></a>`daily_limit_reached` | 429 | Your daily request limit is used up. It resets at midnight Japan time (`Retry-After`). |
| <a id="error-image-limit-reached"></a>`image_limit_reached` | 429 | Your daily photo allowance is used up. It resets at midnight Japan time. |
| <a id="error-server-error"></a>`server_error` | 500 | Our fault. Retry later, and send us the `request_id` if it continues. |

## 14. Changelog

| Date | Change |
|---|---|
| 2026-10-10 | Photos: `GET /img/{token}`. Limits and `GET /usage`; rate-limit headers on every response. |
| 2026-10-10 | Stock: `GET /vehicles` (full and change sync, tombstones, ETags), `GET /vehicles/{stock_no}`, catalogue lists, `POST /cif`. |
| 2026-10-10 | Draft: authentication, `GET /me`, conventions and errors. |
