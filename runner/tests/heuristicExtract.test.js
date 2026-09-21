import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { findHeuristicCandidates, groupCandidatesIntoOrders, buildHeuristicOrders } from '../src/parsers/shared/heuristicExtract.js';
import { cleanText, sanitizeUrlOrNull, isEmpty } from '../src/parsers/shared/sanitize.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixturePath = path.resolve(__dirname, '../fixtures/generic-order-card.html');

test('heuristic fallback extracts an order from a page with no known markers', async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        await page.goto(`file://${fixturePath}`);

        const candidates = await findHeuristicCandidates(page);
        assert.equal(candidates.length, 1);
        assert.equal(candidates[0].orderIdText, 'SYNTH-GEN-0001');
        assert.equal(candidates[0].statusPhrase, 'delivered');
        assert.equal(candidates[0].quantityText, '2');
        assert.match(candidates[0].dateText, /Jul 14, 2025/);

        const groups = groupCandidatesIntoOrders(candidates);
        assert.equal(groups.length, 1);

        const orders = buildHeuristicOrders(groups, { cleanText, sanitizeUrlOrNull, isEmpty });
        assert.equal(orders.length, 1);

        const [order] = orders;
        assert.equal(order.provider_order_id, 'SYNTH-GEN-0001');
        assert.equal(order.total, '1749.00');
        assert.equal(order.items.length, 1);
        assert.equal(order.items[0].title, 'Synthetic Generic Backpack');
        assert.equal(order.items[0].quantity, 2);
        assert.equal(order.items[0].official_product_url, 'https://www.amazon.com/synthetic-generic-item/p/SYNTHGEN0001');
    } finally {
        await browser.close();
    }
});

test('heuristic fallback drops a candidate with no order id and more than one match on the page', async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        await page.setContent(`
            <div class="card-a"><span>$500</span><span>Delivered</span><a href="https://www.amazon.com/dp/AAA111">Item A</a></div>
            <div class="card-b"><span>$700</span><span>Shipped</span><a href="https://www.amazon.com/dp/BBB222">Item B</a></div>
        `);

        const candidates = await findHeuristicCandidates(page);
        assert.equal(candidates.length, 2);

        // Neither candidate has an explicit order id, and there's more than
        // one of them, so grouping intentionally drops both rather than
        // fabricating unstable ids.
        const groups = groupCandidatesIntoOrders(candidates);
        assert.equal(groups.length, 0);
    } finally {
        await browser.close();
    }
});
