/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
import { test, expect, type Page } from '@playwright/test';

async function login(page: Page) {
	await page.goto('/');
	await page.locator('[name="login_username"]').fill('admin');
	await page.locator('[name="login_password"]').fill('admin');
	await page.locator('form#auth [type="submit"]').click();
	await expect(page).toHaveURL(/index\.php/);
}

async function labels(page: Page, ids: string[]) {
	for (const id of ids) {
		const control = page.locator(`#${id}`);
		await expect(control).toHaveCount(1);
		expect(await control.evaluate(el => Array.from((el as HTMLInputElement).labels ?? []).map(label => label.textContent?.trim()).filter(Boolean))).not.toEqual([]);
	}
}

for (const [route, ids] of [
	['/plugins.php', ['filter', 'state', 'rows']],
	['/tree.php?action=edit&id=1', ['sfilter', 'hfilter', 'gfilter']],
	['/data_queries.php?action=item_edit&id=1&snmp_query_id=1', ['svg_field', 'svg_text']],
	['/package.php', ['package_export_type']],
	['/utilities.php?action=view_boost_status', ['refresh']],
	['/auth_changepassword.php', ['current', 'password', 'password_confirm']],
	['/color.php?action=import', ['allow_update']],
	['/graph_view.php?action=preview', ['host_id', 'site_id']],
] as [string, string[]][]) {
	test(`${route} connects native controls to their labels`, async ({ page }) => {
		await login(page);
		await page.goto(route);
		await labels(page, ids);
	});
}

test('password reset identity and reset controls have accessible labels', async ({ page }) => {
	await page.goto('/auth_resetpassword.php?action=formidentity');
	await labels(page, ['identity']);
	await page.goto('/auth_resetpassword.php?action=formreset&hash=sonarbrowserfixture20261010');
	await labels(page, ['password', 'password_confirm']);
});

test('autocomplete decoys are hidden from the user and accessibility tree', async ({ page }) => {
	await login(page);
	await page.goto('/auth_changepassword.php');
	const decoys = page.locator('tr[style="display:none"] input[type="text"], tr[style="display:none"] input[type="password"]');
	await expect(decoys).toHaveCount(2);
	for (const input of await decoys.all()) await expect(input).toBeHidden();
});

for (const route of ['/index.php', '/graph_view.php?action=preview', '/install/install.php', '/graph_realtime.php?local_graph_id=0']) {
	test(`${route} receives exactly one title from the shared PHP header`, async ({ page }) => {
		await login(page);
		await page.goto(route);
		await expect(page.locator('head > title')).toHaveCount(1);
		expect(await page.title()).not.toBe('');
		if (route.startsWith('/install/') || route.startsWith('/graph_realtime')) {
			await expect(page.locator('html')).toHaveAttribute('lang', /.+/);
			expect(await page.evaluate(() => document.doctype?.name.toLowerCase())).toBe('html');
		}
		if (route.startsWith('/graph_realtime')) await labels(page, ['graph_start', 'ds_step', 'size']);
	});
}

for (const [route, minimum] of [
	['/index.php', 1], ['/plugins.php', 1], ['/package.php', 2],
	['/host.php?action=edit&id=1', 2], ['/host_templates.php?action=edit&id=1', 2],
	['/data_queries.php?action=item_edit&id=1&snmp_query_id=1', 3], ['/data_templates.php?action=template_edit&id=1', 1],
	['/tree.php?action=edit&id=1', 3], ['/utilities.php?action=view_boost_status', 1],
] as [string, number][]) {
	test(`${route} marks control and layout tables as presentation`, async ({ page }) => {
		await login(page);
		await page.goto(route);
		expect(await page.locator('table[role="presentation"]').count()).toBeGreaterThanOrEqual(minimum);
	});
}

for (const pageName of ['user_admin', 'user_group_admin']) {
	for (const tab of ['permsg', 'permsd', 'permste', 'permstr']) {
		test(`${pageName} ${tab} exposes policy controls as a layout`, async ({ page }) => {
			await login(page);
			const action = pageName === 'user_admin' ? 'user_edit' : 'edit';
			await page.goto(`/${pageName}.php?action=${action}&id=1&tab=${tab}`);
			expect(await page.locator('table[role="presentation"]').count()).toBeGreaterThan(0);
		});
	}
}

for (const [file, action] of [
	['host_templates.php', 'item_remove_gt_confirm'], ['host_templates.php', 'item_remove_dq_confirm'],
	['automation_templates.php', 'item_remove_agr_confirm'], ['automation_templates.php', 'item_remove_atr_confirm'],
	['automation_templates.php', 'item_remove_ttr_confirm'],
]) {
	test(`${file} ${action} renders one set of confirmation IDs`, async ({ page }) => {
		await login(page);
		await page.goto(`/${file}?action=${action}&id=1&host_template_id=1&template_id=1&rule_id=1&header=false`);
		await expect(page.locator('#cancel')).toHaveCount(1);
		await expect(page.locator('#continue')).toHaveCount(1);
	});
}

test('graph properties fragment keeps the parent data ID unique', async ({ page }) => {
	await login(page);
	await page.goto('/graph_view.php?action=zoom-preview&local_graph_id=1&rra_id=0');
	await expect(page.locator('#data')).toHaveCount(1);
	await page.evaluate(() => (window as any).graphProperties());
	await expect(page.getByText('RRDtool Command:', { exact: true })).toBeAttached();
	await expect(page.locator('#data')).toHaveCount(1);
});

test('version and license headings retain their text with current HTML elements', async ({ page }) => {
	await login(page);
	await page.goto('/about.php');
	await expect(page.locator('font, tt')).toHaveCount(0);
	await expect(page.locator('span.textSubHeaderDark')).toContainText('1.3.');
	await expect(page.locator('code')).toHaveCount(2);
	await page.goto('/color.php?action=import');
	await expect(page.locator('font')).toHaveCount(0);
	await expect(page.locator('span.textEditTitle')).toHaveCount(2);
});
