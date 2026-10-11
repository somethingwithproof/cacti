/*
 +-------------------------------------------------------------------------
 | Copyright (C) 2004-2026 The Cacti Group								   |
 |																		   |
 | This program is free software; you can redistribute it and/or		   |
 | modify it under the terms of the GNU General Public License			   |
 | as published by the Free Software Foundation; either version 2		   |
 | of the License, or (at your option) any later version.				   |
 +-------------------------------------------------------------------------
 | Cacti: The Complete RRDtool-based Graphing Solution					   |
 +-------------------------------------------------------------------------
 | http://www.cacti.net/												   |
 +-------------------------------------------------------------------------
*/

import { test, expect } from '@playwright/test';

for (const scenario of [
	{ name: 'wrong password', username: 'admin', password: 'wrong-fixture-password' },
	{ name: 'unknown user', username: 'missingfixtureuser', password: 'wrong-fixture-password' },
	{ name: 'empty password', username: 'admin', password: '' },
]) {
	test(`local login ${scenario.name} renders a working reset link`, async ({ page }) => {
		await page.goto('/');
		await expect(page.locator('.cactiAuthErrors a')).toHaveCount(0);
		if (await page.locator('#realm').count()) {
			await page.locator('#realm').selectOption({ label: 'Local' });
		}
		await page.locator('#login_username').fill(scenario.username);
		await page.locator('#login_password').fill(scenario.password);
		if (!scenario.password) {
			await page.locator('#login_password').evaluate((input: HTMLInputElement) => { input.required = false; });
		}
		await Promise.all([
			page.waitForNavigation(),
			page.locator('form#auth input[type="submit"], form#auth button[type="submit"]').click(),
		]);
		const errors = page.locator('.cactiAuthErrors');
		await expect(errors).not.toContainText('<a');
		const reset = errors.getByRole('link', { name: 'Reset password', exact: true });
		await expect(reset).toHaveCount(1);
		await expect(reset).toHaveAttribute('href', /auth_resetpassword\.php$/);
		await reset.click();
		await expect(page).toHaveURL(/auth_resetpassword\.php$/);
		await expect(page.locator('form#auth')).toBeVisible();
	});
}

test('login page renders form controls without browser errors', async ({ page }) => {
	const errors: string[] = [];

	page.on('console', (message) => {
		if (message.type() === 'error') {
			errors.push(message.text());
		}
	});

	page.on('pageerror', (error) => {
		errors.push(error.message);
	});

	const response = await page.goto('/', { waitUntil: 'networkidle' });

	expect(response?.ok()).toBe(true);
	await expect(page.locator('form#auth')).toBeVisible();
	await expect(page.locator('#login_username')).toBeVisible();
	await expect(page.locator('#login_password')).toBeVisible();
	await expect(page.locator('input[type="submit"], button[type="submit"]')).toHaveCount(1);
	expect(errors).toEqual([]);
});

test('login page core static assets load successfully', async ({ page }) => {
	const assetResponses: Array<{ url: string; status: number }> = [];

	page.on('response', (response) => {
		const url = response.url();

		if (/\.(css|js)(\?|$)/.test(url)) {
			assetResponses.push({
				url,
				status: response.status(),
			});
		}
	});

	await page.goto('/', { waitUntil: 'networkidle' });

	expect(assetResponses.some((asset) => asset.url.includes('/include/js/jquery.js') && asset.status === 200)).toBe(true);
	expect(assetResponses.some((asset) => asset.url.includes('/include/layout.js') && asset.status === 200)).toBe(true);
	// main.css is served from the active theme directory, e.g.
	// /include/themes/modern/main.css, not /include/css/.
	expect(assetResponses.some((asset) => /\/include\/themes\/[^/]+\/main\.css(\?|$)/.test(asset.url) && asset.status === 200)).toBe(true);
	expect(assetResponses.filter((asset) => asset.status >= 400)).toEqual([]);
});
