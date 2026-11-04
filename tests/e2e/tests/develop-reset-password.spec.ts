import { test, expect } from '@playwright/test';

const rejected = 'Incorrect resetlink hash';

test.describe('password reset form hash handling', () => {
	for (const [label, hash] of [['empty', ''], ['unknown', 'ffffffffffffffffffffffffffffffff'], ['malformed', 'not a hash!']]) {
		test(`${label} hash returns to the identity form`, async ({ page }) => {
			const response = await page.goto(`/auth_resetpassword.php?action=formreset&hash=${encodeURIComponent(hash)}`);
			expect(response?.ok()).toBe(true);
			await expect(page.getByText(rejected)).toBeVisible();
			await expect(page.locator('#identity')).toBeVisible();
			await expect(page.locator('#password')).toHaveCount(0);
			expect(await page.content()).not.toMatch(/Undefined array key|Warning:/);
		});
	}

	// Needs a live, unexpired row in user_auth_reset_hashes supplied by the environment.
	test('a stored hash shows the new password form', async ({ page }) => {
		const hash = process.env.CACTI_E2E_RESET_HASH;
		test.skip(!hash, 'CACTI_E2E_RESET_HASH is not set');
		await page.goto(`/auth_resetpassword.php?action=formreset&hash=${encodeURIComponent(hash!)}`);
		await expect(page.locator('#password')).toBeVisible();
		await expect(page.locator('#password_confirm')).toBeVisible();
		await expect(page.getByText(rejected)).toHaveCount(0);
	});
});
