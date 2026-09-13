# Limitations

This is a demonstration, not an official Amazon, Walmart, or Anthropic
integration, and not a claim of complete coverage. It does not guarantee
continuous synchronization, guaranteed return eligibility, or protection
from account challenges/suspensions. Flipkart support was removed; Amazon
India and Walmart (orders) and Claude (subscription) are the three
supported sites.

## Walmart parser is synthetic/heuristic only, and walmart.com showed a bot check

Same situation as Claude below: `runner/src/parsers/walmart/index.js` has
never been built against real walmart.com markup, only invented fixtures
(`runner/fixtures/walmart/`) plus the shared order heuristic. In manual
testing, navigating to `https://www.walmart.com/orders` in the isolated
browser surfaced a "Robot or human?" bot-check page before reaching the
actual order history. As with every other challenge, Savv never attempts to
solve or bypass it - the user completes it personally inside the isolated
browser.

## Claude parser is synthetic/heuristic only, and claude.ai showed a bot check

Like the original Amazon parser, `runner/src/parsers/claude/index.js` has
never been built against real claude.ai markup - only invented fixtures
(`runner/fixtures/claude/`) plus the generic subscription heuristic
(`runner/src/parsers/shared/subscriptionHeuristic.js`). In manual testing,
navigating to `https://claude.ai/settings/billing` in the isolated browser
surfaced a Cloudflare "Just a moment..." challenge before reaching the
actual billing page. Per the project's own rules, Savv never attempts to
solve or bypass this - the user must click through it personally inside the
isolated browser window, the same way they would for any other challenge.
Until real, sanitized claude.ai fixtures are supplied (see
[parser-maintenance.md](parser-maintenance.md)), a real scan will most
likely fall through to the heuristic path or report "could not safely read
this page version," same as Amazon did before its real-selector pass.

## Verified working (as of first local run)

This was originally written in an environment with no PHP/Composer/Node/
MariaDB installed, so it shipped unexecuted. It has since been installed and
exercised end-to-end on a real Windows machine (PHP 8.3.33, MariaDB
10.11.19, Node 22): all 20 migrations ran clean, the full backend test suite
(107 tests) and runner test suite (21 tests) pass, `npm run build` produces
working Vite assets, and a full HTTP walkthrough (register → login →
dashboard → connections → start an import session → runner launches a real
headed Chromium window that successfully navigates to Amazon's real sign-in
page → stop → temporary profile deleted) completed successfully.

That pass surfaced and fixed several real bugs, worth knowing about if
you're running an older clone of this repo or hit similar symptoms:

- `backend/public/index.php` and `.htaccess` didn't exist (never scaffolded
  by hand originally) - without them every request 500s with "Failed
  opening required .../public/index.php".
- The runner's HMAC signature check used Express's `req.path`, which is
  *relative to the router's mount point* (e.g. `/` or `/:id/scan` under the
  `/internal/sessions` mount), not the full path Laravel signs - every
  request failed with `invalid_signature` until it was switched to
  `req.originalUrl`. If you see that error, check both sides are hashing
  the same literal path string.
- `resources/js/rbi-viewer.js` imported `@novnc/novnc/core/rfb.js`, but that
  package's `exports` field only allows importing the bare package name.
- `vite.config.js` needed `build.target: 'es2022'` - `@novnc/novnc` uses
  top-level `await` internally, which fails under Vite's default target.
- A fresh Playwright Chromium install's very first launch can take longer
  than `RUNNER_REQUEST_TIMEOUT_SECONDS`'s old default of 10s (e.g. Windows
  Defender scanning the newly-extracted binary) - later launches are fast.
  Default is now 30s; bump it further if "Could not start the temporary
  browser" appears right after first install but not afterward.
- `config/hashing.php` locks the app to Argon2id, which is enforced when
  *setting* the `password` attribute (not just when reading it) - a test or
  script that does `$user->password = bcrypt(...)` will throw immediately,
  by design. Use `Hash::make()` everywhere instead of the `bcrypt()`
  helper, which is hardcoded to bcrypt regardless of app config.
- Windows-specific: a folder's stray "Read-only" attribute (common on
  OneDrive-synced paths) can make PHP's `is_writable()` wrongly report a
  perfectly writable directory as not writable - clear it with
  `attrib -R <path> /S /D` if you see "directory must be present and
  writable" errors on Windows.

None of the above are environment-specific workarounds papered over in this
doc - they were fixed in the actual source (`public/index.php`,
`verifySignature.js`, `rbi-viewer.js`, `vite.config.js`,
`config/savv.php`, the test files) and are already reflected in this repo.

Still not exercised: the Ubuntu/Xvfb/x11vnc/shared-websockify streaming path
(`RUNNER_STREAM_ENABLED=true`), and a real marketplace scan against
non-synthetic markup (see "Parsers are synthetic-fixture-only" below).

## Parsers are synthetic-fixture-only

`runner/src/parsers/amazon-in` and `runner/src/parsers/flipkart` are built
and tested only against invented fixtures in `runner/fixtures/`. Neither
recognizes a real amazon.in or flipkart.com page today - see
[parser-maintenance.md](parser-maintenance.md) for how to extend them once
sanitized real fixtures are available. Until then, every real-world scan
attempt will correctly stop with the "could not safely read this page
version" message.

## Scanning is single-page by default

The runner's scan loop looks for a `[data-savv-next-page]` link and will
follow up to `RUNNER_MAX_PAGES_PER_SCAN` (default 5) of them with a delay
between navigations, but no real parser implements that selector yet (the
synthetic fixtures are single-page). Multi-page order histories will need
that selector wired up per real fixture set.

## Not built in this pass

- **Manual order/return/refund entry forms and CSV import.** The
  `import_batches.source` enum already allows `manual`/`csv` and
  `user_corrections` exists for merge precedence, but there's no UI for
  either yet - only the runner-driven scan/preview/confirm flow is wired up
  end-to-end.
- **Correction/merge-conflict UI.** `Savv\Services\MergeService` computes
  and returns field-level conflicts when a corrected field's stored value
  differs from a new incoming observation, but nothing surfaces those
  conflicts to the user today - they're silently kept (the correction wins).
- **Hard data-retention enforcement.** `users.retention_days` exists and is
  read by the privacy page, but no scheduled job actually deletes data past
  that horizon yet.
- **Per-user revocable browser/device list.** There's no multi-device
  concept in this design (no persistent API tokens) - every import is a
  fresh, single-use session tied to one login.
- **CAPTCHA/challenge handling.** By design, the runner never attempts to
  solve or bypass one - the user must handle it personally inside the
  isolated browser, or cancel. There's no special UI state for "a challenge
  is showing," just the normal embedded viewer.
- **Rate-limit-aware backoff across sessions.** Each session independently
  stops on `429`/repeated `403` (per spec); there's no cross-session
  cooldown if a marketplace starts rate-limiting a given IP.

## Test coverage

Backend PHPUnit tests cover: registration/login/rate-limiting, order/return/
refund/import-session authorization boundaries, import-session lifecycle
(one-active-session rule, expiration, inactivity, view-token issuance and
expiry), the scheduled cleanup command, money parsing, status normalization,
text sanitization (XSS payloads), URL allowlisting (malicious URLs, scheme
rejection), forbidden-field rejection, deduplication/fingerprinting, merge
precedence (corrections win, empty never overwrites, duplicate imports
update rather than duplicate, duplicate shipment events are ignored), and
account export/deletion.

Runner tests cover: parser support/extraction against every synthetic
fixture (including the "unknown layout" case), HMAC signature verification
(valid/invalid/expired/replayed/tampered), and SSRF/navigation-allowlist
enforcement (localhost, private ranges, cloud metadata address, non-http(s)
schemes, wrong-provider hosts, unrelated external sites).

Not automated (would need a real browser + real runner process running):
end-to-end process-timeout/cleanup of an actual Chromium process, temporary
profile directory deletion on disk, and the full noVNC streaming path in the
Ubuntu deployment. These were reasoned through in code review but need
manual verification once the toolchain is installed.
