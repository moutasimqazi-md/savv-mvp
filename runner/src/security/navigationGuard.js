/**
 * Reviewed allowlist for browser navigation during an import session. This
 * is the runner's SSRF/navigation boundary: only these hosts (plus the
 * marketplace's own normal authentication hosts) may ever be navigated to
 * inside the isolated Chromium instance. Everything else - including
 * localhost, private/link-local ranges, cloud metadata endpoints, and
 * non-http(s) schemes - is blocked before navigation occurs.
 */

const ALLOWED_HOSTS_BY_PROVIDER = {
    amazon_in: [
        'www.amazon.in',
        'amazon.in',
        // Amazon's normal sign-in flow can redirect through this host.
        'www.amazon.com',
    ],
    flipkart: [
        'www.flipkart.com',
        'flipkart.com',
        // Flipkart's normal sign-in flow can redirect through this host.
        'accounts.flipkart.com',
    ],
};

const BLOCKED_SCHEMES = new Set(['file:', 'ftp:', 'data:', 'javascript:', 'chrome:', 'chrome-extension:', 'about:']);

function isPrivateOrReservedHost(hostname) {
    if (hostname === 'localhost' || hostname.endsWith('.localhost')) return true;

    // IPv4 literal checks: loopback, RFC1918, link-local, and the common
    // cloud metadata address.
    const ipv4 = hostname.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
    if (ipv4) {
        const [a, b] = ipv4.slice(1).map(Number);
        if (a === 127) return true; // 127.0.0.0/8
        if (a === 10) return true; // 10.0.0.0/8
        if (a === 172 && b >= 16 && b <= 31) return true; // 172.16.0.0/12
        if (a === 192 && b === 168) return true; // 192.168.0.0/16
        if (a === 169 && b === 254) return true; // 169.254.0.0/16 (link-local + cloud metadata)
        if (a === 0) return true;
        return false;
    }

    if (hostname === '::1' || hostname.startsWith('fe80:') || hostname.startsWith('fc') || hostname.startsWith('fd')) {
        return true;
    }

    return false;
}

export function isNavigationAllowed(urlString, provider) {
    let url;
    try {
        url = new URL(urlString);
    } catch {
        return false;
    }

    if (BLOCKED_SCHEMES.has(url.protocol)) return false;
    if (url.protocol !== 'https:' && url.protocol !== 'http:') return false;

    const hostname = url.hostname.toLowerCase();

    if (isPrivateOrReservedHost(hostname)) return false;

    const allowed = ALLOWED_HOSTS_BY_PROVIDER[provider] ?? [];

    return allowed.includes(hostname);
}

export function allowedHostsFor(provider) {
    return ALLOWED_HOSTS_BY_PROVIDER[provider] ?? [];
}
