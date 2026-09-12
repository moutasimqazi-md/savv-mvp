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

const ALLOWED_HOSTS = new Set(['www.amazon.in', 'amazon.in', 'www.flipkart.com', 'flipkart.com']);

export function sanitizeUrlOrNull(value) {
    if (!value || typeof value !== 'string') return null;
    if (value.length > 2048) return null;

    let url;
    try {
        url = new URL(value);
    } catch {
        return null;
    }

    if (url.protocol !== 'https:') return null;
    if (!ALLOWED_HOSTS.has(url.hostname.toLowerCase())) return null;

    return url.toString();
}

export function isEmpty(value) {
    return value === null || value === undefined || String(value).trim() === '';
}
