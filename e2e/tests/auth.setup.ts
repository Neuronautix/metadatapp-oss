import { test as setup, expect } from '@playwright/test';
import * as path from 'path';
import * as fs from 'fs';

const adminFile = path.join(__dirname, '../storageState/admin.json');
const userFile = path.join(__dirname, '../storageState/user.json');

// Ensure directory exists
const storageStateDir = path.join(__dirname, '../storageState');
if (!fs.existsSync(storageStateDir)) {
    fs.mkdirSync(storageStateDir, { recursive: true });
}

setup('authenticate as admin', async ({ page }) => {
    setup.setTimeout(60000);
    // Disable MSW
    await page.addInitScript(() => {
        localStorage.setItem('use_msw', 'false');
    });

    const baseURL = process.env.OSOMA_BASE_URL || 'https://osoma.metadatapp.test';
    const username = process.env.E2E_ADMIN_USER || 'doctor.yiolyo';
    const password = process.env.E2E_ADMIN_PASS || 'Pa55w0rd';

    await page.goto(baseURL + '/admin');

    // Bulletproof selectors for login/dashboard detection
    const loginForm = page.locator('#username');
    const dashboard = page.getByAltText('MAPP Logo').or(page.locator('.ra-layout'));

    try {
        await expect(loginForm.or(dashboard)).toBeVisible({ timeout: 20000 });
    } catch (e) {
        // Retry on cold start
        await page.goto(baseURL + '/admin');
        await expect(loginForm.or(dashboard)).toBeVisible({ timeout: 20000 });
    }

    if (await loginForm.isVisible()) {
        await page.fill('#username', username);
        await page.fill('#password', password);
        const loginButton = page.locator('#kc-login').or(page.getByRole('button', { name: 'Sign In' }));
        await loginButton.click();
        
        await page.waitForURL((url) => url.toString().includes('/admin') && !url.toString().includes('auth'), { timeout: 30000 });
    }

    // Final verification of dashboard presence
    await expect(dashboard).toBeVisible({ timeout: 30000 });
    await page.context().storageState({ path: adminFile });
});

setup('authenticate as user', async ({ page }) => {
    setup.setTimeout(60000);
    // Disable MSW
    await page.addInitScript(() => {
        localStorage.setItem('use_msw', 'false');
    });

    const baseURL = process.env.OSOMA_BASE_URL || 'https://osoma.metadatapp.test';
    const username = process.env.E2E_USER_USER || 'njuke.oaye';
    const password = process.env.E2E_USER_PASS || 'Pa55w0rd';

    await page.goto(baseURL + '/admin');
    const loginForm = page.locator('#username');
    const dashboard = page.getByAltText('MAPP Logo').or(page.locator('.ra-layout'));

    try {
        await expect(loginForm.or(dashboard)).toBeVisible({ timeout: 20000 });
    } catch (e) {
        await page.reload();
        await expect(loginForm.or(dashboard)).toBeVisible({ timeout: 20000 });
    }

    if (await loginForm.isVisible()) {
        await page.fill('#username', username);
        await page.fill('#password', password);
        const loginButton = page.locator('#kc-login').or(page.getByRole('button', { name: 'Sign In' }));
        await loginButton.click();
        await page.waitForURL((url) => url.toString().includes('/admin') && !url.toString().includes('auth'), { timeout: 30000 });
    }

    await expect(dashboard).toBeVisible({ timeout: 30000 });
    await page.context().storageState({ path: userFile });
});
