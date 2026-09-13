# Privacy model

## What Savv MVP collects

Only what a provider's order-history/order-detail page visibly renders,
limited to the fields listed in [architecture.md](architecture.md): order
identifiers, dates, statuses, amounts, item titles/quantities/prices,
shipment/return/refund timeline fields, and official marketplace links.
Never addresses, phone numbers, payment details, marketplace messages,
wishlists, or browsing history.

## What Savv MVP never collects

See [runner-security.md](runner-security.md)'s "Never collect or store"
list - passwords, OTPs, cookies, session IDs, tokens, browser profiles, and
complete page HTML are excluded at the runner, and independently rejected
again by Laravel if anything matching those shapes is ever submitted
(`Savv\Support\ForbiddenFieldGuard`).

## Consent

Consent is per-provider and versioned (`consents.consent_version`). A user
must accept the consent notice each time they start a new import session for
a provider (see `ConnectionsController::start`). The notice text is fixed
and shown in [connections/index.blade.php](../backend/resources/views/connections/index.blade.php).

## Retention

- `users.retention_days` (falls back to `SAVV_DEFAULT_RETENTION_DAYS`,
  default 730 days) is the intended retention horizon for a user's order
  data; enforcing a hard deletion job past that horizon is not yet built
  (see [limitations.md](limitations.md)).
- `import_previews` are always short-lived: deleted on cancel, expiry,
  successful confirmation, or by the `imports:cleanup` scheduled command.
- `import_sessions` never contain the data listed above as "never collects"
  in the first place, so there's nothing sensitive to retain or purge beyond
  the lifecycle bookkeeping columns themselves.

## User controls

- **Delete an individual order**: `DELETE /orders/{publicId}` (soft delete).
- **Export account data**: `GET /account/export` - a JSON download of the
  user's own orders/items/shipments/returns/refunds/invoices/consents.
- **Delete account**: `DELETE /account` (password-confirmed) - cascades to
  all of the user's orders, consents, provider connections, and import
  sessions via foreign key `ON DELETE CASCADE`.
- **Cancel an active import**: terminates the isolated browser immediately
  and discards its unconfirmed preview.

## Audit logs

`audit_logs` records identifiers, action types, IP address, and small safe
metadata (counts, not content) - never product titles, order numbers,
tracking numbers, or addresses. See `Savv\Services\AuditLogger`. Application
logs (`storage/logs/laravel.log`) must never receive personal order data;
the `audit` log channel (`storage/logs/audit.log`) is separate and equally
redacted.

## Disclosure shown to users

Every dashboard view carries:

> Order information reflects the most recent user-authorized import. Verify
> purchases, deliveries, returns, and refunds on the official Amazon or
> Flipkart website.

And every import session start requires accepting:

> Savv will open a temporary remote browser for this import. You will log
> into the marketplace directly in that browser. Savv does not intentionally
> store your marketplace password or OTP. The temporary browser and its
> profile will be deleted when the import ends. This demonstration is not an
> official Amazon or Flipkart integration.
