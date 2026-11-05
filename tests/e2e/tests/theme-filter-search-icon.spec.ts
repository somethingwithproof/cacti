import { test, expect, type Page } from '@playwright/test';

/* Run against the standard disposable Cacti compose installation, with the
 * local admin account (CACTI_E2E_USER / CACTI_E2E_PASSWORD, default admin/admin)
 * released from the forced first-login password change.
 * Collapses the host filter, reloads so the filter is hidden at load, then
 * expands it and checks the search icon, placeholder and autocomplete setting
 * for every theme that decorates the filter input. */

const themes = ['cacti', 'dark', 'deepness', 'midwinter', 'modern', 'paper-plane', 'paw', 'raspberry', 'sunrise'];

async function login(page: Page): Promise<void> {
    await page.goto('/');
    await page.locator('input[name="login_username"]').fill(process.env.CACTI_E2E_USER ?? 'admin');
    await page.locator('input[name="login_password"]').fill(process.env.CACTI_E2E_PASSWORD ?? 'admin');
    await page.locator('#realm').selectOption('0');
    await page.locator('#auth button[type="submit"]').click();
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
        await page.waitForLoadState('networkidle');
        const input = page.locator('input#filter');
        const icon = page.locator('input#filter + i.ti-search.filter');

        /* midwinter moves the filter into its own panel and has no collapse
         * control, so only the decoration is checked there, whether or not the
         * panel is open. */
        const toggle      = page.locator('.cactiFilterState').first();
        const collapsible = await toggle.count() > 0;

        if (collapsible) {
            /* Make sure the stored state is hidden, whichever state we start in. */
            if (await input.isVisible()) {
                await toggle.click();
            }
            await expect(input).toBeHidden();

            await page.reload();
            await page.waitForLoadState('networkidle');
            await expect(input).toBeHidden();

            await toggle.click();
            await expect(input).toBeVisible();
            await expect(icon).toBeVisible();
        }

        await expect(icon).toHaveCount(1);
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
