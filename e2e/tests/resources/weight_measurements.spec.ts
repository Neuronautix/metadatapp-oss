import { test, expect } from '@playwright/test';

test.describe.skip('Legacy admin resource: Weight measurements', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.setItem('use_msw', 'false'));
    });

    test('List view loads', async ({ page }) => {
        await page.goto('/admin#/weight_measurements');
        await expect(page.locator('.RaList-main, table')).toBeVisible({ timeout: 15000 });
        await expect(page.locator('h2').filter({ hasText: /weight/i })).toBeVisible();
    });
});
