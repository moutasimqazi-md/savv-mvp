import { test } from 'node:test';
import assert from 'node:assert/strict';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';
import { claudeParser } from '../src/parsers/claude/index.js';

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

test('claude parser supports its own synthetic fixtures', async () => {
    for (const name of ['subscription-active.html', 'subscription-cancelled.html']) {
        await withPage(fixtureUrl('claude', name), async (page) => {
            assert.equal(await claudeParser.supportsCurrentPage(page), true, `expected support for ${name}`);
        });
    }
});

test('claude parser does not claim the unknown layout fixture', async () => {
    await withPage(fixtureUrl('unknown-layout.html'), async (page) => {
        assert.equal(await claudeParser.supportsCurrentPage(page), false);
    });
});

test('claude parser extracts an active subscription', async () => {
    await withPage(fixtureUrl('claude', 'subscription-active.html'), async (page) => {
        const subscriptions = await claudeParser.extractSubscriptions(page);
        assert.equal(subscriptions.length, 1);

        const [sub] = subscriptions;
        assert.equal(sub.provider_subscription_id, 'SYNTH-SUB-0001');
        assert.equal(sub.plan_name, 'Claude Pro (Synthetic)');
        assert.equal(sub.original_status, 'Active');
        assert.equal(sub.price, '20.00');
        assert.equal(sub.billing_cycle, 'monthly');
        assert.equal(sub.official_billing_url, 'https://claude.ai/settings/billing');

        const { valid } = claudeParser.validate(sub);
        assert.equal(valid, true);
    });
});

test('claude parser extracts a cancelled subscription with its distinct status text', async () => {
    await withPage(fixtureUrl('claude', 'subscription-cancelled.html'), async (page) => {
        const [sub] = await claudeParser.extractSubscriptions(page);
        assert.match(sub.original_status, /Cancelled/);
    });
});

test('claude parser official_billing_url is only ever an https claude.ai link', async () => {
    await withPage(fixtureUrl('claude', 'subscription-active.html'), async (page) => {
        const [sub] = await claudeParser.extractSubscriptions(page);
        assert.match(sub.official_billing_url, /^https:\/\/claude\.ai\//);
    });
});
