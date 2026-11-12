import { test, expect } from '@playwright/test';

const ADMIN_USER = process.env.E2E_ADMIN_USER || 'doctor.yiolyo';
const ADMIN_PASS = process.env.E2E_ADMIN_PASS || 'Pa55w0rd';

test.describe('Simple Authentication', () => {
    test.use({ storageState: { cookies: [], origins: [] } });
    test('Authenticate via UI and verify dashboard access', async ({ page, baseURL }) => {
        // Disable MSW
        await page.addInitScript(() => {
            localStorage.setItem('use_msw', 'false');
        });

        console.log(`Navigating to ${baseURL}/admin...`);
        await page.goto('/admin');

        // Verify Keycloak login page loaded
        // Using the correct regex we discovered earlier
        await expect(page).toHaveURL(/.*\/protocol\/openid-connect\/auth.*/, { timeout: 15000 });
        await expect(page.locator('#username')).toBeVisible();

        console.log('Filling credentials...');
        await page.fill('#username', ADMIN_USER);
        await page.fill('#password', ADMIN_PASS);

        console.log('Submitting login form...');
        // Try both ID and role to be robust
        const loginButton = page.locator('#kc-login').or(page.getByRole('button', { name: 'Sign In' }));
        await loginButton.click();

        console.log('Waiting for redirect to dashboard...');
        // Match /admin or /admin#/
        await expect(page).toHaveURL(/\/admin(#\/)?$/, { timeout: 15000 });

        console.log('Verifying dashboard content...');
        await expect(page.locator('main')).toBeVisible();
    });
});
