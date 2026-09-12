import 'dotenv/config';
import express from 'express';
import { verifySignatureMiddleware } from './security/verifySignature.js';
import { sessionsRouter } from './routes/sessions.js';

const HOST = process.env.RUNNER_HOST ?? '127.0.0.1';
const PORT = Number(process.env.RUNNER_PORT ?? 3010);
const SHARED_SECRET = process.env.RUNNER_SHARED_SECRET;
const MAX_SKEW_SECONDS = Number(process.env.RUNNER_MAX_TIMESTAMP_SKEW_SECONDS ?? 300);

if (!SHARED_SECRET) {
    console.error('RUNNER_SHARED_SECRET is not set. Refusing to start.');
    process.exit(1);
}

if (HOST !== '127.0.0.1' && HOST !== 'localhost') {
    console.error('RUNNER_HOST must be 127.0.0.1 (or localhost) - this API must never be exposed publicly.');
    process.exit(1);
}

const app = express();

app.use(express.json({
    limit: '256kb',
    verify: (req, _res, buf) => {
        req.rawBody = buf.toString('utf8');
    },
}));

app.use('/internal/sessions', verifySignatureMiddleware(SHARED_SECRET, MAX_SKEW_SECONDS), sessionsRouter);

app.use((err, _req, res, _next) => {
    console.error('Unhandled runner error:', err.message);
    res.status(500).json({ error: 'internal_error' });
});

app.listen(PORT, HOST, () => {
    console.log(`Savv runner listening on ${HOST}:${PORT} (internal only)`);
});
