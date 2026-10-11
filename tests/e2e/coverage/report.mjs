// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
import { readFileSync, readdirSync } from 'node:fs';
import { resolve, relative, isAbsolute, matchesGlob } from 'node:path';
import { fileURLToPath } from 'node:url';
import { execFileSync } from 'node:child_process';
import v8ToIstanbul from 'v8-to-istanbul';
import coverageLibrary from 'istanbul-lib-coverage';
import reportLibrary from 'istanbul-lib-report';
import reports from 'istanbul-reports';

export function sourcePath(entry, origin, root, tracked, exclusions = []) {
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
}

export async function convert(entries, { origin, root, tracked, exclusions = [] }) {
	const map = coverageLibrary.createCoverageMap({});
	for (const entry of entries) {
		if (!entry.url) continue;
		const path = sourcePath(entry, origin, root, tracked, exclusions);
		if (!path) continue;
		const converter = v8ToIstanbul(path, 0, { source: entry.source });
		await converter.load();
		converter.applyCoverage(entry.functions);
		map.merge(converter.toIstanbul());
	}
	if (map.files().length === 0) throw new Error('No checkout-owned JavaScript coverage was collected.');
	return map;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	const root = resolve(fileURLToPath(new URL('../../..', import.meta.url)));
	const directory = resolve(process.env.CACTI_COVERAGE_DIR ?? resolve(root, 'coverage/browser'));
	const entries = readdirSync(directory).filter(name => name.endsWith('.json')).flatMap(name => JSON.parse(readFileSync(resolve(directory, name), 'utf8')));
	const tracked = new Set(execFileSync('git', ['ls-files', '-z'], { cwd: root, encoding: 'utf8' }).split('\0'));
	const properties = readFileSync(resolve(root, 'sonar-project.properties'), 'utf8');
	const exclusions = properties.match(/^sonar.exclusions=(.+)$/m)?.[1].split(',');
	if (!exclusions) throw new Error('Sonar source exclusions are missing.');
	const map = await convert(entries, { root, tracked, exclusions, origin: process.env.CACTI_BASE_URL ?? 'http://127.0.0.1:8080' });
	const context = reportLibrary.createContext({ dir: resolve(root, 'coverage'), coverageMap: map });
	reports.create('lcovonly').execute(context);
	reports.create('text-summary').execute(context);
}
