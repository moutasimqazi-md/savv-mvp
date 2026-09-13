# Runner security model

The Node.js/Playwright runner (`/runner`) launches one isolated Chromium
instance per import session and is the only component that ever touches a
marketplace page. This document is the security boundary it must never
cross.

## Never collect or store

Passwords, OTPs, CAPTCHA answers, marketplace cookies, marketplace session
IDs, CSRF tokens, authorization headers, browser localStorage values,
complete browser profiles, complete authenticated HTML pages, screenshots
containing credentials, screen recordings, keystroke logs, full payment-card
information, saved payment instruments.

## Never do

- Add listeners to password or OTP inputs, or inspect their values.
- Capture screenshots during authentication, or record the browser stream.
- Log browser input.
- Store marketplace cookies in MariaDB.
- Upload browser profiles anywhere.
- Reuse a marketplace session across imports (every import requires a fresh
  login - the temporary Chromium profile is deleted when the session ends).
- Replay marketplace requests from PHP.
- Call undocumented marketplace APIs, or reverse-engineer private mobile APIs.
- Attempt to disguise automation, rotate IPs/proxies, or modify browser
  fingerprints.
- Bypass CAPTCHA, a security challenge, or a rate limit. If the session hits
  one, the user completes it personally or cancels the import - the runner
  never intervenes.
- Automate purchases, cancellations, returns, or refunds.

## Enforced boundaries

- **Network binding**: the runner's internal API binds to `127.0.0.1` only
  (`src/server.js` refuses to start on any other host) and is never
  reachable from outside the machine it runs on.
- **Request authentication**: every request from Laravel is signed with
  HMAC-SHA256 over `METHOD\npath\ntimestamp\nnonce\nsha256(body)`
  (`Savv\Support\HmacSigner` on the Laravel side,
  `runner/src/security/verifySignature.js` on the runner side). Requests are
  rejected for a missing/invalid signature, a timestamp outside a 5-minute
  skew, a body that doesn't match its signed hash, or a nonce that has
  already been used (in-memory replay cache, pruned by expiry).
- **Navigation allowlist / SSRF guard**
  (`runner/src/security/navigationGuard.js`): the isolated browser may only
  navigate to a small, explicitly reviewed list of hosts per provider (the
  marketplace's own domain plus its normal sign-in redirect hosts) over
  `https:`/`http:`. Localhost, `127.0.0.0/8`, RFC1918 private ranges,
  link-local addresses (including the `169.254.169.254` cloud metadata
  address), and `file:`/`ftp:`/`data:`/`javascript:`/`chrome:` schemes are
  always blocked, regardless of provider.
- **Process isolation**: each session gets its own temporary Chromium
  profile directory and (in the streamed/Ubuntu deployment) its own X
  display number, never reused between sessions. Every spawned process uses
  argument arrays, never shell string concatenation with user/page-derived
  input. Chromium is never launched as root (`sessionManager.js` refuses).
- **Lifecycle limits**: 15-minute maximum session lifetime, 5-minute
  inactivity timeout, single-use short-lived browser-view tokens, and a
  `php artisan imports:cleanup` command (run every minute by the scheduler)
  that terminates orphaned sessions and purges stale previews.
- **Parser output**: parsers return only the structured fields listed in
  [architecture.md](architecture.md) - never raw/complete HTML, and every
  string is sanitized (`runner/src/parsers/shared/sanitize.js`) and every
  URL is host/scheme-checked before being sent to Laravel, which
  independently re-validates everything again
  (`Savv\Services\ImportPreviewValidator`,
  `Savv\Support\ForbiddenFieldGuard`) and rejects any payload containing a
  key that looks like credential/session material.
- **Unsupported layouts**: if a parser's `supportsCurrentPage()` returns
  false, the runner stops immediately and returns the fixed message
  *"Savv Companion could not safely read this page version. No account
  credentials or page contents were uploaded."* - it never falls back to
  guessing at page structure.

## What this demonstration does not do

It does not claim official Amazon or Flipkart integration, does not
guarantee protection from account challenges or suspensions, and does not
run continuous background scraping - scanning only happens after an
explicit user click, on the page currently open in that session's isolated
browser.
