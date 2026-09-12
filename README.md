# Savv MVP

Savv MVP lets a signed-in user bring their own Amazon India and Flipkart order,
shipment, return, cancellation, and refund history into one dashboard — imported
only from what the **Savv Companion** browser extension can see on the page
after the user manually signs in and presses "Scan this page" themselves.

Savv MVP never asks for a marketplace password or OTP, never touches
marketplace cookies/sessions/tokens, and never automates a marketplace
account. See [docs/extension-security.md](docs/extension-security.md) and
[docs/privacy-model.md](docs/privacy-model.md) for the full boundary.

> Order information reflects the most recent user-authorized import. Verify
> purchases, deliveries, returns, and refunds on the official Amazon or
> Flipkart website.

## Project layout

```
/backend     Laravel 12 application (PHP 8.4, MariaDB 10.11.19)
/extension   Manifest V3 "Savv Companion" browser extension
/docs        Architecture, security, deployment, and API documentation
/tests       Extension parser tests and synthetic HTML fixtures
```

Backend-specific PHPUnit tests live under `backend/tests` (standard Laravel
layout). The top-level `/tests` directory holds parser-level tests for the
browser extension, which run under Node's built-in test runner and never
touch the Laravel app.

## Quick start (Windows development)

See [docs/deployment-windows.md](docs/deployment-windows.md) for full detail.
Short version:

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

In a second terminal:

```powershell
cd backend
php artisan queue:work database --queue=imports,default --sleep=3 --tries=3 --timeout=120
```

Then load `extension/` as an unpacked extension in Chrome or Edge
(`chrome://extensions` → Developer mode → "Load unpacked").

## Ubuntu production

See [docs/deployment-ubuntu.md](docs/deployment-ubuntu.md).

## Database

MariaDB 10.11.19 only. No Docker, no Redis, no Horizon. See
[docs/database.md](docs/database.md) and
[docs/admin-database-setup.md](docs/admin-database-setup.md) for the manual
database/user creation SQL an administrator must run.

## Documentation index

- [docs/architecture.md](docs/architecture.md)
- [docs/database.md](docs/database.md)
- [docs/admin-database-setup.md](docs/admin-database-setup.md)
- [docs/extension-security.md](docs/extension-security.md)
- [docs/parser-maintenance.md](docs/parser-maintenance.md)
- [docs/privacy-model.md](docs/privacy-model.md)
- [docs/deployment-windows.md](docs/deployment-windows.md)
- [docs/deployment-ubuntu.md](docs/deployment-ubuntu.md)
- [docs/api-contract.md](docs/api-contract.md)
- [docs/limitations.md](docs/limitations.md)

## Status

This repository was scaffolded by hand (no `composer create-project` was run,
because this environment has no PHP/Composer/Node/MariaDB installed). Before
first run you must install the toolchain described in
[docs/deployment-windows.md](docs/deployment-windows.md) or
[docs/deployment-ubuntu.md](docs/deployment-ubuntu.md), then run
`composer install` to generate `vendor/` and the autoloader. Nothing here has
been executed or test-run yet — see [docs/limitations.md](docs/limitations.md).
