# Limitations

This is a demonstration, not an official Amazon or Flipkart integration, and
not a claim of complete marketplace coverage. It does not guarantee
continuous synchronization, guaranteed return eligibility, or protection
from marketplace account challenges/suspensions.

## Nothing here has been executed

This repository was written in an environment with no PHP, Composer,
Node.js, or MariaDB installed, so nothing could be run, migrated, built, or
tested during development. Everything above this line is source code the
author has read and reasoned about, not a running, verified system. Before
trusting it:

```bash
cd backend && composer install && php artisan test
cd ../runner && npm install && npm test
```

...and fix whatever those turn up - dependency version drift (this was
written against Laravel 12 / Playwright 1.48-era APIs, which may have moved
on by the time you install them), typos, and any Laravel/Playwright API
details that differ from what's assumed here.

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
