/**
 * Best-effort, provider-agnostic subscription detection for real billing
 * pages whose exact markup we have never seen (no sanitized fixture
 * available - see docs/parser-maintenance.md). Mirrors
 * heuristicExtract.js's approach for orders, adapted for a billing page:
 * looks for a currency amount near a billing-cycle word ("per month",
 * "/year", etc.) instead of near an order id/product link.
 *
 * Deliberately narrow: extracts only short, pattern-matched atomic fields
 * (a currency amount, a billing-cycle word, a plan-name-ish heading, a
 * renewal date) - never the raw text of a whole billing-panel container,
 * since a real billing page may show payment method details or an address
 * in the same block.
 */

const MAX_ELEMENTS_SCANNED = 5000;
const MAX_CANDIDATES = 10;

function scanPageForSubscriptionCandidates([maxElements, maxCandidates]) {
    const CURRENCY_RE = /(?:\$|₹|Rs\.?|INR|USD)\s?[\d,]+(?:\.\d{1,2})?/;
    const CYCLE_WORDS = [
        ['monthly', /per month|\/month|\/mo\b|monthly/i],
        ['yearly', /per year|\/year|\/yr\b|yearly|annual/i],
    ];
    const STATUS_WORDS = ['past due', 'payment failed', 'cancelled', 'canceled', 'expired', 'trial', 'active', 'current plan'];
    const DATE_RES = [
        /\b\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?,?\s+\d{4}\b/i,
        /\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4}\b/i,
        /\b\d{4}-\d{2}-\d{2}\b/,
    ];

    const all = document.querySelectorAll('body *');
    const candidates = [];

    for (let i = 0; i < all.length && i < maxElements; i++) {
        const el = all[i];
        if (el.childElementCount > 40) continue;

        const text = (el.innerText || '').trim();
        if (!text || text.length > 4000) continue;
        if (!CURRENCY_RE.test(text)) continue;

        const cycle = CYCLE_WORDS.find(([, re]) => re.test(text));
        if (!cycle) continue;

        candidates.push({ el, text, cycle: cycle[0] });
    }

    // Unlike the order heuristic (many repeating cards -> pick the
    // innermost match to avoid double-counting a card inside its own
    // list), a billing page has one subscription panel, and the price +
    // cycle text often sits together in a small leaf element while the
    // plan name is a sibling - "innermost" would pick that price-only
    // leaf and miss the name entirely. Pick the outermost match instead.
    const outermost = candidates.filter(
        (c) => !candidates.some((other) => other !== c && other.el.contains(c.el) && other.el !== c.el),
    );

    return outermost.slice(0, maxCandidates).map(({ text, cycle }) => {
        const priceMatch = text.match(CURRENCY_RE);
        const status = STATUS_WORDS.find((w) => text.toLowerCase().includes(w)) ?? null;

        let dateMatch = null;
        for (const re of DATE_RES) {
            dateMatch = text.match(re);
            if (dateMatch) break;
        }

        // Best-effort plan name: the first short line of text that isn't
        // itself the price/date/cycle noise - real plan names are short
        // ("Claude Pro", "Team plan"), so a short first line is a
        // reasonable, low-risk guess without capturing surrounding prose.
        const firstLine = text.split('\n').map((l) => l.trim()).find((l) => l.length > 0 && l.length <= 60) ?? null;

        return {
            planNameText: firstLine,
            priceText: priceMatch ? priceMatch[0] : null,
            billingCycle: cycle,
            statusText: status,
            renewalText: dateMatch ? dateMatch[0] : null,
        };
    });
}

/**
 * @param {import('playwright').Page} page
 * @returns {Promise<Array<object>>} raw, untrusted candidate rows - callers
 *   must still run every field through cleanText()/sanitizeUrlOrNull().
 */
export async function findSubscriptionCandidates(page) {
    return page.evaluate(scanPageForSubscriptionCandidates, [MAX_ELEMENTS_SCANNED, MAX_CANDIDATES]);
}
