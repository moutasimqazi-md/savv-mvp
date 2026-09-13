import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { writeVncToken, removeVncToken } from '../src/display/streamingStack.js';

test('writes and removes a well-formed token file', async () => {
    const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'savv-vnc-'));
    process.env.RUNNER_VNC_TOKEN_DIR = dir;

    const token = 'a'.repeat(32);
    await writeVncToken(token, 5901);

    const contents = await fs.readFile(path.join(dir, token), 'utf8');
    assert.equal(contents.trim(), '127.0.0.1:5901');

    await removeVncToken(token);
    await assert.rejects(() => fs.access(path.join(dir, token)));
});

test('refuses to write a token file for a malformed token (path traversal guard)', async () => {
    const dir = await fs.mkdtemp(path.join(os.tmpdir(), 'savv-vnc-'));
    process.env.RUNNER_VNC_TOKEN_DIR = dir;

    await assert.rejects(() => writeVncToken('../../etc/passwd', 5901));
    await assert.rejects(() => writeVncToken('has spaces', 5901));
    await assert.rejects(() => writeVncToken('short', 5901));
});
