# Administrator database setup

Run this manually, once, as a MariaDB administrator. It is never run
automatically by the application, and the real password is never committed
to source control.

```sql
CREATE DATABASE savv_mvp
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER 'savv_moutasim'@'localhost'
    IDENTIFIED BY 'REPLACE_WITH_A_STRONG_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX,
      DROP, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
ON savv_mvp.*
TO 'savv_moutasim'@'localhost';

FLUSH PRIVILEGES;
```

For the test database used by `php artisan test` (see `backend/phpunit.xml`),
repeat with a second database:

```sql
CREATE DATABASE savv_mvp_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX,
      DROP, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
ON savv_mvp_test.*
TO 'savv_moutasim'@'localhost';

FLUSH PRIVILEGES;
```

After creating the database and user, put the chosen password into
`backend/.env` (`DB_PASSWORD=...`) - never into `.env.example` or any
committed file.

## cPanel-managed MariaDB/MySQL

If your MariaDB is managed through cPanel (see
[deployment-cpanel.md](deployment-cpanel.md)) instead of a raw shell, use
cPanel's **MySQL® Databases** page instead of the SQL above:

1. Create a database - cPanel will prefix it with your cPanel username,
   e.g. `cpaneluser_savv_mvp`.
2. Create a database user the same way (prefixed similarly), with a strong
   generated password.
3. Add the user to the database with **All Privileges**.
4. Put the resulting prefixed database name/username/password into
   `backend/.env`.

## Secret rotation

If `DB_PASSWORD`, `RUNNER_SHARED_SECRET`, or `APP_KEY` are ever exposed
(committed by mistake, leaked in a log, shared insecurely):

1. Generate a new value (`php artisan key:generate` for `APP_KEY`; a long
   random string for the others, e.g. `php -r "echo bin2hex(random_bytes(32));"`).
2. Update `backend/.env` (and `runner/.env` for `RUNNER_SHARED_SECRET`,
   which must match exactly in both places).
3. For `DB_PASSWORD`, also run `ALTER USER 'savv_moutasim'@'localhost'
   IDENTIFIED BY 'NEW_PASSWORD'; FLUSH PRIVILEGES;` in MariaDB (or cPanel's
   password-change UI).
4. Restart `php artisan queue:work`, the runner (`npm start` /
   `pm2 restart savv-runner`), and the web server.
5. Rotating `APP_KEY` invalidates existing encrypted values and signed
   cookies - users will be signed out.
