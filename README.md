# Edited Savv MVP

Savv MVP is a **demonstration**, not an official Amazon or Flipkart
integration. A signed-in user starts a temporary, isolated remote browser
from the Savv website, logs into Amazon India or Flipkart **directly and
personally** inside it, and imports the order, shipment, return, and refund
information that page visibly renders into a Laravel dashboard.

Savv MVP never asks for a marketplace password or OTP, never touches
marketplace cookies/sessions/tokens, and never automates a marketplace
account. See [docs/runner-security.md](docs/runner-security.md) and
[docs/privacy-model.md](docs/privacy-model.md) for the full boundary.

> Order information reflects the most recent user-authorized import. Verify
> purchases, deliveries, returns, and refunds on the official Amazon or
> Flipkart website.

## Project layout

```
/backend   Laravel 12 application (PHP 8.4, MariaDB 10.11.19)
/runner    Node.js 22 + Playwright isolated-browser service
/docs      Architecture, security, deployment, and API documentation
```

Backend tests live in `backend/tests` (PHPUnit, against MariaDB). Runner
tests live in `runner/tests` (Node's built-in test runner, driving a real
headless Chromium against the synthetic fixtures in `runner/fixtures`).

## Quick start (Windows development)

See [docs/deployment-windows.md](docs/deployment-windows.md) for full
detail - Chromium runs **headed** locally (a visible window opens on your
own screen; there's no streaming/noVNC in local dev). Short version:

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

In a second terminal (queue worker):

```powershell
cd backend
php artisan queue:work database --queue=imports,default --sleep=3 --tries=3 --timeout=120
```

In a third terminal (the runner):

```powershell
cd runner
copy .env.example .env
npm install
npm start
```

## Ubuntu demonstration deployment

Full streamed setup (Nginx, Xvfb + x11vnc + a shared noVNC/websockify
broker, Supervisor, systemd cron). See
[docs/deployment-ubuntu.md](docs/deployment-ubuntu.md).

## cPanel

Plain shared cPanel hosting **cannot** run the isolated-browser runner (no
root, no persistent daemons, no system packages). See
[docs/deployment-cpanel.md](docs/deployment-cpanel.md) for what does and
doesn't work, and the supported VPS+WHM path.

## Database

MariaDB 10.11.19 only. No Docker, no Redis, no Horizon. See
[docs/database.md](docs/database.md) and
[docs/admin-database-setup.md](docs/admin-database-setup.md) for the manual
database/user creation SQL an administrator must run.

## Documentation index

- [docs/architecture.md](docs/architecture.md)
- [docs/database.md](docs/database.md)
- [docs/admin-database-setup.md](docs/admin-database-setup.md)
- [docs/runner-security.md](docs/runner-security.md)
- [docs/parser-maintenance.md](docs/parser-maintenance.md)
- [docs/privacy-model.md](docs/privacy-model.md)
- [docs/deployment-windows.md](docs/deployment-windows.md)
- [docs/deployment-ubuntu.md](docs/deployment-ubuntu.md)
- [docs/deployment-cpanel.md](docs/deployment-cpanel.md)
- [docs/api-contract.md](docs/api-contract.md)
- [docs/limitations.md](docs/limitations.md)

## Status

This repository was originally scaffolded by hand (no `composer
create-project` / `npm create`) in an environment with no PHP/Composer/
Node/MariaDB installed. It has since been installed and verified end-to-end
on a real machine: 107/107 backend tests pass, 21/21 runner tests pass, and
a full register → login → connect → start import session → real headed
Chromium reaches Amazon's sign-in page → stop → cleanup walkthrough
completed successfully. See
[docs/limitations.md](docs/limitations.md#verified-working-as-of-first-local-run)
for exactly what was tested and the real bugs that surfaced and were fixed
along the way.

The Amazon India and Flipkart parsers only recognize the synthetic fixtures
in `runner/fixtures/` today (invented data, invented markup) - see
[docs/parser-maintenance.md](docs/parser-maintenance.md) before pointing
either parser at a real marketplace page.
