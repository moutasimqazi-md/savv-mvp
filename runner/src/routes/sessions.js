import { Router } from 'express';
import {
    createSession, getSessionStatus, scanSession, stopSession, getSessionHealth, registerViewToken,
} from '../sessionManager.js';

export const sessionsRouter = Router();

sessionsRouter.post('/', async (req, res) => {
    const { sessionId, provider } = req.body ?? {};

    if (typeof sessionId !== 'string' || !/^[A-Za-z0-9_-]{10,64}$/.test(sessionId)) {
        return res.status(400).json({ error: 'invalid_session_id' });
    }

    if (provider !== 'amazon_in' && provider !== 'flipkart') {
        return res.status(400).json({ error: 'invalid_provider' });
    }

    try {
        const result = await createSession(sessionId, provider);
        res.status(201).json(result);
    } catch (e) {
        res.status(500).json({ error: 'session_start_failed', message: safeMessage(e) });
    }
});

sessionsRouter.get('/:id', (req, res) => {
    res.json(getSessionStatus(req.params.id));
});

sessionsRouter.post('/:id/scan', async (req, res) => {
    try {
        const result = await scanSession(req.params.id);
        res.json(result);
    } catch (e) {
        res.status(404).json({ error: 'session_not_found', message: safeMessage(e) });
    }
});

sessionsRouter.post('/:id/view-token', async (req, res) => {
    const { token, ttlSeconds } = req.body ?? {};

    if (typeof token !== 'string' || !/^[A-Za-z0-9]{16,64}$/.test(token)) {
        return res.status(400).json({ error: 'invalid_token' });
    }

    try {
        const result = await registerViewToken(req.params.id, token, Number(ttlSeconds) || 60);
        res.json(result);
    } catch (e) {
        res.status(404).json({ error: 'session_not_found', message: safeMessage(e) });
    }
});

sessionsRouter.post('/:id/stop', async (req, res) => {
    const result = await stopSession(req.params.id);
    res.json(result);
});

sessionsRouter.get('/:id/health', (req, res) => {
    res.json(getSessionHealth(req.params.id));
});

function safeMessage(error) {
    // Never echo raw exception internals (paths, stack traces) back over
    // the wire, even on this loopback-only API.
    return error instanceof Error ? error.message.slice(0, 200) : 'unknown_error';
}
