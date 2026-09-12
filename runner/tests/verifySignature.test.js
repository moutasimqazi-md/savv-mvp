import { test } from 'node:test';
import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import { verifySignatureMiddleware, stringToSign } from '../src/security/verifySignature.js';

const SECRET = 'test-shared-secret';

function sign({ method = 'POST', path = '/internal/sessions', timestamp, nonce, body = '' }) {
    const ts = timestamp ?? String(Math.floor(Date.now() / 1000));
    const n = nonce ?? crypto.randomUUID();
    const bodyHash = crypto.createHash('sha256').update(body).digest('hex');
    const signature = crypto.createHmac('sha256', SECRET).update(stringToSign(method, path, ts, n, bodyHash)).digest('hex');

    return {
        headers: {
            'X-Savv-Timestamp': ts,
            'X-Savv-Nonce': n,
            'X-Savv-Body-Hash': bodyHash,
            'X-Savv-Signature': signature,
        },
        rawBody: body,
        method,
        path,
    };
}

function fakeReqRes({ headers, rawBody, method, path }) {
    const req = {
        method,
        path,
        rawBody,
        header: (name) => headers[name],
    };

    let statusCode = null;
    let jsonBody = null;
    const res = {
        status(code) {
            statusCode = code;
            return this;
        },
        json(body) {
            jsonBody = body;
            return this;
        },
    };

    return { req, res, getStatus: () => statusCode, getJson: () => jsonBody };
}

test('accepts a correctly signed request', () => {
    const middleware = verifySignatureMiddleware(SECRET, 300);
    const { req, res, getStatus } = fakeReqRes(sign({}));

    let nextCalled = false;
    middleware(req, res, () => { nextCalled = true; });

    assert.equal(nextCalled, true);
    assert.equal(getStatus(), null);
});

test('rejects a request signed with the wrong secret', () => {
    const middleware = verifySignatureMiddleware(SECRET, 300);
    const signed = sign({});
    signed.headers['X-Savv-Signature'] = 'a'.repeat(64);
    const { req, res, getStatus } = fakeReqRes(signed);

    let nextCalled = false;
    middleware(req, res, () => { nextCalled = true; });

    assert.equal(nextCalled, false);
    assert.equal(getStatus(), 401);
});

test('rejects a stale timestamp outside the allowed skew', () => {
    const middleware = verifySignatureMiddleware(SECRET, 300);
    const staleTimestamp = String(Math.floor(Date.now() / 1000) - 3600);
    const { req, res, getStatus } = fakeReqRes(sign({ timestamp: staleTimestamp }));

    middleware(req, res, () => {});

    assert.equal(getStatus(), 401);
});

test('rejects a replayed nonce on the second use', () => {
    const middleware = verifySignatureMiddleware(SECRET, 300);
    const signed = sign({ nonce: 'fixed-nonce-for-replay-test' });

    const first = fakeReqRes(signed);
    middleware(first.req, first.res, () => {});
    assert.equal(first.getStatus(), null);

    const second = fakeReqRes(signed);
    middleware(second.req, second.res, () => {});
    assert.equal(second.getStatus(), 401);
});

test('rejects a body that does not match the signed body hash', () => {
    const middleware = verifySignatureMiddleware(SECRET, 300);
    const signed = sign({ body: '{"a":1}' });
    signed.rawBody = '{"a":2}'; // tampered after signing

    const { req, res, getStatus } = fakeReqRes(signed);
    middleware(req, res, () => {});

    assert.equal(getStatus(), 401);
});

test('rejects a request missing signature headers', () => {
    const middleware = verifySignatureMiddleware(SECRET, 300);
    const { req, res, getStatus } = fakeReqRes({ headers: {}, rawBody: '', method: 'GET', path: '/internal/sessions/x' });

    middleware(req, res, () => {});

    assert.equal(getStatus(), 401);
});
