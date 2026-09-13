# Ubuntu demonstration deployment

This is the full "streamed remote browser" deployment: Nginx in front of
Laravel (PHP-FPM), a Node.js runner spawning one Xvfb + x11vnc + websockify
chain per import session, and MariaDB. No Docker.

Requires root/sudo on the box (a plain shared-hosting account cannot do
this - see [deployment-cpanel.md](deployment-cpanel.md) for that case).

## 1. System packages

```bash
sudo apt update
sudo apt install -y nginx mariadb-server php8.4-fpm php8.4-cli \
    php8.4-bcmath php8.4-curl php8.4-mbstring php8.4-mysql php8.4-xml \
    php8.4-intl php8.4-fileinfo composer \
    xvfb x11vnc novnc websockify \
    ca-certificates curl gnupg
```

Confirm MariaDB is 10.11.19 (`mysql --version`); if your distro ships a
different series, install from MariaDB's own apt repository instead of the
Ubuntu default.

Install Node.js 22 LTS (via NodeSource or your preferred method), then
Playwright's Chromium dependencies:

```bash
sudo npx playwright install-deps chromium
```

## 2. Application user

Run everything as a dedicated, non-root user (never root - the runner
refuses to launch Chromium as root anyway):

```bash
sudo adduser --system --group savv
sudo mkdir -p /opt/savv-mvp
sudo chown savv:savv /opt/savv-mvp
```

Deploy the repository to `/opt/savv-mvp` (git clone or rsync), owned by
`savv:savv`.

## 3. Database

Follow [admin-database-setup.md](admin-database-setup.md).

## 4. Backend

```bash
cd /opt/savv-mvp/backend
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
# edit .env: APP_ENV=production, APP_URL=https://your-domain,
# DB_PASSWORD=..., RUNNER_SHARED_SECRET=..., RUNNER_STREAM_ENABLED=true,
# SESSION_SECURE_COOKIE=true
php artisan migrate --force
npm install && npm run build
sudo chown -R savv:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## 5. Runner

```bash
cd /opt/savv-mvp/runner
cp .env.example .env
# edit .env: RUNNER_SHARED_SECRET=<same value as backend/.env>,
# RUNNER_HEADED=false
npm install   # postinstall downloads Playwright's Chromium
```

## 6. Process supervision

Use Supervisor (or systemd - either works; Supervisor shown here) for the
queue worker, the runner, and **one shared, long-lived websockify broker**.
Sample configs are in `docs/samples/`:

- `docs/samples/supervisor-savv-queue.conf`
- `docs/samples/supervisor-savv-runner.conf`
- `docs/samples/supervisor-savv-websockify.conf`

```bash
sudo cp docs/samples/supervisor-savv-queue.conf /etc/supervisor/conf.d/
sudo cp docs/samples/supervisor-savv-runner.conf /etc/supervisor/conf.d/
sudo cp docs/samples/supervisor-savv-websockify.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
```

The websockify broker is the **only** websocket process Nginx ever proxies
to. It multiplexes every concurrent import session using its `TokenFile`
plugin: the runner writes a small `token -> 127.0.0.1:<vncPort>` mapping
file per session under `runner/tmp/vnc-tokens/` when a view token is issued
(`POST /internal/sessions/{id}/view-token`), and removes it when the token
expires or the session stops. This avoids needing Nginx itself to do
per-session dynamic routing.

Do **not** supervise Xvfb/x11vnc directly - those are spawned per-session by
the runner (`runner/src/display/streamingStack.js`) and torn down when the
session ends.

## 7. Scheduler (system cron)

```bash
sudo crontab -u savv -e
```

Add:

```
* * * * * cd /opt/savv-mvp/backend && php artisan schedule:run > /dev/null 2>&1
```

This runs `imports:cleanup` every minute, using a database lock
(`withoutOverlapping`) so overlapping cron ticks never run it concurrently.

## 8. Nginx

Sample config: `docs/samples/nginx-savv.conf`. Key points:

- Laravel's `public/` is the document root, as usual.
- A `location /rbi/` block proxies the noVNC websocket to the **shared**
  websockify broker on `127.0.0.1:6900` - **never** expose the raw VNC or
  websockify port directly; only Nginx should be reachable from the
  internet, over HTTPS.
- `location /internal/` must not exist in the public server block at all -
  the runner's internal API is never proxied publicly.

## 9. HTTPS

Use certbot (Let's Encrypt) or your certificate of choice:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain
```

Then set `SESSION_SECURE_COOKIE=true` in `backend/.env` (already set in
step 4) and confirm `APP_URL` uses `https://`.

## 10. Firewall

```bash
sudo ufw allow 'Nginx Full'
sudo ufw allow OpenSSH
sudo ufw enable
```

The runner's port (3010, internal API), the shared websockify broker's port
(6900), and every per-session VNC port (`5900 + display number`) must
**not** be opened - they're only ever reached via `127.0.0.1`, either
directly by PHP-FPM or proxied by Nginx's `/rbi/` block on the same
machine.

## Restarting after a deploy

```bash
sudo supervisorctl restart savv-queue:*
sudo supervisorctl restart savv-runner
sudo supervisorctl restart savv-websockify
sudo systemctl reload php8.4-fpm nginx
```
