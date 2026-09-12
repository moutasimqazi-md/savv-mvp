import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { amazonInParser } from '../src/parsers/amazon-in/index.js';
import { flipkartParser } from '../src/parsers/flipkart/index.js';

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

test('amazon-in parser supports its own synthetic fixtures', async () => {
    for (const name of ['order.html', 'delivered.html', 'cancelled.html', 'return.html', 'refund.html']) {
        await withPage(fixtureUrl('amazon-in', name), async (page) => {
            assert.equal(await amazonInParser.supportsCurrentPage(page), true, `expected support for ${name}`);
        });
    }
});

test('amazon-in parser does not claim flipkart or unknown fixtures', async () => {
    await withPage(fixtureUrl('flipkart', 'order.html'), async (page) => {
        assert.equal(await amazonInParser.supportsCurrentPage(page), false);
    });
    await withPage(fixtureUrl('unknown-layout.html'), async (page) => {
        assert.equal(await amazonInParser.supportsCurrentPage(page), false);
    });
});

test('flipkart parser supports its own synthetic fixtures', async () => {
    for (const name of ['order.html', 'delivered.html', 'cancelled.html', 'return.html', 'refund.html']) {
        await withPage(fixtureUrl('flipkart', name), async (page) => {
            assert.equal(await flipkartParser.supportsCurrentPage(page), true, `expected support for ${name}`);
        });
    }
});

test('flipkart parser does not claim amazon or unknown fixtures', async () => {
    await withPage(fixtureUrl('amazon-in', 'order.html'), async (page) => {
        assert.equal(await flipkartParser.supportsCurrentPage(page), false);
    });
    await withPage(fixtureUrl('unknown-layout.html'), async (page) => {
        assert.equal(await flipkartParser.supportsCurrentPage(page), false);
    });
});

test('amazon-in parser extracts a fully-formed synthetic order', async () => {
    await withPage(fixtureUrl('amazon-in', 'return.html'), async (page) => {
        const orders = await amazonInParser.extractOrders(page);
        assert.equal(orders.length, 1);

        const [order] = orders;
        assert.equal(order.provider_order_id, 'AMZ-SYNTH-0004');
        assert.equal(order.items.length, 1);
        assert.equal(order.items[0].title, 'Synthetic Desk Lamp');
        assert.equal(order.returns.length, 1);
        assert.equal(order.returns[0].provider_return_id, 'AMZ-SYNTH-RET-0004');

        const { valid } = amazonInParser.validate(order);
        assert.equal(valid, true);
    });
});

test('flipkart parser extracts a fully-formed synthetic refund order', async () => {
    await withPage(fixtureUrl('flipkart', 'refund.html'), async (page) => {
        const orders = await flipkartParser.extractOrders(page);
        assert.equal(orders.length, 1);

        const [order] = orders;
        assert.equal(order.provider_order_id, 'FK-SYNTH-0005');
        assert.equal(order.refunds.length, 1);
        assert.equal(order.refunds[0].amount, '399.00');

        const { valid } = flipkartParser.validate(order);
        assert.equal(valid, true);
    });
});

test('extracted URLs are only ever https marketplace links', async () => {
    await withPage(fixtureUrl('amazon-in', 'order.html'), async (page) => {
        const [order] = await amazonInParser.extractOrders(page);
        assert.match(order.official_order_url, /^https:\/\/www\.amazon\.in\//);
        assert.match(order.items[0].official_product_url, /^https:\/\/www\.amazon\.in\//);
    });
});
