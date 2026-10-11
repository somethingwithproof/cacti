// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { resolve } from 'node:path';
import { sourcePath, convert } from './report.mjs';

function fixture() {
	const root = mkdtempSync(resolve(tmpdir(), 'cacti-sonar-coverage-'));
	const source = 'function value() { return 1; }\nvalue();\n';
	writeFileSync(resolve(root, 'client.js'), source);
	const entry = { url: 'http://cacti.test/client.js', source, functions: [{ functionName: '', ranges: [{ startOffset: 0, endOffset: source.length, count: 1 }], isBlockCoverage: true }] };
	return { root, source, entry, tracked: new Set(['client.js']), origin: 'http://cacti.test' };
}

test('browser coverage rejects another origin, untracked files and configured exclusions', () => {
	const f = fixture();
	try {
		assert.equal(sourcePath({ ...f.entry, url: 'https://other.test/client.js' }, f.origin, f.root, f.tracked), null);
		assert.equal(sourcePath(f.entry, f.origin, f.root, new Set()), null);
		assert.equal(sourcePath(f.entry, f.origin, f.root, f.tracked, ['**/*.js']), null);
	} finally { rmSync(f.root, { recursive: true }); }
});

test('browser coverage fails closed when served source differs from the checkout', () => {
	const f = fixture();
	try { assert.throws(() => sourcePath({ ...f.entry, source: 'stale source' }, f.origin, f.root, f.tracked), /does not match/); }
	finally { rmSync(f.root, { recursive: true }); }
});

test('browser coverage keeps execution counts and uncovered functions', async () => {
	const f = fixture();
	try {
		f.entry.functions.push({ functionName: 'value', ranges: [{ startOffset: 0, endOffset: 30, count: 0 }], isBlockCoverage: true });
		const map = await convert([f.entry], f);
		const summary = map.getCoverageSummary().toJSON();
		assert.equal(map.files().length, 1);
		assert.equal(summary.functions.covered, 0);
		assert.ok(summary.lines.covered < summary.lines.total);
	} finally { rmSync(f.root, { recursive: true }); }
});

test('browser coverage merges observations without inventing covered functions', async () => {
	const f = fixture();
	try {
		const map = await convert([f.entry, f.entry], f);
		assert.equal(map.files().length, 1);
		assert.equal(map.getCoverageSummary().lines.pct, 100);
	} finally { rmSync(f.root, { recursive: true }); }
});

test('browser coverage refuses an empty first-party report', async () => {
	const f = fixture();
	try { await assert.rejects(convert([], f), /No checkout-owned/); }
	finally { rmSync(f.root, { recursive: true }); }
});
