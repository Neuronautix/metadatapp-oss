import { test, expect } from '@playwright/test';

test.describe.skip('Legacy admin resource: Studies', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => localStorage.setItem('use_msw', 'false'));
    });

    test('List view loads', async ({ page }) => {
        await page.goto('/admin#/experiments');
        await expect(page.locator('.RaList-main').first()).toBeVisible({ timeout: 15000 });
        await expect(page.getByRole('heading', { name: /studies/i })).toBeVisible();
    });

    test('Create Study page loads', async ({ page }) => {
        await page.goto('/admin#/experiments/create');
        await expect(page.locator('form')).toBeVisible({ timeout: 15000 });
        await expect(page.getByText('Create Study')).toBeVisible();
    });
});
