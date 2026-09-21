/**
 * Amazon US parser.
 *
 * Has three extraction paths, tried in order:
 *
 * 1. Fixture-based (`.savv-fixture-*` markers) - built and tested only
 *    against the synthetic fixtures in /runner/fixtures/amazon-us/*.html.
 *    Exists purely for the test suite.
 * 2. Real selectors (extractRealOrders() below) - built against sanitized
 *    real amazon.com "Your Orders" markup (order-card / yohtmlc-order-id /
 *    order-header__header-list-item / yohtmlc-product-title / item-box /
 *    yohtmlc-shipment-status-primaryText). These are Amazon's actual,
 *    long-stable BEM-style class names as of the fixture this was built
 *    against - see docs/parser-maintenance.md for how to refresh them.
 *    Deliberately skips the `.yohtmlc-recipient` ("Ship to") block - that's
 *    where the shipping address lives, and this project never extracts
 *    addresses.
 * 3. Heuristic fallback (shared/heuristicExtract.js) - runs only when
 *    neither of the above finds anything, e.g. after a layout change.
 *    Provider-agnostic pattern matching, deliberately narrow (only atomic
 *    fields like price/date/status/order-id/product-link, never a whole
 *    "order card" block of text) to avoid ever capturing an address or
 *    phone number that might sit in the same container. Lower precision
 *    than the real-selector path - see docs/limitations.md.
 */
import { cleanText, sanitizeUrlOrNull, isEmpty, mainPageAttribute } from '../shared/sanitize.js';
import { findHeuristicCandidates, groupCandidatesIntoOrders, buildHeuristicOrders } from '../shared/heuristicExtract.js';

const PARSER_VERSION = 'amazon-us@0.3.0-real-selectors';
const MAX_ORDERS = 100;
const MAX_ITEMS_PER_ORDER = 100;

export const amazonUsParser = {
    getVersion() {
        return PARSER_VERSION;
    },

    async supportsCurrentPage(page) {
        if ((await mainPageAttribute(page, 'data-savv-page')) === 'amazon-orders') return true;
        if ((await page.locator('.savv-fixture-order').count()) > 0) return true;
        if ((await page.locator('.order-card.js-order-card').count()) > 0) return true;

        const candidates = await findHeuristicCandidates(page);
        return groupCandidatesIntoOrders(candidates).length > 0;
    },

    async detectPageType(page) {
        return (await mainPageAttribute(page, 'data-savv-page')) ?? 'unknown';
    },

    async extractOrders(page) {
        const orderHandles = await page.locator('.savv-fixture-order').all();

        const orders = [];
        for (const handle of orderHandles.slice(0, MAX_ORDERS)) {
            const providerOrderId = await handle.getAttribute('data-savv-order-id');
            if (isEmpty(providerOrderId)) continue;

            const orderDateText = await textOrNull(handle, '.savv-fixture-order-date');
            const totalText = await textOrNull(handle, '.savv-fixture-order-total');
            const statusText = await textOrNull(handle, '.savv-fixture-status');
            const orderUrl = await hrefOrNull(handle, '.savv-fixture-order-link');

            orders.push({
                provider_order_id: cleanText(providerOrderId, 100),
                order_date: parseFixtureDate(orderDateText),
                original_status: cleanText(statusText),
                currency: 'USD',
                total: parseFixtureAmount(totalText),
                official_order_url: sanitizeUrlOrNull(orderUrl),
                observed_at: new Date().toISOString(),
                items: await this.extractOrderItems(page, handle),
                shipments: await this.extractShipments(page, handle),
                returns: await this.extractReturns(page, handle),
                refunds: await this.extractRefunds(page, handle),
            });
        }

        if (orders.length > 0) return orders;

        const realOrders = await extractRealOrders(page);
        if (realOrders.length > 0) return realOrders;

        // Neither path found anything - fall back to the provider-agnostic
        // heuristic pass (see module docblock above).
        const candidates = await findHeuristicCandidates(page);
        const groups = groupCandidatesIntoOrders(candidates).slice(0, MAX_ORDERS);
        return buildHeuristicOrders(groups, { cleanText, sanitizeUrlOrNull, isEmpty });
    },

    async extractOrderItems(page, orderEl) {
        const itemHandles = await orderEl.locator('.savv-fixture-item').all();
        const items = [];

        for (const item of itemHandles.slice(0, MAX_ITEMS_PER_ORDER)) {
            const title = await textOrNull(item, '.savv-fixture-item-link');
            if (isEmpty(title)) continue;

            const qtyText = await textOrNull(item, '.savv-fixture-item-qty');
            const priceText = await textOrNull(item, '.savv-fixture-item-price');
            const productUrl = await hrefOrNull(item, '.savv-fixture-item-link');
            const imageUrl = await hrefOrAttrOrNull(item, '.savv-fixture-item-image', 'src');

            items.push({
                title: cleanText(title),
                quantity: parseFixtureQuantity(qtyText),
                unit_price: parseFixtureAmount(priceText),
                line_total: parseFixtureAmount(priceText),
                product_image_url: sanitizeUrlOrNull(imageUrl, { isImage: true }),
                official_product_url: sanitizeUrlOrNull(productUrl),
            });
        }

        return items;
    },

    async extractShipments(page, orderEl) {
        const carrier = await textOrNull(orderEl, '.savv-fixture-shipment-carrier');
        const tracking = await textOrNull(orderEl, '.savv-fixture-shipment-tracking');
        const status = await textOrNull(orderEl, '.savv-fixture-status');

        if (isEmpty(carrier) && isEmpty(tracking) && isEmpty(status)) return [];

        return [{
            carrier: cleanText(carrier),
            tracking_number: cleanText(tracking),
            original_status: cleanText(status),
        }];
    },

    async extractReturns(page, orderEl) {
        const returnHandles = await orderEl.locator('.savv-fixture-return').all();
        const returns = [];

        for (const ret of returnHandles) {
            returns.push({
                provider_return_id: cleanText(await ret.getAttribute('data-savv-return-id'), 100),
                original_status: cleanText(await textOrNull(ret, '.savv-fixture-return-status')),
                requested_at: parseFixtureDate(await textOrNull(ret, '.savv-fixture-return-requested')),
            });
        }

        return returns;
    },

    async extractRefunds(page, orderEl) {
        const refundHandles = await orderEl.locator('.savv-fixture-refund').all();
        const refunds = [];

        for (const refund of refundHandles) {
            const amountText = await textOrNull(refund, '.savv-fixture-refund-amount');
            if (isEmpty(amountText)) continue;

            refunds.push({
                amount: parseFixtureAmount(amountText),
                currency: 'USD',
                original_status: cleanText(await textOrNull(refund, '.savv-fixture-refund-status')),
                initiated_at: parseFixtureDate(await textOrNull(refund, '.savv-fixture-refund-date')),
            });
        }

        return refunds;
    },

    normalize(raw) {
        return raw;
    },

    validate(order) {
        const errors = [];
        if (isEmpty(order.provider_order_id)) errors.push('missing provider_order_id');
        if (!Array.isArray(order.items) || order.items.length === 0) errors.push('no items extracted');

        return { valid: errors.length === 0, errors };
    },

    redact(order) {
        // Nothing sensitive is ever attached to the order object in the
        // first place - see the "Do not extract" list in docs/architecture.md.
        return order;
    },
};

/**
 * Real amazon.com "Your Orders" page extraction. See the module docblock for
 * which class names this relies on and why `.yohtmlc-recipient` (shipping
 * address) is deliberately never queried.
 *
 * Does the entire DOM read in one page.evaluate() call rather than many
 * individual Playwright locator round-trips - a real order-history page can
 * list many orders, and locator-per-field/per-row adds up to hundreds of
 * round-trips that can exceed the scan timeout. All the raw values coming
 * back are still untrusted and run through cleanText()/sanitizeUrlOrNull()
 * here in Node, exactly as the other paths do.
 */
async function extractRealOrders(page) {
    const rawOrders = await page.evaluate(scanRealAmazonOrders, MAX_ORDERS);
    const orders = [];

    for (const raw of rawOrders) {
        if (isEmpty(raw.orderIdText)) continue;

        const items = raw.items
            .filter((item) => !isEmpty(item.title))
            .slice(0, MAX_ITEMS_PER_ORDER)
            .map((item) => ({
                title: cleanText(item.title),
                // Amazon's order-history list only shows an explicit quantity
                // when > 1; not observed in the fixture this was built against.
                quantity: 1,
                unit_price: null,
                line_total: null,
                product_image_url: sanitizeUrlOrNull(item.imageUrl, { isImage: true }),
                official_product_url: sanitizeUrlOrNull(item.productUrl),
            }));

        if (items.length === 0) continue;

        const status = cleanText(raw.statusText);
        const invoiceUrl = sanitizeUrlOrNull(raw.invoiceUrl);

        orders.push({
            provider_order_id: cleanText(raw.orderIdText, 100),
            order_date: parseFixtureDate(raw.orderDateText),
            original_status: status,
            currency: 'USD',
            total: parseFixtureAmount(raw.totalText),
            official_order_url: sanitizeUrlOrNull(raw.orderUrl),
            observed_at: new Date().toISOString(),
            items,
            shipments: status ? [{ original_status: status }] : [],
            returns: [],
            refunds: [],
            invoices: invoiceUrl ? [{ official_invoice_url: invoiceUrl }] : [],
        });
    }

    return orders.slice(0, MAX_ORDERS);
}

/**
 * Runs entirely inside the page (via page.evaluate) - must be a pure,
 * self-contained function with no references to anything outside it.
 * Never reads `.yohtmlc-recipient` (the "Ship to" / shipping-address block).
 */
function scanRealAmazonOrders(maxOrders) {
    function fieldValue(card, labelText) {
        const rows = card.querySelectorAll('.order-header__header-list-item');

        for (const row of rows) {
            const label = row.querySelector('.a-size-mini');
            if (!label || label.textContent.trim().toLowerCase() !== labelText) continue;

            const valueRow = Array.from(row.querySelectorAll('.a-row'))
                .find((r) => !r.classList.contains('a-size-mini'));
            const valueSpan = valueRow ? valueRow.querySelector('.a-size-base') : null;

            return valueSpan ? valueSpan.textContent.trim() : null;
        }

        return null;
    }

    const cards = Array.from(document.querySelectorAll('.order-card.js-order-card')).slice(0, maxOrders);

    return cards.map((card) => {
        const orderIdEl = card.querySelector('.yohtmlc-order-id span[dir="ltr"]');
        const statusEl = card.querySelector('.yohtmlc-shipment-status-primaryText')
            ?? card.querySelector('.delivery-box__primary-text');
        const orderLinkEl = card.querySelector('.yohtmlc-order-level-connections a[href*="order-details"]');
        const invoiceLinkEl = card.querySelector('a[href*="/your-orders/invoice/"]');

        const items = Array.from(card.querySelectorAll('.item-box')).map((item) => {
            const titleEl = item.querySelector('.yohtmlc-product-title a');
            const img = item.querySelector('.product-image img');

            return {
                title: titleEl ? titleEl.textContent.trim() : null,
                productUrl: titleEl ? titleEl.getAttribute('href') : null,
                imageUrl: img ? (img.getAttribute('data-a-hires') ?? img.getAttribute('src')) : null,
            };
        });

        return {
            orderIdText: orderIdEl ? orderIdEl.textContent.trim() : null,
            orderDateText: fieldValue(card, 'order placed'),
            totalText: fieldValue(card, 'total'),
            statusText: statusEl ? statusEl.textContent.trim() : null,
            orderUrl: orderLinkEl ? orderLinkEl.getAttribute('href') : null,
            invoiceUrl: invoiceLinkEl ? invoiceLinkEl.getAttribute('href') : null,
            items,
        };
    });
}

async function textOrNull(scope, selector) {
    const el = scope.locator(selector).first();
    if ((await el.count()) === 0) return null;
    return (await el.textContent())?.trim() ?? null;
}

async function hrefOrNull(scope, selector) {
    return hrefOrAttrOrNull(scope, selector, 'href');
}

async function hrefOrAttrOrNull(scope, selector, attribute) {
    const el = scope.locator(selector).first();
    if ((await el.count()) === 0) return null;
    return el.getAttribute(attribute);
}

function parseFixtureAmount(text) {
    if (isEmpty(text)) return null;
    const cleaned = text.replace(/[^\d.]/g, '');
    return cleaned === '' ? null : cleaned;
}

function parseFixtureQuantity(text) {
    if (isEmpty(text)) return 1;
    const match = text.match(/(\d+)/);
    return match ? Number(match[1]) : 1;
}

function parseFixtureDate(text) {
    if (isEmpty(text)) return null;
    const parsed = new Date(text);
    return Number.isNaN(parsed.getTime()) ? null : parsed.toISOString();
}
