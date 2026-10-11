/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const coverageDirectory = process.env.CACTI_COVERAGE_DIR;
test.beforeEach(async ({ page, browserName }) => {
	if (coverageDirectory) {
		expect(browserName).toBe('chromium');
		await page.coverage.startJSCoverage({ resetOnNavigation: false });
	}
});
test.afterEach(async ({ page }, testInfo) => {
	if (!coverageDirectory) return;
	const entries = await page.coverage.stopJSCoverage();
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
