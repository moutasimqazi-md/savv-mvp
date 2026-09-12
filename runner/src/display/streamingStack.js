import { spawn } from 'node:child_process';

/**
 * Starts an isolated Xvfb display + x11vnc + websockify chain for one
 * session, on Linux only. Nginx proxies the resulting websocket at
 * /rbi/{sessionId} (see docs/deployment-ubuntu.md) - the VNC/websocket port
 * itself is bound to 127.0.0.1 and is never exposed publicly.
 *
 * Uses safe argument arrays throughout (no shell string concatenation with
 * user input) and never runs any of these processes as root.
 */
export function startStreamingStack({ displayNumber, wsPort }) {
    if (typeof process.getuid === 'function' && process.getuid() === 0) {
        throw new Error('Refusing to start the streaming stack as root.');
    }

    const displayArg = `:${displayNumber}`;
    // Each session needs its own VNC port, distinct from every other
    // concurrent session's - derive it from the (already-unique) display
    // number rather than relying on x11vnc's default (which collides).
    const vncPort = 5900 + displayNumber;

    const xvfb = spawn('Xvfb', [displayArg, '-screen', '0', '1280x800x24', '-nolisten', 'tcp'], {
        stdio: 'ignore',
    });

    const x11vnc = spawn('x11vnc', [
        '-display', displayArg,
        '-rfbport', String(vncPort),
        '-localhost',
        '-nopw',
        '-forever',
        '-shared',
        '-quiet',
    ], { stdio: 'ignore' });

    const websockify = spawn('websockify', [
        '--web', '/usr/share/novnc',
        `127.0.0.1:${wsPort}`,
        `localhost:${vncPort}`,
    ], { stdio: 'ignore' });

    const processes = [xvfb, x11vnc, websockify];

    return {
        displayEnv: displayArg,
        stop() {
            for (const p of processes) {
                if (!p.killed) p.kill('SIGTERM');
            }
        },
    };
}
