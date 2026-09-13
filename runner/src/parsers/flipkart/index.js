/**
 * Flipkart parser.
 *
 * Has two extraction paths:
 *
 * 1. Fixture-based (`.savv-fixture-fk-*` markers) - built and tested only
 *    against the synthetic fixtures in /runner/fixtures/flipkart/*.html. It
 *    does NOT use real flipkart.com DOM selectors, because none have been
 *    supplied yet - see docs/parser-maintenance.md before extending this
 *    path with real selectors.
 * 2. Heuristic fallback (shared/heuristicExtract.js) - runs only when the
 *    fixture path finds nothing. Provider-agnostic pattern matching against
 *    whatever real page is open, deliberately narrow (only atomic fields
 *    like price/date/status/order-id/product-link, never a whole "order
 *    card" block of text) to avoid ever capturing an address or phone
 *    number that might sit in the same container. Lower precision than a
 *    fixture-verified parser - see docs/limitations.md.
 */
import { cleanText, sanitizeUrlOrNull, isEmpty, mainPageAttribute } from '../shared/sanitize.js';
import { findHeuristicCandidates, groupCandidatesIntoOrders, buildHeuristicOrders } from '../shared/heuristicExtract.js';

const PARSER_VERSION = 'flipkart@0.2.0-heuristic-fallback';
const MAX_ORDERS = 100;
const MAX_ITEMS_PER_ORDER = 100;

export const flipkartParser = {
    getVersion() {
        return PARSER_VERSION;
    },

    async supportsCurrentPage(page) {
        if ((await mainPageAttribute(page, 'data-savv-page')) === 'flipkart-orders') return true;
        if ((await page.locator('.savv-fixture-fk-order').count()) > 0) return true;

        const candidates = await findHeuristicCandidates(page);
        return groupCandidatesIntoOrders(candidates).length > 0;
    },

    async detectPageType(page) {
        return (await mainPageAttribute(page, 'data-savv-page')) ?? 'unknown';
    },

    async extractOrders(page) {
        const orderHandles = await page.locator('.savv-fixture-fk-order').all();
        const orders = [];

        for (const handle of orderHandles.slice(0, MAX_ORDERS)) {
            const providerOrderId = await handle.getAttribute('data-savv-order-id');
            if (isEmpty(providerOrderId)) continue;

            orders.push({
                provider_order_id: cleanText(providerOrderId, 100),
                order_date: parseFixtureDate(await textOrNull(handle, '.savv-fixture-fk-order-date')),
                original_status: cleanText(await textOrNull(handle, '.savv-fixture-fk-status')),
                currency: 'INR',
                total: parseFixtureAmount(await textOrNull(handle, '.savv-fixture-fk-order-total')),
                official_order_url: sanitizeUrlOrNull(await hrefOrNull(handle, '.savv-fixture-fk-order-link')),
                observed_at: new Date().toISOString(),
                items: await this.extractOrderItems(page, handle),
                shipments: await this.extractShipments(page, handle),
                returns: await this.extractReturns(page, handle),
                refunds: await this.extractRefunds(page, handle),
            });
        }

        if (orders.length > 0) return orders;

        // No fixture-marked orders on this page - fall back to the
        // provider-agnostic heuristic pass (see module docblock above).
        const candidates = await findHeuristicCandidates(page);
        const groups = groupCandidatesIntoOrders(candidates).slice(0, MAX_ORDERS);
        return buildHeuristicOrders(groups, { cleanText, sanitizeUrlOrNull, isEmpty });
    },

    async extractOrderItems(page, orderEl) {
        const itemHandles = await orderEl.locator('.savv-fixture-fk-item').all();
        const items = [];

        for (const item of itemHandles.slice(0, MAX_ITEMS_PER_ORDER)) {
            const title = await textOrNull(item, '.savv-fixture-fk-item-link');
            if (isEmpty(title)) continue;

            items.push({
                title: cleanText(title),
                quantity: parseFixtureQuantity(await textOrNull(item, '.savv-fixture-fk-item-qty')),
                unit_price: parseFixtureAmount(await textOrNull(item, '.savv-fixture-fk-item-price')),
                line_total: parseFixtureAmount(await textOrNull(item, '.savv-fixture-fk-item-price')),
                product_image_url: sanitizeUrlOrNull(await hrefOrAttrOrNull(item, '.savv-fixture-fk-item-image', 'src'), { isImage: true }),
                official_product_url: sanitizeUrlOrNull(await hrefOrNull(item, '.savv-fixture-fk-item-link')),
            });
        }

        return items;
    },

    async extractShipments(page, orderEl) {
        const carrier = await textOrNull(orderEl, '.savv-fixture-fk-shipment-carrier');
        const tracking = await textOrNull(orderEl, '.savv-fixture-fk-shipment-tracking');
        const status = await textOrNull(orderEl, '.savv-fixture-fk-status');

        if (isEmpty(carrier) && isEmpty(tracking) && isEmpty(status)) return [];

        return [{
            carrier: cleanText(carrier),
            tracking_number: cleanText(tracking),
            original_status: cleanText(status),
        }];
    },

    async extractReturns(page, orderEl) {
        const returnHandles = await orderEl.locator('.savv-fixture-fk-return').all();
        const returns = [];

        for (const ret of returnHandles) {
            returns.push({
                provider_return_id: cleanText(await ret.getAttribute('data-savv-return-id'), 100),
                original_status: cleanText(await textOrNull(ret, '.savv-fixture-fk-return-status')),
                requested_at: parseFixtureDate(await textOrNull(ret, '.savv-fixture-fk-return-requested')),
            });
        }

        return returns;
    },

    async extractRefunds(page, orderEl) {
        const refundHandles = await orderEl.locator('.savv-fixture-fk-refund').all();
        const refunds = [];

        for (const refund of refundHandles) {
            const amountText = await textOrNull(refund, '.savv-fixture-fk-refund-amount');
            if (isEmpty(amountText)) continue;

            refunds.push({
                amount: parseFixtureAmount(amountText),
                currency: 'INR',
                original_status: cleanText(await textOrNull(refund, '.savv-fixture-fk-refund-status')),
                initiated_at: parseFixtureDate(await textOrNull(refund, '.savv-fixture-fk-refund-date')),
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
        return order;
    },
};

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
