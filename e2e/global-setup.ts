import { chromium, FullConfig } from '@playwright/test';
import * as path from 'path';
import * as fs from 'fs';

async function globalSetup(config: FullConfig) {
    const { baseURL, storageState } = config.projects[0].use;
    const storageStateDir = path.join(__dirname, 'storageState');

    if (!fs.existsSync(storageStateDir)) {
        fs.mkdirSync(storageStateDir, { recursive: true });
    }

    const browser = await chromium.launch({
        args: ['--host-resolver-rules=MAP auth.metadatapp.test 127.0.0.1, MAP osoma.metadatapp.test 127.0.0.1']
    });

    // Define users to authenticate
    const users = [
        {
            role: 'admin',
            username: process.env.E2E_ADMIN_USER || 'doctor.yiolyo',
            password: process.env.E2E_ADMIN_PASS || 'Pa55w0rd',
            storageStatePath: path.join(storageStateDir, 'admin.json')
        },
        {
            role: 'user',
            username: process.env.E2E_USER_USER || 'njuke.oaye',
            password: process.env.E2E_USER_PASS || 'Pa55w0rd',
            storageStatePath: path.join(storageStateDir, 'user.json')
        }
    ];

    for (const user of users) {
        console.log(`Authenticating ${user.role}...`);
        const context = await browser.newContext({ ignoreHTTPSErrors: true });
        const page = await context.newPage();

        // Disable MSW via localStorage
        await page.addInitScript(() => {
            localStorage.setItem('use_msw', 'false');
            localStorage.removeItem('metadatapp_auth_session');
            localStorage.removeItem('metadatapp_role');
        });

        try {
            if (!baseURL) {
                throw new Error('baseURL is not defined in the configuration');
            }

            await page.goto(baseURL + '/', { waitUntil: 'domcontentloaded' });

            const authenticatedAppMarker = page.getByRole('button', { name: /Logout|Mock User|doctor|njuke|User/i }).first();
            const osomaLoginButton = page.getByRole('button', { name: 'Login with Keycloak' });

            if (await authenticatedAppMarker.isVisible({ timeout: 3000 }).catch(() => false)) {
                await context.storageState({ path: user.storageStatePath });
                console.log(`Saved existing storage state for ${user.role} to ${user.storageStatePath}`);
                continue;
            }

            if (await osomaLoginButton.isVisible({ timeout: 10000 }).catch(() => false)) {
                await osomaLoginButton.click();
            }

            // Wait for Keycloak redirect and login form
            await page.waitForURL(/.*\/protocol\/openid-connect\/auth.*/, { timeout: 30000 });
            await page.waitForSelector('#username, #password', { state: 'visible', timeout: 15000 });

            // Fill credentials
            await page.fill('#username', user.username);
            await page.fill('#password', user.password);

            // Submit
            // Try to find the login button by typical Keycloak IDs or name
            const loginButton = page.locator('#kc-login').or(page.getByRole('button', { name: 'Sign In' }));
            await loginButton.click();

            // Wait for successful redirect back through /auth/callback to the app.
            await page.waitForURL((url) => url.origin === new URL(baseURL).origin && !url.pathname.startsWith('/auth/callback'), { timeout: 45000 });

            await authenticatedAppMarker.waitFor({ state: 'visible', timeout: 30000 });

            await context.storageState({ path: user.storageStatePath });
            console.log(`Saved storage state for ${user.role} to ${user.storageStatePath}`);

        } catch (error) {
            const failurePath = path.join(storageStateDir, `${user.role}-auth-failure.png`);
            await page.screenshot({ path: failurePath, fullPage: true }).catch(() => undefined);
            console.error(`Auth failure URL for ${user.role}: ${page.url()}`);
            console.error(`Auth failure screenshot for ${user.role}: ${failurePath}`);
            console.error(`Failed to authenticate ${user.role}:`, error);
            // Don't fail the entire build here, subsequent tests will fail if auth is missing
            // But we should probably throw to stop early if strictly required
            throw error;
        } finally {
            await context.close();
        }
    }

    await browser.close();
}

export default globalSetup;
