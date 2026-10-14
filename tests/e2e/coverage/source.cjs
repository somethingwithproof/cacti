// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
const { readFileSync } = require('node:fs');
const { resolve, relative, isAbsolute, matchesGlob } = require('node:path');

exports.sourcePath = function sourcePath(entry, origin, root, tracked, exclusions = []) {
	const url = new URL(entry.url);
	if (url.origin !== new URL(origin).origin) return null;
	const pathname = decodeURIComponent(url.pathname).replace(/^\//, '');
	const path = resolve(root, pathname);
	const name = relative(root, path);
	if (isAbsolute(name) || name.startsWith('..') || !tracked.has(name)) return null;
	if (exclusions.some(pattern => matchesGlob(name, pattern))) return null;
	if (!/\.(?:js|mjs)$/.test(name)) return null;
	if (readFileSync(path, 'utf8') !== entry.source) throw new Error(`Coverage source does not match checkout: ${name}`);
	return path;
};
