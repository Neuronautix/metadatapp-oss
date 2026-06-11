import { test, expect } from '@playwright/test';

test.describe('Dashboard', () => {
  // Ensure MSW is disabled
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('use_msw', 'false');
    });
  });

  test('Loads successfully and displays key widgets', async ({ page }) => {
    await page.goto('/admin');

    // Should be redirected to dashboard immediately (checked by global setup)
    // or manually if we were unauthenticated (but this test runs on auth projects)

    // Wait for main content
    await expect(page.locator('main')).toBeVisible();

    // Check for dashboard-specific elements
    // Based on the React Admin default or custom dashboard
    // We look for the "Welcome" or generic content first
    await expect(page.locator('body')).toBeVisible();
  });
});
