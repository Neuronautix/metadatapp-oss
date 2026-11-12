import { defineConfig, devices } from "@playwright/test";

/**
 * E2E Playwright Configuration
 *
 * This configuration runs E2E tests against the Osoma frontend with a live API (not MSW).
 */
export default defineConfig({
  timeout: 60000,
  expect: {
    timeout: 30000,
  },
  testDir: "./tests",

  /* Run tests in files in parallel */
  fullyParallel: true,

  /* Fail the build on CI if you accidentally left test.only in the source code. */
  forbidOnly: !!process.env.CI,

  /* Retry on CI only */
  retries: process.env.CI ? 2 : 0,

  /* Sequential execution at top level for data isolation (API Platform pattern) */
  workers: process.env.CI ? 1 : 1,

  /* Reporter to use. See https://playwright.dev/docs/test-reporters */
  reporter: process.env.CI ? "github" : "line",

  /* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
  use: {
    /* Base URL to use in actions like `await page.goto("/")`. */
    baseURL: process.env.E2E_BASE_URL || "https://osoma.metadatapp.test",

    /* Ignore HTTPS errors for self-signed certificates in dev (API Platform pattern) */
    ignoreHTTPSErrors: true,

    launchOptions: {
      args: ['--host-resolver-rules=MAP auth.metadatapp.test 127.0.0.1, MAP osoma.metadatapp.test 127.0.0.1']
    },

    /* Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer */
    trace: "on-first-retry",

    /* Capture screenshot on failure https://playwright.dev/docs/api/class-testoptions#test-options-screenshot */
    screenshot: "only-on-failure",

    /* Video recording: retain on failure by default, or force always if env var set */
    video: process.env.FORCE_VIDEO ? 'on' : 'retain-on-failure',
  },

  /* Global setup for authentication */
  globalSetup: require.resolve('./global-setup'),

  /* Configure projects for major browsers */
  projects: [
    {
      name: 'osoma',
      use: {
        ...devices['Desktop Chrome'],
        baseURL: process.env.OSOMA_BASE_URL || 'https://osoma.metadatapp.test',
        storageState: 'storageState/admin.json',
      },
      testMatch: ['**/osoma/**/*.spec.ts', '**/tests/*.spec.ts'],
    },
  ],
});
