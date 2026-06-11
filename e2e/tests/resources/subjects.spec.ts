import { test, expect } from '@playwright/test';

test.describe.skip('Legacy admin resource: Subjects', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.setItem('use_msw', 'false'));
    });

    test('List view loads', async ({ page }) => {
        await page.goto('/admin#/subjects');
        await expect(page.locator('.RaList-main, table')).toBeVisible({ timeout: 15000 });
        await expect(page.locator('h2').filter({ hasText: /subjects/i })).toBeVisible();
    });

    test('Create new subject page loads', async ({ page }) => {
        await page.goto('/admin#/subjects/create');
        await expect(page.locator('form')).toBeVisible({ timeout: 15000 });
        await expect(page.getByText('Create Subject')).toBeVisible();
    });
});
