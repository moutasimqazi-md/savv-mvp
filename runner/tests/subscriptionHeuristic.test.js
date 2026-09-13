import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { findSubscriptionCandidates } from '../src/parsers/shared/subscriptionHeuristic.js';
import { claudeParser } from '../src/parsers/claude/index.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const fixturePath = path.resolve(__dirname, '../fixtures/generic-subscription-card.html');

test('subscription heuristic extracts a plan from a page with no known markers', async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        await page.goto(`file://${fixturePath}`);

        const candidates = await findSubscriptionCandidates(page);
        assert.equal(candidates.length, 1);

        const [candidate] = candidates;
        assert.equal(candidate.planNameText, 'Synthetic Pro Plan');
        assert.equal(candidate.billingCycle, 'monthly');
        assert.equal(candidate.statusText, 'active');
        assert.match(candidate.priceText, /15\.00/);
    } finally {
        await browser.close();
    }
});

test('claude parser falls back to the heuristic on an unrecognized billing page', async () => {
    const browser = await chromium.launch();
    try {
        const page = await browser.newPage();
        await page.goto(`file://${fixturePath}`);

        assert.equal(await claudeParser.supportsCurrentPage(page), true);

        const subscriptions = await claudeParser.extractSubscriptions(page);
        assert.equal(subscriptions.length, 1);
        assert.equal(subscriptions[0].plan_name, 'Synthetic Pro Plan');
        assert.equal(subscriptions[0].billing_cycle, 'monthly');
    } finally {
        await browser.close();
    }
});
