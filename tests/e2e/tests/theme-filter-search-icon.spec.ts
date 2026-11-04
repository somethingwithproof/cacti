import { test, expect, type Page } from '@playwright/test';

/* Run against the standard disposable Cacti compose installation.
 * Collapses the host filter, reloads so the filter is hidden at load, then
 * expands it and checks the search icon, placeholder and autocomplete setting
 * for every theme that decorates the filter input. */

const themes = ['cacti', 'dark', 'deepness', 'midwinter', 'modern', 'paper-plane', 'paw', 'raspberry', 'sunrise'];

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill('admin');
    await page.locator('input[name="login_password"]').fill('admin');
    await page.locator('form#login input[type="submit"]').click();
    await expect(page).toHaveURL(/index\.php/);
}

async function selectTheme(page: Page, theme: string): Promise<void> {
    await page.goto('/auth_profile.php?action=edit&tab=general');
    if (await page.locator('#selected_theme').inputValue() !== theme) {
        const saved = page.waitForResponse(response =>
            response.url().includes('auth_profile.php?tab=general&action=update_data') &&
            response.request().method() === 'POST');
        await page.locator('#selected_theme').selectOption(theme);
        expect((await saved).ok()).toBeTruthy();
        await page.waitForLoadState('networkidle');
    }
}

test.beforeEach(async ({ page }) => { await login(page); });

for (const theme of themes) {
    test(`${theme} decorates the filter when it is hidden at load and later expanded`, async ({ page }) => {
        await selectTheme(page, theme);

        await page.goto('/host.php');
        const input = page.locator('input#filter');
        const icon = page.locator('input#filter + i.ti-search.filter');

        /* Make sure the stored state is hidden, whichever state we start in. */
        if (await input.isVisible()) {
            await page.locator('.cactiTableTitle').first().click();
        }
        await expect(input).toBeHidden();

        await page.reload();
        await page.waitForLoadState('networkidle');
        await expect(input).toBeHidden();

        await page.locator('.cactiTableTitle').first().click();
        await expect(input).toBeVisible();
        await expect(icon).toHaveCount(1);
        await expect(icon).toBeVisible();
        await expect(input).toHaveAttribute('autocomplete', 'off');
        await expect(input).toHaveAttribute('placeholder', /\S/);

        /* A second skin pass must not add a second icon. */
        await page.evaluate(() => (window as any).applySkin());
        await expect(page.locator('input#filter + i.ti-search.filter')).toHaveCount(1);

        /* Narrow viewport then widened keeps exactly one icon. */
        await page.setViewportSize({ width: 480, height: 800 });
        await page.setViewportSize({ width: 1400, height: 900 });
        await expect(page.locator('input#filter ~ i.ti-search.filter')).toHaveCount(1);

        /* Restore the default so later specs see an expanded filter. */
        await page.evaluate(() => sessionStorage.clear());
    });
}
