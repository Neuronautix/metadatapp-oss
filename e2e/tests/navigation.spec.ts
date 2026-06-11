import { test, expect } from '@playwright/test';

test.describe('Navigation', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('use_msw', 'false');
        });
    });

    const metadataRoutes = [
        { name: 'Metadata Catalog', path: '/metadata', heading: /Metadata Catalog/i },
        { name: 'Study collection', path: '/metadata/experiments', heading: /Studies/i },
        { name: 'Assay collection', path: '/metadata/procedures', heading: /Assays/i },
        { name: 'Subject collection', path: '/metadata/subjects', heading: /Subjects/i },
        { name: 'Cage collection', path: '/metadata/cages', heading: /Cages/i },
        { name: 'Connected applications collection', path: '/metadata/connected_apps', heading: /Connected Apps/i }
    ];

    for (const route of metadataRoutes) {
        test(`${route.name} route loads`, async ({ page }) => {
            await page.goto(route.path);

            await expect(page).toHaveURL(new RegExp(`${route.path.replace('/', '\\/')}(\\?.*)?$`));
            await expect(page.locator('main')).toBeVisible();
            await expect(page.getByRole('heading', { name: route.heading }).first()).toBeVisible();
        });
    }
});
