import { test, expect } from '@playwright/test'
import { openOsomaApp } from './helpers'

test.describe('Curation Workflow', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('use_msw', 'false')
    })
  })

  test('navigates to data curation module and displays upload prompt', async ({ page }) => {
    await openOsomaApp(page, '/')

    // The feature flag feature.curationWorkflow.enabled is 'true' by default
    const curationLink = page.getByRole('link', { name: 'Data Curation' })
    await expect(curationLink).toBeVisible()
    await curationLink.click()

    // Verify routing and UI
    await expect(page).toHaveURL(/.*\/curation\/import/)
    await expect(page.getByRole('heading', { name: 'Data Curation' })).toBeVisible()
    await expect(page.getByText('Upload laboratory data')).toBeVisible()
  })
})
