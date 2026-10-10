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

## 5. Conventions

- **Request IDs.** Every response has an `X-Request-Id` header. Quote it when you contact Toco about a problem.
- **Unknown parameters are rejected** with `400 unknown_parameter`, not ignored. A typo in a filter name will not silently return the wrong stock.
- **Request bodies** are limited to 64 KB.
- **Times** are UTC, in ISO 8601 format (`2026-10-10T04:12:09Z`).

## 6. Errors

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
| <a id="error-too-many-requests"></a>`too_many_requests` | 429 | Slow down. Retry after `Retry-After` seconds. |
| <a id="error-server-error"></a>`server_error` | 500 | Our fault. Retry later, and send us the `request_id` if it continues. |

## 7. Changelog

| Date | Change |
|---|---|
| 2026-10-10 | Draft: authentication, `GET /me`, conventions and errors. |
