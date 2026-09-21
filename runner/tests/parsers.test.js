import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { amazonUsParser } from '../src/parsers/amazon-us/index.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixturesDir = path.resolve(__dirname, '../fixtures');

function fixtureUrl(...parts) {
    return `file://${path.join(fixturesDir, ...parts)}`;
}

async function withPage(url, fn) {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        await page.goto(url);
        return await fn(page);
    } finally {
        await browser.close();
    }
}

test('amazon-us parser supports its own synthetic fixtures', async () => {
    for (const name of ['order.html', 'delivered.html', 'cancelled.html', 'return.html', 'refund.html']) {
        await withPage(fixtureUrl('amazon-us', name), async (page) => {
            assert.equal(await amazonUsParser.supportsCurrentPage(page), true, `expected support for ${name}`);
        });
    }
});

test('amazon-us parser does not claim the unknown layout fixture', async () => {
    // Note: supportsCurrentPage() also runs the provider-agnostic heuristic
    // fallback (see shared/heuristicExtract.js), which is content-based and
    // can in principle match generic order-like markup on *any* provider's
    // page - that's fine in production because sessionManager only ever
    // invokes a provider's parser after navigationGuard has already
    // confirmed the page is actually on that provider's host. What must
    // never cross-match is the fixture-tagged/real-selector path itself.
    await withPage(fixtureUrl('unknown-layout.html'), async (page) => {
        assert.equal(await amazonUsParser.supportsCurrentPage(page), false);
    });
});

test('amazon-us parser extracts a fully-formed synthetic order', async () => {
    await withPage(fixtureUrl('amazon-us', 'return.html'), async (page) => {
        const orders = await amazonUsParser.extractOrders(page);
        assert.equal(orders.length, 1);

        const [order] = orders;
        assert.equal(order.provider_order_id, 'AMZ-SYNTH-0004');
        assert.equal(order.items.length, 1);
        assert.equal(order.items[0].title, 'Synthetic Desk Lamp');
        assert.equal(order.returns.length, 1);
        assert.equal(order.returns[0].provider_return_id, 'AMZ-SYNTH-RET-0004');

        const { valid } = amazonUsParser.validate(order);
        assert.equal(valid, true);
    });
});

test('extracted URLs are only ever https marketplace links', async () => {
    await withPage(fixtureUrl('amazon-us', 'order.html'), async (page) => {
        const [order] = await amazonUsParser.extractOrders(page);
        assert.match(order.official_order_url, /^https:\/\/www\.amazon\.com\//);
        assert.match(order.items[0].official_product_url, /^https:\/\/www\.amazon\.com\//);
    });
});

test('amazon-us real-selector path extracts a fixture built on real order-card markup', async () => {
    await withPage(fixtureUrl('amazon-us', 'real-structure-delivered.html'), async (page) => {
        assert.equal(await amazonUsParser.supportsCurrentPage(page), true);

        const orders = await amazonUsParser.extractOrders(page);
        assert.equal(orders.length, 1);

        const [order] = orders;
        assert.equal(order.provider_order_id, '999-1112223-3334445');
        assert.match(order.original_status, /Delivered/);
        assert.equal(order.total, '2499.00');
        assert.equal(order.official_order_url, 'https://www.amazon.com/your-orders/order-details?orderID=999-1112223-3334445&ref=ppx_yo2ov_dt_b_fed_order_details');
        assert.equal(order.invoices.length, 1);
        assert.equal(order.invoices[0].official_invoice_url, 'https://www.amazon.com/your-orders/invoice/popover?orderId=999-1112223-3334445&ref_=fed_invoice_ajax');

        assert.equal(order.items.length, 1);
        assert.equal(order.items[0].title, 'Synthetic Desk Organizer');
        assert.equal(order.items[0].official_product_url, 'https://www.amazon.com/dp/SYNTHREAL001?ref=ppx_yo2ov_dt_b_fed_asin_title');
        assert.equal(order.items[0].product_image_url, 'https://m.media-amazon.com/images/I/synthetic-organizer-hires.jpg');

        const { valid } = amazonUsParser.validate(order);
        assert.equal(valid, true);
    });
});

test('amazon-us real-selector path never extracts the "Ship to" recipient block', async () => {
    await withPage(fixtureUrl('amazon-us', 'real-structure-delivered.html'), async (page) => {
        const [order] = await amazonUsParser.extractOrders(page);
        const serialized = JSON.stringify(order);

        assert.doesNotMatch(serialized, /Synthetic Test Recipient/);
        assert.doesNotMatch(serialized, /Invented Lane/);
        assert.doesNotMatch(serialized, /Faketown/);
    });
});

test('amazon-us real-selector path extracts many orders quickly (single page.evaluate scan)', async () => {
    await withPage(fixtureUrl('amazon-us', 'real-structure-many-orders.html'), async (page) => {
        const start = Date.now();
        const orders = await amazonUsParser.extractOrders(page);
        const elapsedMs = Date.now() - start;

        assert.equal(orders.length, 25);
        assert.equal(orders[0].provider_order_id, 'SYNTH-MANY-001');
        assert.equal(orders[24].provider_order_id, 'SYNTH-MANY-025');
        // A per-row Playwright locator round-trip approach took long enough
        // on a real page to exceed a 30s scan timeout; a single evaluate()
        // scan should finish in well under a second even for 25 orders.
        assert.ok(elapsedMs < 5000, `expected extraction to finish quickly, took ${elapsedMs}ms`);
    });
});
