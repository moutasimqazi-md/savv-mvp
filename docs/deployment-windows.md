# Windows development setup

No Docker, no WSL required (though WSL is fine if you prefer a Linux shell).
This is the local development path: Chromium runs **headed** (a visible
window opens directly on your screen) - there is no Xvfb/noVNC locally.

## 1. Install the toolchain

- **PHP 8.4** - e.g. via [php.new](https://php.new) or a standalone zip from
  windows.php.net. Enable these extensions in `php.ini` (uncomment the
  `extension=` lines): `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`,
  `filter`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`,
  `tokenizer`, `xml`.
- **Composer** - from [getcomposer.org](https://getcomposer.org).
- **MariaDB 10.11.19** - the official MSI installer from mariadb.org (pick
  version 10.11.19 specifically, not a newer series).
- **Node.js 22 LTS** - from [nodejs.org](https://nodejs.org). Used for
  compiling frontend assets (`backend/`) and for running the Playwright
  runner (`runner/`) - not required by anything else at runtime.

Verify:

```powershell
php -v
composer -V
node -v
mysql --version
```

## 2. Database

Follow [admin-database-setup.md](admin-database-setup.md) to create the
`savv_mvp` (and `savv_mvp_test`) database and the `savv_moutasim` user in
your local MariaDB.

## 3. Backend (Laravel)

```powershell
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Edit `.env`: set `DB_PASSWORD` to the password you chose, and generate a
`RUNNER_SHARED_SECRET`:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Then:

```powershell
php artisan migrate
npm install
npm run build
php artisan serve
```

Savv MVP is now at http://localhost:8000.

In a second terminal, run the queue worker (imports are processed
asynchronously):

```powershell
cd backend
php artisan queue:work database --queue=imports,default --sleep=3 --tries=3 --timeout=120
```

In a third terminal, run the scheduler (needed for `imports:cleanup`) - on
Windows there's no cron, so just loop it, or use Task Scheduler to run
`php artisan schedule:run` every minute:

```powershell
cd backend
while ($true) { php artisan schedule:run; Start-Sleep -Seconds 60 }
```

## 4. Runner (Node.js + Playwright)

```powershell
cd runner
copy .env.example .env
```

Edit `runner/.env`: set `RUNNER_SHARED_SECRET` to the **same** value you put
in `backend/.env`, and set `RUNNER_HEADED=true` (the default).

```powershell
npm install
npm start
```

`npm install`'s `postinstall` step downloads a Chromium build for Playwright
- this needs network access once, but nothing at application runtime beyond
what Chromium itself needs to reach the marketplace.

## 5. Try it

1. Open http://localhost:8000, register, go to **Connections**.
2. Pick a provider, accept the consent notice, click "Start temporary
   browser" - a real Chromium window opens on your screen.
3. Log in there yourself, open the order-history page.
4. Back in Savv MVP, click "I'm logged in - scan my orders".

Because the parsers only recognize the synthetic fixtures in
`runner/fixtures/` (see [parser-maintenance.md](parser-maintenance.md)),
scanning a real marketplace page today will correctly report the "could not
safely read this page version" message - that's expected until real,
sanitized fixtures are supplied.

## Tests and linting

```powershell
cd backend
php artisan test
./vendor/bin/pint          # code style

cd ..\runner
npm test
```
