import crypto from 'node:crypto';

/**
 * Verifies signed internal requests from Laravel. Must stay in lock-step
 * with the string-to-sign format in backend/app/Support/HmacSigner.php:
 *
 *   METHOD "\n" path "\n" timestamp "\n" nonce "\n" sha256(body)
 *
 * Rejects: missing/malformed headers, timestamp outside the allowed skew,
 * a body hash that doesn't match the actual body, a signature that doesn't
 * match (constant-time compare), and a nonce that has already been used.
 */

const usedNonces = new Map(); // nonce -> expiryEpochSeconds

function pruneExpiredNonces(nowSeconds) {
    for (const [nonce, expiry] of usedNonces) {
        if (expiry < nowSeconds) usedNonces.delete(nonce);
    }
}

export function stringToSign(method, path, timestamp, nonce, bodyHash) {
    return [method.toUpperCase(), path, timestamp, nonce, bodyHash].join('\n');
}

export function verifySignatureMiddleware(sharedSecret, maxSkewSeconds) {
    return (req, res, next) => {
        const timestamp = req.header('X-Savv-Timestamp');
        const nonce = req.header('X-Savv-Nonce');
        const bodyHash = req.header('X-Savv-Body-Hash');
        const signature = req.header('X-Savv-Signature');

        if (!timestamp || !nonce || !bodyHash || !signature) {
            return res.status(401).json({ error: 'missing_signature_headers' });
        }

        const nowSeconds = Math.floor(Date.now() / 1000);
        const tsNumber = Number(timestamp);

        if (!Number.isFinite(tsNumber) || Math.abs(nowSeconds - tsNumber) > maxSkewSeconds) {
            return res.status(401).json({ error: 'timestamp_out_of_range' });
        }

        pruneExpiredNonces(nowSeconds);

        if (usedNonces.has(nonce)) {
            return res.status(401).json({ error: 'nonce_replayed' });
        }

        const rawBody = req.rawBody ?? '';
        const actualBodyHash = crypto.createHash('sha256').update(rawBody).digest('hex');

        if (!timingSafeEqualStrings(actualBodyHash, bodyHash)) {
            return res.status(401).json({ error: 'body_hash_mismatch' });
        }

        const expectedSignature = crypto
            .createHmac('sha256', sharedSecret)
            .update(stringToSign(req.method, req.path, timestamp, nonce, bodyHash))
            .digest('hex');

        if (!timingSafeEqualStrings(expectedSignature, signature)) {
            return res.status(401).json({ error: 'invalid_signature' });
        }

        usedNonces.set(nonce, nowSeconds + maxSkewSeconds);

        next();
    };
}

function timingSafeEqualStrings(a, b) {
    const bufferA = Buffer.from(a, 'utf8');
    const bufferB = Buffer.from(b, 'utf8');

    if (bufferA.length !== bufferB.length) {
        // Still compare against something of matching length so the
        // early return doesn't leak a fast-fail via timing in practice.
        crypto.timingSafeEqual(bufferA, Buffer.alloc(bufferA.length));
        return false;
    }

    return crypto.timingSafeEqual(bufferA, bufferB);
}
