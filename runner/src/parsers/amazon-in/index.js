/**
 * Amazon India parser - PROVISIONAL / SYNTHETIC FIXTURES ONLY.
 *
 * This parser is intentionally built only against the synthetic fixtures in
 * /runner/fixtures/amazon-in/*.html, using invented `.savv-fixture-*`
 * markers. It does NOT use real amazon.in DOM selectors, because none have
 * been supplied yet. Before pointing this at a real order-history page,
 * provide sanitized HTML fixtures captured from a page you are authorized
 * to view, with all personal data, order IDs, tracking numbers, cookies,
 * and tokens removed (see docs/parser-maintenance.md).
 *
 * Once real fixtures arrive, extend the selector fallbacks below - keep the
 * `.savv-fixture-*` path working so the synthetic tests keep passing, and
 * add the real-page path alongside it.
 */
import { cleanText, sanitizeUrlOrNull, isEmpty } from '../shared/sanitize.js';

const PARSER_VERSION = 'amazon-in@0.1.0-synthetic';
const MAX_ORDERS = 100;
const MAX_ITEMS_PER_ORDER = 100;

export const amazonInParser = {
    getVersion() {
        return PARSER_VERSION;
    },

    async supportsCurrentPage(page) {
        return (await page.locator('main').getAttribute('data-savv-page')) === 'amazon-orders'
            || (await page.locator('.savv-fixture-order').count()) > 0;
    },

    async detectPageType(page) {
        const attr = await page.locator('main').getAttribute('data-savv-page').catch(() => null);
        return attr ?? 'unknown';
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
                currency: 'INR',
                total: parseFixtureAmount(totalText),
                official_order_url: sanitizeUrlOrNull(orderUrl),
                observed_at: new Date().toISOString(),
                items: await this.extractOrderItems(page, handle),
                shipments: await this.extractShipments(page, handle),
                returns: await this.extractReturns(page, handle),
                refunds: await this.extractRefunds(page, handle),
            });
        }

        return orders;
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
            const imageUrl = await item.locator('.savv-fixture-item-image').getAttribute('src').catch(() => null);

            items.push({
                title: cleanText(title),
                quantity: parseFixtureQuantity(qtyText),
                unit_price: parseFixtureAmount(priceText),
                line_total: parseFixtureAmount(priceText),
                product_image_url: sanitizeUrlOrNull(imageUrl),
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
                currency: 'INR',
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

async function textOrNull(scope, selector) {
    const el = scope.locator(selector).first();
    if ((await el.count()) === 0) return null;
    return (await el.textContent())?.trim() ?? null;
}

async function hrefOrNull(scope, selector) {
    const el = scope.locator(selector).first();
    if ((await el.count()) === 0) return null;
    return el.getAttribute('href');
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
