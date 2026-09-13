# Architecture

Savv MVP is a demonstration, not an official Amazon or Flipkart integration.
It has two runtime components plus a database:

```
User's browser
    |
    v
Laravel (PHP 8.4, /backend) --- MariaDB 10.11.19
    |  signed internal HTTP calls (HMAC-SHA256, 127.0.0.1 only)
    v
Node.js runner (/runner) --- Playwright --- isolated Chromium
```

## Request flow for one import

1. User signs in to Savv MVP (Laravel session auth) and opens **Connections**.
2. User picks Amazon India or Flipkart, reads the consent notice, and accepts it.
   Laravel records a `Consent` row.
3. Laravel creates an `ImportSession` row (status `requested`) and calls the
   runner's `POST /internal/sessions` over a signed loopback HTTP request.
4. The runner launches one isolated, persistent-profile Chromium instance
   (headed - visible either on the developer's own screen locally, or over
   Xvfb+x11vnc+noVNC in the Ubuntu demo deployment) and navigates it to the
   marketplace's order-history page.
5. Laravel's `/imports/{id}` page embeds `/imports/{id}/browser` in an
   iframe. In the Ubuntu deployment that page connects a noVNC viewer over a
   same-origin websocket proxy, authenticated with a single-use, short-lived
   view token; locally it just tells the developer to look at the window
   that opened on their own screen.
6. The user logs into the marketplace **directly inside that isolated
   browser** - Savv never sees the password or OTP.
7. User clicks "I'm logged in - scan my orders". Laravel dispatches
   `ScanImportSession` onto the `imports` queue, which calls the runner's
   `POST /internal/sessions/{id}/scan`.
8. The runner checks the current page's hostname against its navigation
   allowlist, runs the provider parser's `supportsCurrentPage()` /
   `extractOrders()`, sanitizes everything, and returns normalized JSON -
   never raw HTML, never credentials.
9. Laravel re-validates the JSON independently
   (`Savv\Services\ImportPreviewValidator`) and stores it as `ImportPreview`
   rows. The user reviews the preview and selects what to import.
10. On confirm, Laravel dispatches `ConfirmImportSession`, which merges the
    selected previews into permanent `Order`/`OrderItem`/`Shipment`/
    `OrderReturn`/`Refund`/`Invoice` rows inside one DB transaction
    (`Savv\Services\ImportConfirmationService`), applying merge precedence
    (`Savv\Services\MergeService`) and deduplication
    (`Savv\Services\DeduplicationService`).
11. Laravel dispatches `TerminateImportSession`, which asks the runner to
    stop the browser and then marks the session terminated. The runner
    deletes the temporary Chromium profile directory.

## Why two processes instead of one

Laravel cannot read a cross-origin marketplace tab from PHP, and Savv MVP
must never touch marketplace cookies/sessions/tokens. The only way to let a
user authenticate themselves against a real marketplace and then read what's
*visibly rendered* afterward is to run an isolated browser Laravel doesn't
have credential-level access to, and only ask it for structured, sanitized
data - never the page's HTML, never its storage.

The runner is a separate Node.js/Playwright process, reachable only on
`127.0.0.1`, authenticated with HMAC-signed requests (see
[api-contract.md](api-contract.md)) so that even another local process
cannot drive it without the shared secret.

## Directories

```
/backend   Laravel 12 application
/runner    Node.js 22 + Playwright isolated-browser service
/docs      This documentation
```

Backend tests live in `backend/tests` (PHPUnit, run against MariaDB).
Runner tests live in `runner/tests` (Node's built-in test runner, driving a
real headless Chromium against the synthetic fixtures in `runner/fixtures`).

## What's deliberately NOT built

See [limitations.md](limitations.md) for the full list - notably: no
automatic multi-page pagination beyond 5 pages, no CAPTCHA handling, no
account-level manual-entry/CSV-import UI in this pass (the underlying
`user_corrections` table exists for future use), and real Amazon/Flipkart
selectors are not implemented (see [parser-maintenance.md](parser-maintenance.md)).
