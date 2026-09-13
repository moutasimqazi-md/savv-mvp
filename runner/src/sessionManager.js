import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';
import { allocateDisplay, releaseDisplay } from './display/displayAllocator.js';
import { startStreamingStack, writeVncToken, removeVncToken } from './display/streamingStack.js';
import { isNavigationAllowed, allowedHostsFor } from './security/navigationGuard.js';
import { parserFor } from './parsers/registry.js';

const HEADED = process.env.RUNNER_HEADED !== 'false';
const PROFILE_ROOT = process.env.RUNNER_PROFILE_ROOT ?? './tmp/profiles';
const MAX_LIFETIME_MS = Number(process.env.RUNNER_MAX_LIFETIME_MINUTES ?? 15) * 60_000;
const MAX_PAGES_PER_SCAN = Number(process.env.RUNNER_MAX_PAGES_PER_SCAN ?? 5);
const PAGE_NAV_DELAY_MS = Number(process.env.RUNNER_PAGE_NAVIGATION_DELAY_MS ?? 1500);

const HOME_URL_BY_PROVIDER = {
    amazon_in: 'https://www.amazon.in/gp/css/order-history',
    flipkart: 'https://www.flipkart.com/account/orders',
};

/** @type {Map<string, object>} sessionId -> session record */
const sessions = new Map();

function assertNotRoot() {
    if (typeof process.getuid === 'function' && process.getuid() === 0) {
        throw new Error('Refusing to launch Chromium as root.');
    }
}

export async function createSession(sessionId, provider) {
    assertNotRoot();

    if (sessions.has(sessionId)) {
        throw new Error(`Session ${sessionId} already exists.`);
    }

    const profileDir = path.resolve(PROFILE_ROOT, sessionId);
    await fs.mkdir(profileDir, { recursive: true });

    let displayNumber = null;
    let streamingStack = null;
    const launchEnv = { ...process.env };

    if (!HEADED) {
        displayNumber = allocateDisplay();
        streamingStack = startStreamingStack({ displayNumber });
        launchEnv.DISPLAY = `:${displayNumber}`;
    }

    const context = await chromium.launchPersistentContext(profileDir, {
        headless: false, // always a real, visible window - headed locally, headed-on-Xvfb in the demo deployment
        env: launchEnv,
        args: ['--no-first-run', '--no-default-browser-check'],
    });

    const record = {
        id: sessionId,
        provider,
        profileDir,
        displayNumber,
        streamingStack,
        context,
        page: null,
        status: 'starting',
        createdAt: Date.now(),
        lastActivityAt: Date.now(),
        lifetimeTimer: null,
        activeViewToken: null,
        viewTokenExpiryTimer: null,
    };

    record.lifetimeTimer = setTimeout(() => {
        stopSession(sessionId).catch(() => {});
    }, MAX_LIFETIME_MS);

    sessions.set(sessionId, record);

    try {
        record.page = await context.newPage();
        await record.page.goto(HOME_URL_BY_PROVIDER[provider], { waitUntil: 'domcontentloaded' });
        record.status = 'ready';
    } catch {
        record.status = 'failed';
    }

    return {
        processRef: sessionId,
        displayRef: displayNumber !== null ? `:${displayNumber}` : 'headed-local',
        status: record.status,
    };
}

export function getSessionStatus(sessionId) {
    const record = sessions.get(sessionId);
    if (!record) return { status: 'not_found' };

    return { status: record.status };
}

export async function scanSession(sessionId) {
    const record = requireSession(sessionId);
    record.lastActivityAt = Date.now();
    record.status = 'scanning';

    const currentUrl = record.page.url();

    if (!isNavigationAllowed(currentUrl, record.provider)) {
        record.status = 'failed';
        return { error: 'unsupported_host', orders: [] };
    }

    const parser = parserFor(record.provider);

    if (!(await parser.supportsCurrentPage(record.page))) {
        record.status = 'failed';
        return {
            error: 'unsupported_layout',
            message: 'Savv Companion could not safely read this page version. No account credentials or page contents were uploaded.',
            orders: [],
        };
    }

    const orders = [];
    let pagesVisited = 0;

    while (pagesVisited < MAX_PAGES_PER_SCAN) {
        pagesVisited++;

        const pageOrders = await parser.extractOrders(record.page);
        for (const order of pageOrders) {
            const { valid } = parser.validate(order);
            if (valid) orders.push(parser.redact(order));
        }

        // .count() first: locator.getAttribute() on a locator matching zero
        // elements waits up to its default 30s timeout hoping one appears -
        // which is exactly what happens here on every real page, since
        // [data-savv-next-page] is our own synthetic attribute that a real
        // marketplace page never has. That 30s stall was silently eating
        // the entire scan timeout budget on every scan.
        const nextPageLink = record.page.locator('[data-savv-next-page]').first();
        const nextPageHref = (await nextPageLink.count()) > 0
            ? await nextPageLink.getAttribute('href', { timeout: 2000 }).catch(() => null)
            : null;

        if (!nextPageHref || !isNavigationAllowed(nextPageHref, record.provider)) break;

        await new Promise((resolve) => setTimeout(resolve, PAGE_NAV_DELAY_MS));

        try {
            await record.page.goto(nextPageHref, { waitUntil: 'domcontentloaded' });
        } catch {
            break;
        }
    }

    record.status = 'ready';

    return { orders, parserVersion: parser.getVersion() };
}

/**
 * Registers this session's view token with the shared websockify broker
 * (see docs/deployment-ubuntu.md) so a viewer holding it can connect to
 * this session's VNC port for `ttlSeconds`. No-op in headed-local mode,
 * where there is no streaming stack to connect to.
 */
export async function registerViewToken(sessionId, token, ttlSeconds) {
    const record = requireSession(sessionId);

    if (!record.streamingStack) {
        return { registered: false };
    }

    if (record.activeViewToken) {
        await removeVncToken(record.activeViewToken).catch(() => {});
        clearTimeout(record.viewTokenExpiryTimer);
    }

    await writeVncToken(token, record.streamingStack.vncPort);
    record.activeViewToken = token;
    record.viewTokenExpiryTimer = setTimeout(() => {
        removeVncToken(token).catch(() => {});
        if (record.activeViewToken === token) record.activeViewToken = null;
    }, ttlSeconds * 1000);

    return { registered: true };
}

export async function stopSession(sessionId) {
    const record = sessions.get(sessionId);
    if (!record) return { status: 'not_found' };

    clearTimeout(record.lifetimeTimer);
    clearTimeout(record.viewTokenExpiryTimer);
    if (record.activeViewToken) {
        await removeVncToken(record.activeViewToken).catch(() => {});
    }

    try {
        await record.context.close();
    } catch {
        // already closed
    }

    record.streamingStack?.stop();

    if (record.displayNumber !== null) {
        releaseDisplay(record.displayNumber);
    }

    await fs.rm(record.profileDir, { recursive: true, force: true });

    sessions.delete(sessionId);

    return { status: 'terminated' };
}

export function getSessionHealth(sessionId) {
    const record = sessions.get(sessionId);
    if (!record) return { ok: false, status: 'not_found' };

    return {
        ok: true,
        status: record.status,
        uptimeMs: Date.now() - record.createdAt,
        idleMs: Date.now() - record.lastActivityAt,
    };
}

function requireSession(sessionId) {
    const record = sessions.get(sessionId);
    if (!record) throw new Error(`Session ${sessionId} not found.`);
    return record;
}

export function allowedHostsForProvider(provider) {
    return allowedHostsFor(provider);
}
