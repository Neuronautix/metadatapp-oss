import { test, expect } from '@playwright/test';
import { openOsomaApp } from './helpers';

test.describe('Osoma Tecniplast alarms', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('use_msw', 'false');
        });
    });

    test('loads the alarms page without backend route errors', async ({ page }) => {
        await openOsomaApp(page);

        await page.getByRole('link', { name: 'Alarms' }).first().click();

        await expect(page).toHaveURL(/\/tp\/alarms$/);
        await expect(page.getByRole('heading', { name: 'Alarms Center', exact: true })).toBeVisible();
        await expect(page.getByRole('table').or(page.getByText('No alarms')).first()).toBeVisible();
        await expect(page.getByText('Alarm stream unavailable')).not.toBeVisible();
        await expect(page.getByText('No route found for')).not.toBeVisible();
    });
});
