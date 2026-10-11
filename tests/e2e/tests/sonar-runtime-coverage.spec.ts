/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { mkdirSync, writeFileSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { sourcePath } from '../coverage/source.cjs';

const coverageDirectory = process.env.CACTI_COVERAGE_DIR;
const root = resolve(__dirname, '../../..');
const tracked = new Set(execFileSync('git', ['ls-files', '-z'], { cwd: root, encoding: 'utf8' }).split('\0'));
const exclusions = readFileSync(resolve(root, 'sonar-project.properties'), 'utf8').match(/^sonar.exclusions=(.+)$/m)?.[1].split(',') ?? [];
test.skip(!coverageDirectory, 'Native coverage requires an explicitly selected disposable fixture.');
test.beforeEach(async ({ page, browserName }) => {
	if (coverageDirectory) {
		expect(browserName).toBe('chromium');
		await page.coverage.startJSCoverage({ resetOnNavigation: false });
	}
});
test.afterEach(async ({ page }, testInfo) => {
	if (!coverageDirectory) return;
	const observations = await page.coverage.stopJSCoverage();
	// Persist only byte-verified static repository scripts. Inline application
	// data, session tokens and third-party scripts never enter raw artifacts.
	const entries = observations.filter(entry => entry.url && sourcePath(entry,
		process.env.CACTI_BASE_URL ?? 'http://127.0.0.1:8080', root, tracked, exclusions));
	mkdirSync(coverageDirectory, { recursive: true, mode: 0o700 });
	writeFileSync(resolve(coverageDirectory, `${testInfo.workerIndex}-${testInfo.testId}-${testInfo.retry}.json`), JSON.stringify(entries), { mode: 0o600 });
});

for (const route of [
	'/index.php', '/graph_view.php?action=preview', '/graphs.php', '/host.php',
	'/host_templates.php', '/data_queries.php', '/automation_templates.php',
	'/automation_graph_rules.php', '/automation_tree_rules.php', '/plugins.php',
	'/settings.php', '/user_admin.php', '/templates_export.php', '/tree.php',
]) {
	test(`authenticated native page executes first-party scripts: ${route}`, async ({ page }) => {
		await page.goto('/');
		await page.locator('[name="login_username"]').fill('admin');
		await page.locator('[name="login_password"]').fill('admin');
		await page.locator('form#auth [type="submit"]').click();
		await expect(page).toHaveURL(/index\.php/);
		const response = await page.goto(route);
		expect(response?.ok()).toBe(true);
		await expect(page.locator('body')).toBeVisible();
		await expect(page.locator('form#auth')).toHaveCount(0);
		await expect(page.locator('body')).not.toContainText('Fatal error:');
		await expect(page.locator('body')).not.toContainText('You are not permitted to access this section');
		await expect(page.locator('script[src*="include/layout.js"]')).toHaveCount(1);
	});
}
