import { test, expect } from '@playwright/test';

test.describe.skip('Legacy admin resource: Investigation route', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.setItem('use_msw', 'false'));
    });

    test('List view loads', async ({ page }) => {
        await page.goto('/admin#/projects');
        await expect(page.locator('.RaList-main, table')).toBeVisible({ timeout: 15000 });
        await expect(page.locator('h2').filter({ hasText: /projects/i })).toBeVisible();
    });
});
