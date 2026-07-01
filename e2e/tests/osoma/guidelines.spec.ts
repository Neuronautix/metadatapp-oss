/**
 * International Guidelines Reporting — dedicated per-guideline pages + QC sign-off.
 *
 * Covers the flow this feature adds on top of the existing auto-fill/AI pipeline:
 *  1. The hub at /guidelines lists a card per guideline template.
 *  2. A card opens its dedicated page at /guidelines/:templateId.
 *  3. Picking a target and filling a field manually works on the dedicated page.
 *  4. A filled field can be marked reviewed (field-level QC).
 *  5. The report can be approved (report-level QC sign-off).
 *  6. The exported markdown report carries the QC sign-off stamp.
 *
 * Requirements per AGENTS.md:
 *  - MSW must be disabled (localStorage.setItem('use_msw', 'false'))
 *  - Do NOT use page.waitForNetworkIdle() - use explicit selectors
 */

import { readFile, stat } from 'node:fs/promises'
import { test, expect, type TestInfo } from '@playwright/test'
import { openOsomaApp } from './helpers'

const DEMO_PROJECT_NAME = 'Demo - DVC circadian rhythm monitoring in Shank3 mice'

test.describe('International Guidelines Reporting', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('use_msw', 'false')
    })
  })

  test('hub lists guideline cards that open their dedicated page', async ({ page }) => {
    await openOsomaApp(page, '/guidelines')

    await expect(page.getByRole('heading', { name: 'International Guidelines Reporting' })).toBeVisible()
    const arriveCard = page.getByTestId('guideline-card-arrive-v2')
    await expect(arriveCard).toBeVisible()

    await arriveCard.click()
    await expect(page).toHaveURL(/\/guidelines\/arrive-v2/)
    await expect(page.getByRole('heading', { name: 'ARRIVE 2.0' })).toBeVisible()
  })

  test('fills a field, marks it reviewed, approves the report, and the export carries the QC stamp', async ({
    page,
  }, testInfo: TestInfo) => {
    await openOsomaApp(page, '/guidelines/arrive-v2')
    await expect(page.getByRole('heading', { name: 'ARRIVE 2.0' })).toBeVisible()

    await page.getByLabel('Investigation', { exact: true }).selectOption({ label: DEMO_PROJECT_NAME })
    await expect(page.getByTestId('conformance-score')).toBeVisible({ timeout: 15000 })

    // Fill the first visible field manually and save it. Capture its field id
    // once, then re-locate by that stable testid — `.first()` re-resolves to
    // "whatever is first in the DOM right now" on every use, which can point at
    // a different field after the save triggers a section re-render.
    const firstRow = page.locator('[data-testid^="guideline-field-"]').first()
    await expect(firstRow).toBeVisible()
    const fieldId = (await firstRow.getAttribute('data-testid'))!.replace('guideline-field-', '')
    const row = page.getByTestId(`guideline-field-${fieldId}`)

    await row.getByLabel(`Value for ${fieldId}`).fill('E2E-filled value')
    await row.getByRole('button', { name: /^save$/i }).click()
    await expect(row.getByText('Satisfied')).toBeVisible({ timeout: 10000 })

    // Field-level QC: mark it reviewed.
    await row.getByTestId(`review-toggle-${fieldId}`).click()
    await expect(row.getByText(/^Reviewed by/i)).toBeVisible({ timeout: 10000 })

    // Report-level QC: approve the report.
    const reviewPanel = page.getByTestId('review-panel')
    await expect(reviewPanel).toBeVisible()
    await reviewPanel.getByTestId('approve-report').click()
    await expect(reviewPanel.getByTestId('review-status')).toHaveText(/approved/i, { timeout: 10000 })

    // The exported markdown report carries the sign-off stamp.
    const downloadPromise = page.waitForEvent('download', { timeout: 20000 })
    await page.getByRole('button', { name: /download report \(\.md\)/i }).click()
    const download = await downloadPromise
    const targetPath = testInfo.outputPath(download.suggestedFilename())
    await download.saveAs(targetPath)
    const stats = await stat(targetPath)
    expect(stats.size).toBeGreaterThan(0)
    const content = await readFile(targetPath, 'utf-8')
    expect(content).toMatch(/QC status: Approved/i)
  })
})
