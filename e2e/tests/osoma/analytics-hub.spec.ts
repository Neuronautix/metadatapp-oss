import { readFile, stat } from 'node:fs/promises'
import { test, expect } from '@playwright/test'
import JSZip from 'jszip'
import { openOsomaApp } from './helpers'

const TEST_CAGE_ID = 'S81P-40648'
const TEST_START = '2026-02-18T00:00:00'
const TEST_STOP = '2026-02-19T00:00:00'

test.describe('Osoma Analytics Hub', () => {
    test.beforeEach(async ({ page }) => {
        await page.addInitScript(() => {
            localStorage.setItem('use_msw', 'false')
            localStorage.removeItem('dvc-extraction-jobs')
        })
    })

    test('submits a live DVC extraction, downloads the ZIP, and visualizes the result', async ({ page }, testInfo) => {
        test.setTimeout(240000)

        await openOsomaApp(page, '/')
        await page.goto('/dvc/analytics', { waitUntil: 'domcontentloaded' })
        await expect(page.getByRole('link', { name: 'Analytics Hub' })).toHaveAttribute('aria-current', 'page')
        await expect(page.getByRole('heading', { name: 'Analytics Hub' })).toBeVisible()

        await page.getByRole('button', { name: 'Live API Extraction' }).click()
        await expect(page.getByText('DVC Connection Console')).toBeVisible()

        await page.locator('textarea').fill(TEST_CAGE_ID)
        await page.getByRole('button', { name: 'Check Availability' }).click()

        await expect(page.getByText(TEST_CAGE_ID)).toBeVisible({ timeout: 30000 })
        await page.getByRole('button', { name: 'Proceed to Configuration' }).click()

        await expect(page.getByText('Step 2: Task Configuration')).toBeVisible()
        await page.locator('#window-mode-shared').check()
        await page.locator('input[id="metric-ACTIVATION"]').check()

        await page.evaluate(({ start, stop }) => {
            const inputs = Array.from(document.querySelectorAll<HTMLInputElement>('input[type="datetime-local"]'))
            const values = [start, stop]

            inputs.forEach((input, index) => {
                input.value = values[index]
                input.dispatchEvent(new Event('input', { bubbles: true }))
                input.dispatchEvent(new Event('change', { bubbles: true }))
            })
        }, {
            start: TEST_START,
            stop: TEST_STOP,
        })

        await page.getByRole('button', { name: 'Review & Submit' }).click()
        await expect(page.getByText('Step 3: Submit Payload Details')).toBeVisible()
        await expect(page.getByText(`Metrics: ACTIVATION`)).toBeVisible()

        await page.getByRole('button', { name: 'Initiate Server Polling' }).click()
        await expect(page.getByText('Step 4: Realtime Polling')).toBeVisible()

        const taskRow = page.locator('tbody tr').first()
        await expect(taskRow).toBeVisible({ timeout: 30000 })
        await expect(taskRow).toContainText('targets')

        await expect.poll(async () => {
            return taskRow.innerText()
        }, {
            timeout: 180000,
            intervals: [1000, 5000, 5000],
        }).toContain('COMPLETED')

        const downloadPromise = page.waitForEvent('download')
        await page.getByRole('button', { name: 'Download ZIP' }).click()
        const download = await downloadPromise

        const zipPath = testInfo.outputPath(download.suggestedFilename())
        await download.saveAs(zipPath)

        const zipStats = await stat(zipPath)
        const archive = await JSZip.loadAsync(await readFile(zipPath))
        expect(download.suggestedFilename()).toMatch(/\.zip$/i)
        expect(zipStats.size).toBeGreaterThan(0)
        expect(Object.keys(archive.files).some((name) => name.toLowerCase().endsWith('.csv'))).toBe(true)
        expect(Object.keys(archive.files).some((name) => name.includes('animal_locomotion_index'))).toBe(true)

        await page.getByRole('button', { name: 'Extract & Visualize' }).click()
        await expect(page.getByText(/Successfully parsed/i)).toBeVisible({ timeout: 30000 })
        await expect(page.getByText(/temporal records/i)).toBeVisible()
    })
})
