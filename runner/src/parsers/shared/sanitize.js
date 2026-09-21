/**
 * Text/URL sanitization shared by every parser. Mirrors
 * backend/app/Services/TextSanitizer.php and
 * backend/app/Support/MarketplaceUrlValidator.php - Laravel re-validates
 * everything independently, but the runner never sends raw/untrusted
 * strings in the first place.
 */

const MAX_FIELD_LENGTH = 500;

export function cleanText(value, maxLength = MAX_FIELD_LENGTH) {
    if (value === null || value === undefined) return null;

    const normalized = String(value).normalize('NFC');
    const stripped = normalized.replace(/<[^>]*>/g, '');
    const collapsed = stripped.replace(/\s+/g, ' ').trim();

    return collapsed.slice(0, maxLength);
}

// Hosts a clickable link (order/product/invoice/billing) may point to -
// kept to the primary site domains only, since these are what the user's
// browser actually navigates to from "Open on Amazon" / "Manage on Claude"
// buttons.
const ALLOWED_LINK_HOSTS = new Set(['www.amazon.com', 'amazon.com', 'claude.ai', 'www.walmart.com', 'walmart.com']);

// Real product images are served from separate CDN subdomains, not the
// primary site - e.g. a real amazon.com order-history page's <img> src is
// m.media-amazon.com, never www.amazon.com. Images are never navigated to
// by clicking, so a slightly wider allowlist is safe here without
// loosening the link allowlist above.
const ALLOWED_IMAGE_HOSTS = new Set([
    ...ALLOWED_LINK_HOSTS,
    'm.media-amazon.com',
    'images-na.ssl-images-amazon.com',
    'images-eu.ssl-images-amazon.com',
    'images-fe.ssl-images-amazon.com',
    'i5.walmartimages.com',
]);

export function sanitizeUrlOrNull(value, { isImage = false } = {}) {
    if (!value || typeof value !== 'string') return null;
    if (value.length > 2048) return null;

    let url;
    try {
        url = new URL(value);
    } catch {
        return null;
    }

    if (url.protocol !== 'https:') return null;

    const hosts = isImage ? ALLOWED_IMAGE_HOSTS : ALLOWED_LINK_HOSTS;
    if (!hosts.has(url.hostname.toLowerCase())) return null;

    return url.toString();
}

export function isEmpty(value) {
    return value === null || value === undefined || String(value).trim() === '';
}

/**
 * Reads an attribute off the page's <main> element without Playwright's
 * default actionability wait - locator.getAttribute() on a locator that
 * matches zero elements waits up to its timeout (30s by default) hoping one
 * appears, which is exactly what happens on any real page that doesn't
 * have a single unambiguous <main> (most real marketplace pages). Checking
 * .count() first (which never waits) avoids that stall entirely.
 */
export async function mainPageAttribute(page, name) {
    const main = page.locator('main');
    if ((await main.count()) === 0) return null;

    return main.first().getAttribute(name, { timeout: 2000 }).catch(() => null);
}
