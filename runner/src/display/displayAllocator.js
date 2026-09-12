/**
 * Allocates unique X display numbers for Xvfb-backed sessions on Linux.
 * Never reuses a display number while it's checked out.
 */
const base = Number(process.env.RUNNER_DISPLAY_BASE ?? 100);
const inUse = new Set();

export function allocateDisplay() {
    for (let n = base; n < base + 1000; n++) {
        if (!inUse.has(n)) {
            inUse.add(n);
            return n;
        }
    }
    throw new Error('No free display numbers available.');
}

export function releaseDisplay(displayNumber) {
    inUse.delete(displayNumber);
}
