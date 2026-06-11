import { test, expect } from '@playwright/test';

test.describe.skip('Legacy admin resource: Connected applications', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.setItem('use_msw', 'false'));
    });

    test('List view loads', async ({ page }) => {
        await page.goto('/admin#/connected_apps');
        await expect(page.locator('.RaList-main').first()).toBeVisible({ timeout: 15000 });
        await expect(page.getByRole('heading', { name: /connected apps/i })).toBeVisible();
    });
});
