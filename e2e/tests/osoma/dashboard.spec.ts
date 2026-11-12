import { test, expect } from '@playwright/test';
import { openOsomaApp } from './helpers';

test.describe('Osoma Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('use_msw', 'false');
        });
    });

    test('Loads successfully', async ({ page }) => {
        await openOsomaApp(page);

        await expect(page.getByRole('link', { name: 'Investigations' }).first()).toBeVisible({ timeout: 20000 });
        await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
        await expect(page).toHaveTitle(/Metadatapp/i);
    });
});
