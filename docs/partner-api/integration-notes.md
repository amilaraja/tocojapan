# Partner Stock API: integration notes

Spec: /home/tocojapan.com/docs/partner-api/ (SRS-TOC-PAPI docx, Amendment 01 partner types, TOC-PAPI-UI-01 rev 2 Filament brief, TOC-PAPI-DEV-01 build plan).
Module: `app/Modules/PartnerApi`. Tests: `tests/PartnerApi` (testsuite PartnerApi). Partner docs: `app/Modules/PartnerApi/docs/` (guide.md, openapi.json), updated with every endpoint change.

## 1. Switches

| Setting | Default | Effect |
|---|---|---|
| `PARTNER_API_ENABLED` | false | While false every `/partner/v1/*` route answers 404 problem+json. |
| `PARTNER_API_HOST` | unset | When set (api.tocojapan.com), routes bind to that host only. Rebuild the route cache after changing it. |

## 2. Decisions

- Own OAuth client-credentials endpoint, no Passport. Secrets are Argon2id; access tokens are opaque, 15 minutes, stored as SHA-256. The token is looked up on every call, with no cache, so suspend and revoke act on the next request (stricter than SRS §8.7's 60 s).
- Spec format is JSON (`openapi.json`): symfony/yaml is not installed and adding packages to the live docroot was avoided.
- Error codes are stable strings in a `code` member, next to RFC 9457 fields. OAuth errors also carry `error`.
- Partner responses carry `X-LiteSpeed-Cache-Control: no-cache` and `Cache-Control: no-store, private`, so LiteSpeed's page cache never stores a partner response.
- Contract dates are compared in Asia/Tokyo (`partner_api.timezone`).
- `GET /me` is added beyond the SRS so partners can check their keys while integrating.

## 3. Shared-code change register

Every edit outside `app/Modules/PartnerApi` is listed here.

| Date | File | Change | Why |
|---|---|---|---|
| 2026-10-10 | bootstrap/providers.php | Registers PartnerApiServiceProvider | Module registration |
| 2026-10-10 | phpunit.xml, tests/Pest.php | PartnerApi testsuite; Pest TestCase + RefreshDatabase for tests/PartnerApi | Tests |
| 2026-10-10 | app/Http/Middleware/HandleNotFound.php | Skips `partner/*` paths: no managed redirect, no 404 log | Partner 404s are deliberate (out of scope, dead image links) and must stay problem+json |

## 4. Progress

| Phase | Status | Notes |
|---|---|---|
| P1 Foundation | Done 2026-10-10 | Tables partners, partner_clients, partner_access_tokens, partner_audit_logs (migrated on live, empty). Token endpoint, `GET /me`, problem+json, request IDs, 64 KB cap, unknown-parameter rejection, IP allow-list, audit log. 30 tests. |
| P2 Holds + own channels | Next | MIS task #204 |

Known unrelated failures in the full suite (they fail without this module too): Auth AuthenticationTest, PasswordResetTest x3, RegistrationTest, CmsTest sitemap.
