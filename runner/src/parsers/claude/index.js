/**
 * Claude (claude.ai) subscription parser - PROVISIONAL / SYNTHETIC FIXTURES
 * ONLY.
 *
 * Unlike the amazon-us parser, this has no real-selector path: it has been
 * built only against the synthetic fixtures in /runner/fixtures/claude/*.html
 * (invented `.savv-fixture-*` markers, invented plan names and prices). It
 * does NOT use real claude.ai DOM selectors, because none have been
 * supplied - see docs/parser-maintenance.md before extending this with real
 * selectors, following the same sanitized-fixture process used for Amazon.
 *
 * Produces subscription records (extractSubscriptions), not orders - see
 * Savv\Enums\ProviderKind::Subscription on the Laravel side.
 */
import { cleanText, sanitizeUrlOrNull, isEmpty, mainPageAttribute } from '../shared/sanitize.js';
import { findSubscriptionCandidates } from '../shared/subscriptionHeuristic.js';

const PARSER_VERSION = 'claude@0.1.0-synthetic';
const MAX_SUBSCRIPTIONS = 10;

export const claudeParser = {
    getVersion() {
        return PARSER_VERSION;
    },

    async supportsCurrentPage(page) {
        if ((await mainPageAttribute(page, 'data-savv-page')) === 'claude-billing') return true;
        if ((await page.locator('.savv-fixture-subscription').count()) > 0) return true;

        const candidates = await findSubscriptionCandidates(page);
        return candidates.length > 0;
    },

    async detectPageType(page) {
        return (await mainPageAttribute(page, 'data-savv-page')) ?? 'unknown';
    },

    async extractSubscriptions(page) {
        const handles = await page.locator('.savv-fixture-subscription').all();
        const subscriptions = [];

        for (const handle of handles.slice(0, MAX_SUBSCRIPTIONS)) {
            const planName = await textOrNull(handle, '.savv-fixture-plan-name');
            if (isEmpty(planName)) continue;

            const priceText = await textOrNull(handle, '.savv-fixture-price');
            const statusText = await textOrNull(handle, '.savv-fixture-status');
            const billingCycleText = await textOrNull(handle, '.savv-fixture-billing-cycle');
            const renewalText = await textOrNull(handle, '.savv-fixture-renewal-date');
            const billingUrl = await hrefOrNull(handle, '.savv-fixture-billing-link');
            const subscriptionId = await handle.getAttribute('data-savv-subscription-id');

            subscriptions.push({
                provider_subscription_id: cleanText(subscriptionId, 100),
                plan_name: cleanText(planName, 255),
                original_status: cleanText(statusText),
                price: parseAmount(priceText),
                currency: 'USD',
                billing_cycle: normalizeCycle(billingCycleText),
                renewal_at: parseDate(renewalText),
                started_at: null,
                official_billing_url: sanitizeUrlOrNull(billingUrl),
                observed_at: new Date().toISOString(),
            });
        }

        if (subscriptions.length > 0) return subscriptions;

        // No fixture-marked subscription panel on this page - fall back to
        // the provider-agnostic subscription heuristic.
        const candidates = await findSubscriptionCandidates(page);

        return candidates
            .filter((c) => !isEmpty(c.planNameText) && !isEmpty(c.priceText))
            .slice(0, MAX_SUBSCRIPTIONS)
            .map((c) => ({
                provider_subscription_id: null,
                plan_name: cleanText(c.planNameText, 255),
                original_status: cleanText(c.statusText),
                price: parseAmount(c.priceText),
                currency: 'USD',
                billing_cycle: c.billingCycle ?? 'unknown',
                renewal_at: parseDate(c.renewalText),
                started_at: null,
                official_billing_url: null,
                observed_at: new Date().toISOString(),
            }));
    },

    normalize(raw) {
        return raw;
    },

    validate(subscription) {
        const errors = [];
        if (isEmpty(subscription.plan_name)) errors.push('missing plan_name');

        return { valid: errors.length === 0, errors };
    },

    redact(subscription) {
        // Nothing sensitive is ever attached in the first place - payment
        // method details and billing address are never queried.
        return subscription;
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

function parseAmount(text) {
    if (isEmpty(text)) return null;
    const cleaned = text.replace(/[^\d.]/g, '');
    return cleaned === '' ? null : cleaned;
}

function normalizeCycle(text) {
    if (isEmpty(text)) return 'unknown';
    const lower = text.toLowerCase();
    if (lower.includes('month')) return 'monthly';
    if (lower.includes('year') || lower.includes('annual')) return 'yearly';
    return 'unknown';
}

function parseDate(text) {
    if (isEmpty(text)) return null;
    const parsed = new Date(text);
    return Number.isNaN(parsed.getTime()) ? null : parsed.toISOString();
}
