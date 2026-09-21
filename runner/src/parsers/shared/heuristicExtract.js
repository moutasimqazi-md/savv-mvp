/**
 * Best-effort, provider-agnostic order detection for real marketplace pages
 * whose exact markup we have never seen (no sanitized fixture available -
 * see docs/parser-maintenance.md). Used only as a fallback when a parser's
 * fixture-based selectors find nothing.
 *
 * Deliberately narrow: it extracts only short, pattern-matched atomic
 * fields (a currency amount, a date, one of a fixed whitelist of status
 * phrases, a product link, a quantity, an order id) - never the raw text of
 * a whole "order card" container. Real order-history cards often show a
 * shipping address or phone number in the same block as the price/date, so
 * grabbing "everything in the container" would risk capturing exactly the
 * personal fields this project must never extract. Matching narrow,
 * specific patterns instead keeps that risk out by construction.
 *
 * Trade-off versus a fixture-verified parser: lower precision. A candidate
 * without an explicit, labeled order id is skipped entirely rather than
 * given a synthetic id, because a synthetic id would drift between scans
 * and create duplicate orders instead of updating the same one - under-
 * extracting is preferred here to producing unstable data.
 */

const MAX_ELEMENTS_SCANNED = 5000;
const MAX_CANDIDATES = 100;

/**
 * Runs entirely inside the page (via page.evaluate) - must be a pure,
 * self-contained function with no references to anything outside it.
 */
function scanPageForOrderCandidates([maxElements, maxCandidates]) {
    const CURRENCY_RE = /(?:₹|Rs\.?|INR|\$|USD)\s?[\d,]+(?:\.\d{1,2})?/;
    const DATE_RES = [
        // "19 August 2026" (Amazon)
        /\b\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?,?\s+\d{4}\b/i,
        // "Jul 14, 2025" / "Nov 06, 2025" (Flipkart)
        /\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2},?\s+\d{4}\b/i,
        /\b\d{4}-\d{2}-\d{2}\b/,
        /\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/,
        // "Delivered on May 27" (Flipkart, recent orders show no year - the
        // browser's Date parser assumes the current year, which is right
        // often enough for a "recently delivered" label but not guaranteed).
        /\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\.?\s+\d{1,2}\b/i,
    ];
    const STATUS_PHRASES = [
        'return rejected', 'return denied', 'return not approved',
        'return completed', 'return closed', 'item returned',
        'return received', 'received at warehouse', 'received by seller',
        'picked up', 'pickup completed', 'return picked',
        'pickup scheduled', 'pickup arranged',
        'return approved', 'return authorized',
        'return requested', 'return initiated', 'replacement requested',
        'refund completed', 'refunded', 'refund successful',
        'refund initiated', 'refund in progress', 'refund processing',
        'cancelled', 'canceled',
        'out for delivery',
        'delivered',
        'shipped', 'dispatched', 'on the way',
        'packed', 'ready to ship',
        'processing', 'preparing your order',
        'confirmed', 'order confirmed',
        'order placed', 'placed',
    ];
    const PRODUCT_LINK_SELECTOR = 'a[href*="/dp/"], a[href*="/gp/product/"], a[href*="/p/"], a[href*="/product/"]';
    const ORDER_ID_RE = /\bOrder\s*(?:#|ID|No\.?)?\s*[:\-]?\s*([A-Za-z0-9][A-Za-z0-9\-]{5,29})\b/i;
    const QTY_RE = /\bQ(?:t)?y\.?:?\s*(\d{1,3})\b/i;
    const TRACKING_RE = /\b(?:Tracking\s*(?:ID|Number|No\.?)?|AWB\s*(?:No\.?)?)\s*[:\-]?\s*([A-Za-z0-9]{6,30})\b/i;

    const all = document.querySelectorAll('body *');
    const candidates = [];

    for (let i = 0; i < all.length && i < maxElements; i++) {
        const el = all[i];
        if (el.childElementCount > 40) continue;

        const text = (el.innerText || '').trim();
        if (!text || text.length > 4000) continue;
        if (!CURRENCY_RE.test(text)) continue;

        const lower = text.toLowerCase();
        const status = STATUS_PHRASES.find((p) => lower.includes(p)) ?? null;
        const link = el.querySelector(PRODUCT_LINK_SELECTOR);

        if (!status && !link) continue;

        candidates.push({ el, text, status, link });
    }

    // Keep only the innermost matches - drop any candidate that is an
    // ancestor of another candidate, so a large wrapper around several
    // real order rows doesn't also get counted as its own "order."
    const innermost = candidates.filter(
        (c) => !candidates.some((other) => other !== c && c.el.contains(other.el) && c.el !== other.el),
    );

    return innermost.slice(0, maxCandidates).map(({ text, status, link }) => {
        const priceMatch = text.match(CURRENCY_RE);
        let dateMatch = null;
        for (const re of DATE_RES) {
            dateMatch = text.match(re);
            if (dateMatch) break;
        }
        const orderIdMatch = text.match(ORDER_ID_RE);
        const qtyMatch = text.match(QTY_RE);
        const trackingMatch = text.match(TRACKING_RE);
        const img = link ? link.querySelector('img') ?? link.closest('*')?.querySelector('img') : null;

        return {
            orderIdText: orderIdMatch ? orderIdMatch[1] : null,
            priceText: priceMatch ? priceMatch[0] : null,
            dateText: dateMatch ? dateMatch[0] : null,
            statusPhrase: status,
            quantityText: qtyMatch ? qtyMatch[1] : null,
            trackingText: trackingMatch ? trackingMatch[1] : null,
            linkHref: link ? link.getAttribute('href') : null,
            linkText: link ? (link.innerText || link.textContent || '').trim() : null,
            imageSrc: img ? img.getAttribute('src') : null,
        };
    });
}

/**
 * @param {import('playwright').Page} page
 * @returns {Promise<Array<object>>} raw, untrusted candidate rows - callers
 *   must still run every field through cleanText()/sanitizeUrlOrNull().
 */
export async function findHeuristicCandidates(page) {
    return page.evaluate(scanPageForOrderCandidates, [MAX_ELEMENTS_SCANNED, MAX_CANDIDATES]);
}

/**
 * Groups raw candidates into order-shaped records. A candidate without an
 * explicit order id is dropped (see module docblock for why), unless it's
 * the only candidate found on the whole page and clearly reads as a single
 * order - in that case a stable id is derived from its own link, which
 * stays constant across scans of the same order.
 */
export function groupCandidatesIntoOrders(candidates) {
    const byOrderId = new Map();
    const singleton = [];

    for (const candidate of candidates) {
        if (candidate.orderIdText) {
            const key = candidate.orderIdText.toUpperCase();
            if (!byOrderId.has(key)) byOrderId.set(key, { orderIdText: candidate.orderIdText, items: [] });
            byOrderId.get(key).items.push(candidate);
        } else {
            singleton.push(candidate);
        }
    }

    const groups = [...byOrderId.values()];

    if (groups.length === 0 && singleton.length === 1 && singleton[0].linkHref) {
        groups.push({ orderIdText: null, items: singleton, fallbackKey: singleton[0].linkHref });
    }

    return groups;
}

/**
 * Turns grouped heuristic candidates into order objects in the same shape
 * the fixture-based parsers produce, running every field through
 * cleanText()/sanitizeUrlOrNull() - these came from an untrusted real page,
 * not a controlled fixture.
 */
export function buildHeuristicOrders(groups, { cleanText, sanitizeUrlOrNull, isEmpty }) {
    const orders = [];

    for (const group of groups) {
        const providerOrderId = group.orderIdText ?? group.fallbackKey;
        if (isEmpty(providerOrderId)) continue;

        const first = group.items[0];
        const items = group.items
            .filter((c) => !isEmpty(c.linkText))
            .map((c) => ({
                title: cleanText(c.linkText),
                quantity: c.quantityText ? Math.max(1, Number(c.quantityText)) : 1,
                unit_price: parseAmount(c.priceText),
                line_total: parseAmount(c.priceText),
                product_image_url: sanitizeUrlOrNull(c.imageSrc, { isImage: true }),
                official_product_url: sanitizeUrlOrNull(c.linkHref),
            }));

        if (items.length === 0) continue;

        const shipments = [];
        const trackingCandidate = group.items.find((c) => c.trackingText);
        if (trackingCandidate || first.statusPhrase) {
            shipments.push({
                tracking_number: trackingCandidate ? cleanText(trackingCandidate.trackingText, 64) : null,
                original_status: first.statusPhrase ? cleanText(first.statusPhrase) : null,
            });
        }

        orders.push({
            provider_order_id: cleanText(String(providerOrderId), 100),
            order_date: parseHeuristicDate(first.dateText),
            original_status: first.statusPhrase ? cleanText(first.statusPhrase) : null,
            currency: parseCurrency(first.priceText),
            total: parseAmount(first.priceText),
            official_order_url: null,
            observed_at: new Date().toISOString(),
            items,
            shipments,
            returns: [],
            refunds: [],
        });
    }

    return orders;
}

function parseAmount(text) {
    if (!text) return null;
    const cleaned = text.replace(/[^\d.]/g, '');
    return cleaned === '' ? null : cleaned;
}

/**
 * Derives an ISO currency code from whichever symbol/code the shared
 * CURRENCY_RE matched, rather than assuming one marketplace's currency -
 * this heuristic fallback is used by every "orders" provider, not just one
 * region's Amazon.
 */
function parseCurrency(text) {
    if (!text) return 'USD';
    if (/[₹]|Rs\.?|INR/i.test(text)) return 'INR';
    return 'USD';
}

function parseHeuristicDate(text) {
    if (!text) return null;
    const parsed = new Date(text);
    return Number.isNaN(parsed.getTime()) ? null : parsed.toISOString();
}
