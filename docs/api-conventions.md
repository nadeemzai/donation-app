# Imdaad Mobile API — conventions (v1)

For the mobile app developer. Every endpoint follows these rules; the endpoint list is in
`docs/srs.md` §7 and the generated docs at `/docs` (Scribe).

## Base and versions

- Base URL: `https://<domain>/api/v1`. JSON only (the API answers JSON even without `Accept`).
- Every response carries `X-API-Version: v1`.
- v1 only grows: new fields and new endpoints may appear, existing fields never change meaning
  or type. A breaking change ships as `/api/v2`, with v1 kept running until the app updates.
- Apps must ignore fields they don't know.

## Envelope

```json
{ "success": true, "data": { }, "message": "", "errors": null, "meta": { } }
```

| Field | Meaning |
| --- | --- |
| `success` | `true` for 2xx, `false` otherwise |
| `data` | object or list; `null` on errors |
| `message` | short text the app may show to the donor |
| `errors` | only on `422`: `{ "field": ["message", …] }` |
| `meta` | extras: `pagination`, `retry_after`… (`{}` when empty) |

## Status codes

| Code | When | App should |
| --- | --- | --- |
| 200 / 201 | OK / created | — |
| 401 | missing or expired token | send the donor to login |
| 403 | not allowed | show `message` |
| 404 | not found (also: someone else's record) | show "not found" |
| 405 | wrong HTTP method | fix the call |
| 422 | validation or a business rule (e.g. "This campaign has closed") | show `errors` per field, else `message` |
| 429 | too many requests | wait `meta.retry_after` seconds |
| 500 | our fault | show a generic error; quote `X-Request-Id` |

## Values

| Kind | Shape | Example |
| --- | --- | --- |
| Money | `{ amount, currency, formatted }` — `amount` is a **string** with 2 decimals | `{ "amount": "5000.00", "currency": "PKR", "formatted": "Rs 5,000.00" }` |
| Enum | `{ value, label }` — branch on `value`, show `label` | `{ "value": "paid", "label": "Paid" }` |
| Time | ISO 8601, Pakistan time | `"2026-12-14T10:30:00+05:00"` |
| Date | `YYYY-MM-DD` | `"2026-12-31"` |
| Rich text | `*_html`, already cleaned (p, strong, em, a, h2–h4, lists, blockquote) | render in a web view |
| Images / links | full URLs | `"https://…/storage/…/cover.jpg"` |

Never parse `formatted` or `label`; they may change wording or language.

## Lists

`?page=2&per_page=20` (max 50). Response `meta.pagination`:
`current_page`, `per_page`, `total`, `last_page`, `has_more`.

## Authentication

Sanctum bearer tokens issued to donors: `Authorization: Bearer <token>`. Tokens are
obtained with a one-time code (Week 7 Day 2). On `401`, drop the token and log in again.

## Language and tracing

- `Accept-Language: ur` → Urdu messages and labels where available (default English).
- Send `X-Request-Id` (8–64 letters, digits, `.`, `_`, `-`); we echo it back, or create one.
  Quote it when reporting a problem.

## Rate limits

| Group | Limit |
| --- | --- |
| All endpoints | 60 / minute per donor (or per IP when logged out) |
| Login / OTP | 5 / minute and 20 / hour per IP |
| Donating / proof upload | 10 / minute |

`429` responses carry a `Retry-After` header and `meta.retry_after`.

## Privacy

The API never returns staff notes, other donors' data, fund balances, full CNIC/NTN, or
who recorded a donation. Asking for someone else's record returns `404`, not `403`.

## Getting the docs and the Postman collection

Generate after any API change: `composer docs:api` (runs `php artisan scribe:generate`).

| What | Where |
| --- | --- |
| HTML docs (try-it-out included) | `https://<domain>/docs` |
| Postman collection | `https://<domain>/docs.postman` — import into Postman |
| OpenAPI 3 spec | `https://<domain>/docs.openapi` — for code generators |

The docs are always open on local and staging. On the live site they are hidden unless
`API_DOCS_PUBLIC=true`.

## Endpoints (v1)

| Group | Endpoint | Token |
| --- | --- | --- |
| App | `GET /health` · `GET /app/config` | — |
| Catalog | `GET /banners` · `GET /funds` · `GET /campaigns` · `GET /campaigns/{slug}` · `GET /campaigns/{slug}/updates` | — |
| Content | `GET /impact-updates` · `GET /pages/{slug}` · `POST /zakat/calculate` | — |
| Auth | `POST /auth/register` · `POST /auth/login` · `POST /auth/otp/send` · `POST /auth/otp/verify` | — |
| Auth | `POST /auth/logout` | ✔ |
| Donate | `POST /donations` | optional |
| Donate | `GET {status_url}` · `POST {proof_url}` | signed link |
| Me | `GET /me` · `PUT /me` · `GET /me/donations` · `GET /me/receipts/{id}/download` · `GET /me/tax-certificates` · `GET /me/tax-certificates/{id}/download` | ✔ |
| Devices | `POST /devices` · `DELETE /devices` | ✔ |

`tests/Feature/Api/ApiContractTest.php` holds this list and the response shapes. It fails if an
endpoint or a field the app relies on disappears.

## Flow: logging in

```
POST /auth/login        { "identifier": "0300-1234567" }
  → 200 { data: { sent_to: "0300•••4567", expires_in: 600 } }
POST /auth/otp/verify   { "identifier": "0300-1234567", "code": "482913", "device_name": "Galaxy A54" }
  → 200 { data: { token, token_type: "Bearer", expires_at, donor } }
POST /devices           { "token": "<push token>", "platform": "android" }      (with the token)
```

New donors call `POST /auth/register` (name, phone…) instead of login; the rest is the same.
Logging out: `DELETE /devices`, then `POST /auth/logout`.

## Flow: donating

```
GET  /app/config                       → which methods are on, limits, bank details
POST /donations                        Idempotency-Key: <new UUID>
     { fund_code | campaign_slug, amount, payment_method, [wallet_number, cnic_last6], [name, phone] }
  → 201 { data: { donation, next_step, payment, checkout, status_url, proof_url, bank } }
```

| `next_step` | What the app does |
| --- | --- |
| `wait` | Show "approve the payment in your wallet app"; poll `status_url` every `poll_after_seconds` |
| `open_checkout` | Open `checkout.url` in a web view (for `form_post`, post `checkout.fields` to it); then poll `status_url` |
| `upload_proof` | Show `bank` details; then `POST proof_url` (multipart: `reference`, `proof` file) |
| `done` | Paid — show thanks; the receipt is in `donation.receipt` |
| `closed` | Failed or cancelled — offer to try again (with a new Idempotency-Key) |

Poll until `is_final` is `true`. Retrying `POST /donations` with the same Idempotency-Key never
charges twice. If the request times out, retry with the same key.
