import { test, expect } from '@playwright/test';

test.describe.skip('Legacy admin resource: Assays', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.setItem('use_msw', 'false'));
    });

    test('List view loads', async ({ page }) => {
        await page.goto('/admin#/procedures');
        await expect(page.locator('.RaList-main').first()).toBeVisible({ timeout: 15000 });
        await expect(page.getByRole('heading', { name: /procedures/i })).toBeVisible();
    });

    test('Create Assay page loads', async ({ page }) => {
        await page.goto('/admin#/procedures/create');
        await expect(page.locator('form')).toBeVisible({ timeout: 15000 });
        await expect(page.getByText('Create Assay')).toBeVisible();
    });
});
