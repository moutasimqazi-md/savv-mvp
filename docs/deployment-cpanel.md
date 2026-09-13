# cPanel deployment

Read this before deploying to cPanel - the architecture has a hard
constraint that most cPanel hosting doesn't meet.

## The constraint: the runner needs a real machine, not shared hosting

The isolated-browser import feature (`/runner`) launches real Chromium
processes and, in the streamed deployment, Xvfb + x11vnc + a websockify
broker (see [architecture.md](architecture.md) and
[deployment-ubuntu.md](deployment-ubuntu.md)). That requires:

- Installing system packages (Chromium's dependencies, Xvfb, x11vnc,
  websockify) - not available on ordinary shared cPanel hosting.
- Running a long-lived Node.js process bound to a port, plus per-session
  Xvfb/Chromium processes - shared hosting plans generally disallow
  persistent background daemons and cap process/memory limits far below
  what a browser needs.
- Root or sudo to install those packages and configure Supervisor/systemd -
  not available on shared hosting.

**Plain shared cPanel hosting cannot run the runner.** This is true
regardless of how the Laravel side is configured - it's a hosting-tier
limitation, not a configuration problem to work around.

## What does work on cPanel

### Option A: cPanel on a VPS/dedicated server with WHM/root access

Many "cPanel" offerings are actually a VPS or dedicated server with
WHM/cPanel installed on top, where you *do* have root via SSH. In that case:

1. Follow [deployment-ubuntu.md](deployment-ubuntu.md) as-is for the system
   packages, the runner, Supervisor, and the websockify broker - none of
   that goes through cPanel at all.
2. Use cPanel/WHM only for the parts it's good at:
   - **MySQL® Databases** page to create the `savv_mvp` database and user
     (see the cPanel-specific note in
     [admin-database-setup.md](admin-database-setup.md) - cPanel prefixes
     names with your account username).
   - **MultiPHP Manager** / **Select PHP Version** to select PHP 8.4 and
     enable the required extensions (`bcmath`, `ctype`, `curl`, `dom`,
     `fileinfo`, `filter`, `intl`, `mbstring`, `openssl`, `pdo_mysql`,
     `session`, `tokenizer`, `xml`) for the domain.
   - **Domains** page to point the domain's document root at
     `/home/USER/savv-mvp/backend/public`.
   - **Terminal** (or SSH directly) to run `composer install`, `npm run
     build`, `php artisan migrate`, etc.
   - **Cron Jobs** page for the scheduler (step below) instead of editing
     crontab by hand.
   - Nginx/Apache config for the `/rbi/` websocket proxy still needs to be
     hand-edited (via WHM's "Include Editor" for Apache, or directly for
     Nginx if your stack uses it) - see `docs/samples/nginx-savv.conf` and
     adapt for Apache + `mod_proxy_wstunnel` if that's your setup.

### Option B: ordinary shared cPanel hosting (no root)

You can still host the Laravel dashboard, database, and account/privacy
pages here - just not live imports. Realistic choices:

1. **Deploy Laravel only, point Connections at nothing.** Skip installing
   `/runner` entirely. The Connections/import pages will show but any
   attempt to start a session will fail with a clear "could not start the
   temporary browser" error (see `ImportSessionService::start()`'s
   `runner_unreachable` failure path) - there is no live-import feature to
   demo, but the rest of the app (viewing/manually reviewing previously
   imported data, privacy/export/delete) works normally.
2. **Run the runner elsewhere.** Host `/runner` on a small VPS you control
   (any provider), and point `RUNNER_BASE_URL` at it from the cPanel-hosted
   Laravel app. This means the runner is no longer strictly loopback-only
   between the two processes - you must put a private network path between
   them (a WireGuard/VPN tunnel, or at minimum an IP-allowlisted HTTPS
   reverse proxy in front of the runner with the same HMAC signing already
   enforced). This is materially more exposure than the reference
   architecture and is not the design this project ships with by default -
   only do it if you understand and accept that tradeoff.

Given the above, Option A (a VPS-backed cPanel/WHM account) is the
supported path for the full demonstration; Option B is a reduced,
Laravel-only deployment.

### cPanel-specific steps common to both

**Cron (scheduler):** cPanel's **Cron Jobs** page, add:

```
* * * * * cd /home/USER/savv-mvp/backend && php artisan schedule:run > /dev/null 2>&1
```

**Composer without SSH:** if your plan has no Terminal/SSH at all, build
`vendor/` on your own machine (same PHP major.minor version) and upload it
via File Manager/FTP, then run `php artisan migrate` and asset builds
locally against the same database over an allowed remote MySQL connection,
or via a one-off SSH session if your host ever grants temporary access.

**Storage permissions:** cPanel's file owner for web-served files is
typically your account user, not `www-data` - `storage/` and
`bootstrap/cache/` just need to be writable by that same user (no `chown`
to a separate web server user is usually needed, unlike the Ubuntu doc's
`www-data` step).

**HTTPS:** cPanel's **SSL/TLS Status** page with AutoSSL (or your own
certificate) - equivalent to the certbot step in
[deployment-ubuntu.md](deployment-ubuntu.md).
