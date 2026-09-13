# Parser maintenance

## Current status

Each parser now has three extraction paths, tried in order:

1. **Fixture-based** (`.savv-fixture-*` / `.savv-fixture-fk-*` markers) -
   exists purely for the test suite (`runner/fixtures/`), invented data.
2. **Real selectors** - `amazon-in/index.js`'s `extractRealOrders()` is built
   against real amazon.in "Your Orders" markup (`order-card`,
   `yohtmlc-order-id`, `order-header__header-list-item`,
   `yohtmlc-product-title`, `item-box`,
   `yohtmlc-shipment-status-primaryText`), confirmed against a sanitized
   real page. It deliberately never queries `.yohtmlc-recipient` (the "Ship
   to" block - shipping address). **Flipkart has no real-selector path** -
   its real markup uses webpack/CSS-module hashed class names (e.g.
   `yEVTCj`, `col-4-12 RmBXvt`) that regenerate on every Flipkart deploy, so
   hardcoding them today would provide little lasting value; it relies on
   path 3 instead.
3. **Heuristic fallback** (`shared/heuristicExtract.js`) - runs only when
   neither of the above finds anything (e.g. after a layout change, or for
   Flipkart generally). Provider-agnostic pattern matching: looks for a
   currency amount plus either a known status phrase or a product link
   within one small container, then extracts only narrow, atomic fields
   (order id, price, date, one whitelisted status phrase, product link,
   quantity) - **never the container's whole text**, specifically because
   real order cards often show the shipping address or other personal
   fields in the same block. A candidate without an explicit, labeled order
   id is dropped rather than given a synthetic one, since a synthetic id
   would drift between scans and create duplicate orders instead of
   updating the same one - this path favors under-extraction over unstable
   data. See its module docblock for the full reasoning.

An unrecognized layout (none of the three paths finds anything) still
produces the safe *"Savv Companion could not safely read this page
version"* message, never a guess.

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
   never return a raw string or unchecked URL. Pass
   `sanitizeUrlOrNull(url, { isImage: true })` for a `product_image_url` -
   real product images are served from a separate CDN host (e.g.
   `m.media-amazon.com`, not `www.amazon.in`), which the default (link)
   allowlist correctly rejects. Never widen the default link allowlist
   itself to work around this - only `isImage: true` should ever accept a
   CDN host, since link fields are what the user's browser actually
   navigates to when clicked. Laravel independently re-validates via
   `Savv\Support\MarketplaceUrlValidator::sanitizeOrNull($url, isImage: true)`
   and `config('savv.allowed_image_hosts')` - update both lists together.
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
