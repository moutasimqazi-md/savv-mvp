# Database

Savv MVP uses **MariaDB Community Server 10.11.19 exactly** - no other
engine, no Docker, no Redis. All tables use InnoDB, `utf8mb4` /
`utf8mb4_unicode_ci`, and UTC timestamps. Public-facing identifiers are
ULIDs (`public_id` columns); internal numeric auto-increment IDs are used
for foreign keys and joins.

See [admin-database-setup.md](admin-database-setup.md) for the SQL an
administrator runs manually to create the database and user - it is never
run automatically, and the real password is never committed.

## Tables

| Table | Purpose |
|---|---|
| `users` | Accounts. Argon2id password hashes. |
| `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` | Laravel's own database-backed auth/session/cache/queue infrastructure. |
| `consents` | Per-provider, versioned consent acceptance record. |
| `provider_connections` | A user's enablement of Amazon India or Flipkart. |
| `import_sessions` | One temporary isolated-browser import attempt. Never stores cookies, passwords, OTPs, browser-profile contents, auth headers, or complete HTML - see the column list below. |
| `import_previews` | Unconfirmed, normalized scan results shown to the user before import. Deleted on cancel/expiry/completion/cleanup. |
| `import_batches` | The permanent record of one completed import (from a runner scan, manual entry, or CSV). |
| `orders`, `order_items`, `shipments`, `shipment_events`, `returns`, `return_items`, `refunds`, `invoices` | The normalized order data users see on the dashboard. |
| `user_corrections` | Manual field-level overrides. Highest merge precedence - see `Savv\Services\MergeService`. |
| `audit_logs` | Redacted audit trail: identifiers, action types, timestamps - never personal order content. |

### `import_sessions` columns

`public_id`, `user_id`, `consent_id`, `provider`, `status` (14-state
lifecycle - see `Savv\Enums\ImportSessionStatus`), `runner_process_ref`,
`display_ref`, `view_token_hash` (SHA-256 hash of a single-use browser-view
token, never the raw token), `view_token_expires_at`, `safe_error_code`,
`expires_at`, `last_activity_at`, `terminated_at`, timestamps.

## Money

All amounts are stored as `bigint` **integer minor units** (paise) -
`total_minor`, `amount_minor`, etc. Never floating point. See
`Savv\Support\Money` for parsing/formatting.

## Deduplication identity

- Order: `user_id + provider + provider_order_id` (unique index on `orders`).
- Order item: `provider_item_id` when the provider exposes one, otherwise a
  deterministic fingerprint hash of normalized title + variant + quantity +
  line total (`Savv\Services\DeduplicationService`) - never title alone.
- Shipment event: append-only, deduplicated via a hash of
  shipment + status + timestamp + description (`event_hash`).

## Running migrations

```
php artisan migrate
```

Tests run against a separate `savv_mvp_test` database (see
`backend/phpunit.xml`) - create it the same way as the main database before
running `php artisan test`.

## Backup and restoration

```bash
# Backup
mysqldump -u savv_moutasim -p --single-transaction savv_mvp > savv_mvp_backup.sql

# Restore
mysql -u savv_moutasim -p savv_mvp < savv_mvp_backup.sql
```

Run backups on a schedule appropriate to your deployment (e.g. a daily cron
job on Ubuntu, or your host's managed MySQL/MariaDB backup feature on
cPanel). Never commit a backup file containing real user data to source
control.
