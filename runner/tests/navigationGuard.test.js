import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isNavigationAllowed } from '../src/security/navigationGuard.js';

test('allows the marketplace order-history host', () => {
    assert.equal(isNavigationAllowed('https://www.amazon.in/gp/css/order-history', 'amazon_in'), true);
    assert.equal(isNavigationAllowed('https://www.flipkart.com/account/orders', 'flipkart'), true);
});

test('blocks the wrong provider host', () => {
    assert.equal(isNavigationAllowed('https://www.flipkart.com/account/orders', 'amazon_in'), false);
});

test('blocks localhost and private/link-local ranges (SSRF)', () => {
    for (const url of [
        'https://localhost/admin',
        'http://127.0.0.1:8000/',
        'http://10.0.0.5/',
        'http://172.16.0.5/',
        'http://192.168.1.5/',
        'http://169.254.169.254/latest/meta-data/', // cloud metadata endpoint
    ]) {
        assert.equal(isNavigationAllowed(url, 'amazon_in'), false, `expected ${url} to be blocked`);
    }
});

test('blocks non-http(s) schemes', () => {
    for (const url of [
        'file:///etc/passwd',
        'javascript:alert(1)',
        'data:text/html,<script>alert(1)</script>',
        'ftp://www.amazon.in/',
        'chrome://settings',
    ]) {
        assert.equal(isNavigationAllowed(url, 'amazon_in'), false, `expected ${url} to be blocked`);
    }
});

test('blocks unrelated external websites', () => {
    assert.equal(isNavigationAllowed('https://evil.example.com/', 'amazon_in'), false);
});

test('rejects unparsable URLs safely', () => {
    assert.equal(isNavigationAllowed('not a url', 'amazon_in'), false);
});
