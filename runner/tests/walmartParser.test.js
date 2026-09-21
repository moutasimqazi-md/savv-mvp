import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { walmartParser } from '../src/parsers/walmart/index.js';

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

test('walmart parser supports its own synthetic fixtures', async () => {
    for (const name of ['order.html', 'delivered.html', 'cancelled.html', 'return.html', 'refund.html']) {
        await withPage(fixtureUrl('walmart', name), async (page) => {
            assert.equal(await walmartParser.supportsCurrentPage(page), true, `expected support for ${name}`);
        });
    }
});

test('walmart parser does not claim the unknown layout fixture', async () => {
    await withPage(fixtureUrl('unknown-layout.html'), async (page) => {
        assert.equal(await walmartParser.supportsCurrentPage(page), false);
    });
});

test('walmart fixture-tagged markup never appears on the amazon-us fixture', async () => {
    await withPage(fixtureUrl('amazon-us', 'order.html'), async (page) => {
        assert.equal(await page.locator('.savv-fixture-wm-order').count(), 0);
    });
});

test('walmart parser extracts a fully-formed synthetic return order', async () => {
    await withPage(fixtureUrl('walmart', 'return.html'), async (page) => {
        const orders = await walmartParser.extractOrders(page);
        assert.equal(orders.length, 1);

        const [order] = orders;
        assert.equal(order.provider_order_id, 'WM-SYNTH-0004');
        assert.equal(order.items.length, 1);
        assert.equal(order.items[0].title, 'Synthetic Desk Fan');
        assert.equal(order.returns.length, 1);
        assert.equal(order.returns[0].provider_return_id, 'WM-SYNTH-RET-0004');

        const { valid } = walmartParser.validate(order);
        assert.equal(valid, true);
    });
});

test('walmart parser extracts a fully-formed synthetic refund order', async () => {
    await withPage(fixtureUrl('walmart', 'refund.html'), async (page) => {
        const orders = await walmartParser.extractOrders(page);
        assert.equal(orders.length, 1);

        const [order] = orders;
        assert.equal(order.provider_order_id, 'WM-SYNTH-0005');
        assert.equal(order.refunds.length, 1);
        assert.equal(order.refunds[0].amount, '22.00');
    });
});

test('walmart extracted URLs are only ever https walmart.com/CDN links', async () => {
    await withPage(fixtureUrl('walmart', 'order.html'), async (page) => {
        const [order] = await walmartParser.extractOrders(page);
        assert.match(order.official_order_url, /^https:\/\/www\.walmart\.com\//);
        assert.match(order.items[0].official_product_url, /^https:\/\/www\.walmart\.com\//);
        assert.match(order.items[0].product_image_url, /^https:\/\/i5\.walmartimages\.com\//);
    });
});
