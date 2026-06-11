import { test, expect } from '@playwright/test';
import { openOsomaApp } from './helpers';

test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => localStorage.setItem('use_msw', 'false'));
});

test('Osoma Ultimate Demo Showcase', async ({ page }) => {
    test.setTimeout(180000);

    await test.step('🎥 INTRO: Dashboard Reveal', async () => {
        await openOsomaApp(page);
        await expect(page.getByRole('link', { name: 'Investigations' }).first()).toBeVisible({ timeout: 20000 });
        await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
        await page.waitForTimeout(1000);
    });

    await test.step('🎨 SETTINGS: Appearance Toggle (Visual Flair)', async () => {
        await page.getByRole('link', { name: 'Appearance Studio' }).click();
        await expect(page.getByText('Demo Moment')).toBeVisible();

        const activatePresetButton = page.getByRole('button', { name: 'Activate' }).first();
        if (await activatePresetButton.isVisible({ timeout: 3000 }).catch(() => false)) {
            await activatePresetButton.click();
            await page.waitForTimeout(800);
        }
    });

    await test.step('🧪 ACTION: Core metadata views', async () => {
        await expect(
            page.getByRole('link', { name: 'New investigation' }).or(
                page.getByRole('button', { name: 'New investigation' })
            )
        ).toBeVisible();

        await page.getByRole('link', { name: 'Studies' }).first().click();
        await expect(page.getByRole('heading', { name: 'Studies', exact: true })).toBeVisible();

        await page.getByRole('link', { name: 'Subjects' }).first().click();
        await expect(page.getByRole('heading', { name: 'Subjects', exact: true })).toBeVisible();

        await page.getByRole('link', { name: 'Datasets' }).first().click();
        await expect(page.getByRole('heading', { name: 'Datasets', exact: true })).toBeVisible();
    });

    await test.step('📅 ACTION: Calendar overview', async () => {
        await page.getByRole('link', { name: 'Calendar' }).first().click();
        await expect(page.getByRole('heading', { name: 'Calendar', exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'week' }).click();
    });

    await test.step('🔌 ACTION: Connected apps overview', async () => {
        await page.getByRole('link', { name: 'Connected Apps' }).first().click();
        await expect(page.getByRole('heading', { name: 'Connected Applications', exact: true })).toBeVisible();

        const detailLink = page.getByLabel(/View details for/i).first();
        if (await detailLink.isVisible({ timeout: 3000 }).catch(() => false)) {
            await detailLink.click();
            await expect(page.getByRole('button', { name: 'Synchronize Now' })).toBeVisible();
        }
    });
});
