# API contract

Savv MVP has no public JSON API for third parties. There are two internal
contracts: Laravel's own web routes (session/CSRF authenticated, used by the
Blade UI and its `fetch()` polling), and the Laravel-to-runner internal API
(HMAC-signed, loopback-only).

## Laravel web routes

All routes below require an authenticated session (redirect to `/login`
otherwise) and standard CSRF protection on state-changing requests.
Authorization is enforced per-resource via policies
(`Savv\Policies\*`) - a user can only see/act on their own orders, returns,
refunds, and import sessions.

| Method | Path | Purpose |
|---|---|---|
| GET | `/connections` | List provider connection status; start a new import. |
| POST | `/connections/{provider}/start` | Record consent, start an isolated-browser import session. Rate limited (`throttle:import-session-start`). |
| GET | `/imports/{publicId}` | Session status page (Blade); JSON when `Accept: application/json` (used for polling). |
| GET | `/imports/{publicId}/browser` | The embedded viewer page (noVNC iframe, or a "look at your screen" message locally). |
| POST | `/imports/{publicId}/scan` | Trigger a scan (dispatches `ScanImportSession`). |
| GET | `/imports/{publicId}/preview` | JSON list of unconfirmed `ImportPreview` rows for selection. |
| POST | `/imports/{publicId}/confirm` | Confirm selected previews (dispatches `ConfirmImportSession`). Body: `selected[]` = preview `public_id`s. |
| POST | `/imports/{publicId}/cancel` | Cancel and terminate the session immediately. |
| GET | `/dashboard` | Summary cards + recent orders. |
| GET | `/orders`, `/orders/{publicId}` | List/detail, with filter/search/sort query params (see `OrderController`). |
| DELETE | `/orders/{publicId}` | Soft-delete an order. |
| GET | `/returns`, `/refunds` | List views. |
| GET | `/settings/privacy` | Consent history, connections, export/delete actions. |
| GET | `/account/export` | JSON download of the user's own data. |
| DELETE | `/account` | Permanently delete the account (requires current password). |

## Internal runner API

Base URL: `RUNNER_BASE_URL` (default `http://127.0.0.1:3010`), bound to
`127.0.0.1` only - the runner refuses to start on any other host.

Every request must carry:

| Header | Meaning |
|---|---|
| `X-Savv-Timestamp` | Unix seconds. Rejected if more than `RUNNER_MAX_TIMESTAMP_SKEW_SECONDS` (default 300) away from server time. |
| `X-Savv-Nonce` | A UUID, unique per request. Rejected if reused. |
| `X-Savv-Body-Hash` | `sha256(rawRequestBody)`, `sha256('')` for bodyless `GET` requests. |
| `X-Savv-Signature` | `hmac_sha256(secret, "METHOD\npath\ntimestamp\nnonce\nbodyHash")`, hex-encoded. |

`secret` is `RUNNER_SHARED_SECRET`, identical in `backend/.env` and
`runner/.env`. See `Savv\Support\HmacSigner` (signer) and
`runner/src/security/verifySignature.js` (verifier) - the two must stay in
lock-step on the string-to-sign format.

| Method | Path | Body | Response |
|---|---|---|---|
| POST | `/internal/sessions` | `{sessionId, provider}` | `{processRef, displayRef, status}` |
| GET | `/internal/sessions/{id}` | - | `{status}` |
| POST | `/internal/sessions/{id}/scan` | `{}` | `{orders: [...], parserVersion}` or `{error, message, orders: []}` |
| POST | `/internal/sessions/{id}/view-token` | `{token, ttlSeconds}` | `{registered: bool}` - registers a view token with the shared websockify broker (Ubuntu deployment only; a no-op `{registered: false}` in headed-local mode). |
| POST | `/internal/sessions/{id}/stop` | `{}` | `{status: "terminated"}` |
| GET | `/internal/sessions/{id}/health` | - | `{ok, status, uptimeMs, idleMs}` |

`sessionId` must match `^[A-Za-z0-9_-]{10,64}$` (a ULID) and `provider` must
be `amazon_us` or `flipkart` - anything else is `400`. An unknown session ID
returns `404` (`scan`) or `{status: "not_found"}` (`GET` endpoints); `stop`
on an unknown/already-stopped session is a no-op `200` (idempotent).

### Order JSON shape (`scan` response `orders[]`)

Every field is optional except `provider_order_id`, `observed_at`, and
`items` (must be a non-empty array once validated - see
`Savv\Services\ImportPreviewValidator`, which rejects unknown top-level keys
and any key containing a credential/session-shaped substring):

```json
{
  "provider_order_id": "AMZ-SYNTH-0001",
  "order_date": "2026-03-12",
  "original_status": "Delivered",
  "currency": "INR",
  "subtotal": "1499.00",
  "delivery_fee": "0.00",
  "discount": "0.00",
  "tax": "0.00",
  "total": "1499.00",
  "expected_delivery_at": null,
  "delivered_at": "2026-03-15",
  "official_order_url": "https://www.amazon.com/gp/css/order-details?orderID=AMZ-SYNTH-0001",
  "observed_at": "2026-03-15T10:00:00Z",
  "items": [{
    "provider_item_id": null,
    "title": "Synthetic Wireless Mouse",
    "variant": null,
    "quantity": 1,
    "unit_price": "1499.00",
    "line_total": "1499.00",
    "product_image_url": "https://www.amazon.com/images/synthetic-mouse.jpg",
    "official_product_url": "https://www.amazon.com/dp/SYNTH0001"
  }],
  "shipments": [], "returns": [], "refunds": [], "invoices": []
}
```

Amounts are plain decimal strings on the wire; Laravel converts them to
integer minor units (`Savv\Support\Money`) - never floats, at either end.
