/**
 * Flipkart parser - PROVISIONAL / SYNTHETIC FIXTURES ONLY.
 *
 * Built only against the synthetic fixtures in /runner/fixtures/flipkart/*.html
 * using invented `.savv-fixture-fk-*` markers. It does NOT use real
 * flipkart.com DOM selectors, because none have been supplied yet. Before
 * pointing this at a real order-history page, provide sanitized HTML
 * fixtures captured from a page you are authorized to view, with all
 * personal data, order IDs, tracking numbers, cookies, and tokens removed
 * (see docs/parser-maintenance.md).
 */
import { cleanText, sanitizeUrlOrNull, isEmpty } from '../shared/sanitize.js';

const PARSER_VERSION = 'flipkart@0.1.0-synthetic';
const MAX_ORDERS = 100;
const MAX_ITEMS_PER_ORDER = 100;

export const flipkartParser = {
    getVersion() {
        return PARSER_VERSION;
    },

    async supportsCurrentPage(page) {
        return (await page.locator('main').getAttribute('data-savv-page')) === 'flipkart-orders'
            || (await page.locator('.savv-fixture-fk-order').count()) > 0;
    },

    async detectPageType(page) {
        const attr = await page.locator('main').getAttribute('data-savv-page').catch(() => null);
        return attr ?? 'unknown';
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

        return orders;
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
                product_image_url: sanitizeUrlOrNull(await item.locator('.savv-fixture-fk-item-image').getAttribute('src').catch(() => null)),
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
