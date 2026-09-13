# Parser maintenance

## Current status: synthetic fixtures only

`runner/src/parsers/amazon-in/index.js` and `runner/src/parsers/flipkart/index.js`
are built **only** against the synthetic fixtures in `runner/fixtures/`
(invented `.savv-fixture-*`/`.savv-fixture-fk-*` markers, invented order
IDs, invented product names and prices). They do not use real amazon.in or
flipkart.com DOM selectors, because none have been supplied. Do not point
either parser at a real marketplace page as-is - `supportsCurrentPage()`
will correctly return `false` for a real page today, which is intentional:
an unrecognized layout must produce the safe
*"Savv Companion could not safely read this page version"* message, never a
guess.

## Requesting real fixtures

Before extending a parser with real selectors, get sanitized HTML captured
from a page you are personally authorized to view (your own order-history
page). Before sharing it, remove:

- Names, addresses, phone numbers, email addresses
- Real order IDs and tracking IDs
- Payment details
- Cookies, tokens, and any embedded personal metadata (check `<script>`
  blocks and data attributes, not just visible text)

Save each sanitized fixture under `runner/fixtures/amazon-in/` or
`runner/fixtures/flipkart/`, named for what it demonstrates (e.g.
`delivered.html`, `return.html`), matching the existing synthetic set:
`order.html`, `delivered.html`, `cancelled.html`, `return.html`,
`refund.html`, plus one `unknown-layout.html` per provider if you have an
example of a changed/redesigned layout.

## Extending a parser

Each parser implements the shared interface in
`runner/src/parsers/shared/parserInterface.js`:

```
supportsCurrentPage, detectPageType, extractOrders, extractOrderItems,
extractShipments, extractReturns, extractRefunds, normalize, validate,
redact, getVersion
```

When adding real selectors:

1. Keep the existing `.savv-fixture-*` selector path working (or gate it
   behind a fallback chain) so the synthetic tests in `runner/tests/parsers.test.js`
   keep passing - they're your regression suite for structural changes.
2. Add the real selector as a **fallback chain**, not a replacement: try a
   semantic path first (heading text, ARIA labels, ID-independent DOM
   relationships), then a class-name path, so a class-name-only redesign
   doesn't immediately break extraction.
3. Every extracted value must go through `cleanText()` /
   `sanitizeUrlOrNull()` from `runner/src/parsers/shared/sanitize.js` -
   never return a raw string or unchecked URL.
4. Bump the version string returned by `getVersion()` (e.g.
   `amazon-in@0.2.0`) whenever selectors change - it's stored on every
   `Order`/`ImportBatch` row so you can tell which parser version produced
   which data.
5. Respect the limits already enforced in the parser (`MAX_ORDERS`,
   `MAX_ITEMS_PER_ORDER`) and in `sessionManager.js`
   (`RUNNER_MAX_PAGES_PER_SCAN`, `RUNNER_PAGE_NAVIGATION_DELAY_MS`) - do not
   remove them to "catch more data."
6. Add a fixture (sanitized, from a page you're authorized to view) for
   every new page variant you handle, and a test in
   `runner/tests/parsers.test.js` asserting on it.

## Running parser tests

```bash
cd runner
npm install
npm test
```

This runs `node --test`, which launches a real (headless) Chromium via
Playwright against each fixture file - no network access, no real
marketplace pages involved.
