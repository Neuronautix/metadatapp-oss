/**
 * Golden Demo smoke test
 *
 * Covers the end-to-end demo-critical flow:
 *  1. Authenticate and reach the investigations list
 *  2. Open the golden demo investigation ("Demo - DVC circadian...")
 *  3. Verify the linked DVC study renders
 *  4. Require non-empty linked DVC treatment and control evidence
 *  5. Trigger and validate the JSON-LD export
 *  6. Trigger and validate the RO-Crate export
 *  7. Trigger and validate the ARRIVE PDF export
 *
 * Requirements per AGENTS.md:
 *  - MSW must be disabled (localStorage.setItem('use_msw', 'false'))
 *  - Do NOT use page.waitForNetworkIdle() - use explicit selectors
 */

import { readFile, stat } from 'node:fs/promises'
import { test, expect, type Download, type Locator, type Page, type TestInfo } from '@playwright/test'
import JSZip from 'jszip'
import { openOsomaApp } from './helpers'

const DEMO_PROJECT_NAME = 'Demo - DVC circadian rhythm monitoring in Shank3 mice'
const DEMO_EXPERIMENT_NAME = 'DVC circadian activity acquisition'
const DEMO_CAGE_IDS = ['DVC-CAGE-KO-01', 'DVC-CAGE-WT-01'] as const
const DVC_WINDOW_FROM = '2026-03-03T00:00:00Z'
const DVC_WINDOW_TO = '2026-03-06T00:00:00Z'

type SavedDownload = {
  filename: string
  path: string
}

type DvcSeries = {
  cageId?: string
  ts?: unknown[]
  activity?: unknown[]
}

type DvcQuality = {
  cageId?: string
  sensorHealth?: string
}

type DvcComparePayload = {
  data?: DvcSeries[]
  context?: Array<{ cageId?: string }>
  quality?: DvcQuality[]
}

const escapeRegExp = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

const saveDownload = async (download: Download, testInfo: TestInfo): Promise<SavedDownload> => {
  const filename = download.suggestedFilename()
  const targetPath = testInfo.outputPath(filename)

  await download.saveAs(targetPath)

  const stats = await stat(targetPath)
  expect(stats.size, `${filename} should not be empty`).toBeGreaterThan(0)

  return { filename, path: targetPath }
}

const downloadFromControl = async (
  page: Page,
  testInfo: TestInfo,
  control: Locator,
  filenamePattern: RegExp,
): Promise<SavedDownload> => {
  await expect(control).toBeVisible({ timeout: 10000 })
  await expect(control).toBeEnabled({ timeout: 10000 })

  const downloadPromise = page.waitForEvent('download', { timeout: 20000 })
  await control.click()
  const download = await downloadPromise
  const saved = await saveDownload(download, testInfo)

  expect(saved.filename).toMatch(filenamePattern)

  return saved
}

const expectJsonLdDownload = async (saved: SavedDownload) => {
  const payload = JSON.parse((await readFile(saved.path)).toString('utf8')) as Record<string, unknown>
  const graph = Array.isArray(payload['@graph']) ? payload['@graph'] : []
  const root = graph[0] as Record<string, unknown> | undefined

  expect(payload['@context']).toBeTruthy()
  expect(root, 'JSON-LD document should contain a root @graph node').toBeTruthy()
  expect(typeof root?.['@id']).toBe('string')
}

const expectRoCrateDownload = async (saved: SavedDownload) => {
  const archive = await JSZip.loadAsync(await readFile(saved.path))
  const metadataFile = archive.file('ro-crate-metadata.json')

  expect(metadataFile, 'RO-Crate ZIP must contain ro-crate-metadata.json').toBeTruthy()

  const metadata = await metadataFile?.async('string')
  expect(metadata?.length ?? 0, 'RO-Crate metadata should not be empty').toBeGreaterThan(0)

  const parsed = JSON.parse(metadata ?? '{}') as Record<string, unknown>
  expect(parsed['@context']).toBeTruthy()
}

const expectPdfDownload = async (saved: SavedDownload) => {
  const content = await readFile(saved.path)

  expect(content.subarray(0, 5).toString('utf8')).toBe('%PDF-')
}

const expectGoldenDvcEvidence = async (page: Page) => {
  const result = await page.evaluate(
    async ({ cageIds, from, to }) => {
      const session = JSON.parse(localStorage.getItem('metadatapp_auth_session') ?? '{}') as { accessToken?: string }
      const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Bypass-Mock': 'true',
      }

      if (session.accessToken) {
        headers.Authorization = `Bearer ${session.accessToken}`
      }

      const query = new URLSearchParams({
        cageIds: cageIds.join(','),
        from,
        to,
      })
      const response = await fetch(`/api/dvc/compare?${query.toString()}`, { headers })
      const text = await response.text()
      let payload: unknown = null

      try {
        payload = text ? JSON.parse(text) : null
      } catch {
        payload = { raw: text.slice(0, 500) }
      }

      return {
        ok: response.ok,
        status: response.status,
        payload,
      }
    },
    {
      cageIds: [...DEMO_CAGE_IDS],
      from: DVC_WINDOW_FROM,
      to: DVC_WINDOW_TO,
    },
  )

  expect(result.ok, `DVC compare request failed with HTTP ${result.status}`).toBe(true)

  const payload = (result.payload ?? {}) as DvcComparePayload
  const seriesList = Array.isArray(payload.data) ? payload.data : []
  const contextList = Array.isArray(payload.context) ? payload.context : []
  const qualityList = Array.isArray(payload.quality) ? payload.quality : []

  expect(seriesList).toHaveLength(DEMO_CAGE_IDS.length)
  expect(contextList).toHaveLength(DEMO_CAGE_IDS.length)
  expect(qualityList).toHaveLength(DEMO_CAGE_IDS.length)

  for (const cageId of DEMO_CAGE_IDS) {
    const series = seriesList.find((candidate) => candidate.cageId === cageId)
    expect(series, `Missing DVC activity series for ${cageId}`).toBeTruthy()

    const timestamps = Array.isArray(series?.ts) ? series.ts : []
    const activity = Array.isArray(series?.activity) ? series.activity : []

    expect(timestamps.length, `Timestamp count should match activity count for ${cageId}`).toBe(activity.length)
    expect(activity.length, `DVC activity should not be empty for ${cageId}`).toBeGreaterThan(0)
    expect(
      activity.some((value) => typeof value === 'number' && value > 0),
      `DVC activity should include positive values for ${cageId}`,
    ).toBe(true)

    const quality = qualityList.find((candidate) => candidate.cageId === cageId)
    expect(quality?.sensorHealth, `DVC sensor health should be good for ${cageId}`).toBe('good')
  }
}

test.beforeEach(async ({ page }) => {
  await page.addInitScript(() => localStorage.setItem('use_msw', 'false'))
})

test('Golden demo investigation renders linked DVC evidence and exports', async ({ page }, testInfo) => {
  test.setTimeout(150000)

  await test.step('Authenticate and reach Investigations', async () => {
    await openOsomaApp(page)
    await expect(page.getByRole('link', { name: 'Investigations' }).first()).toBeVisible({ timeout: 20000 })
  })

  await test.step('Open the golden demo investigation', async () => {
    await page.getByRole('link', { name: 'Investigations' }).first().click()
    await expect(page.getByRole('heading', { name: /Investigations/i })).toBeVisible({ timeout: 10000 })

    const demoLink = page
      .getByRole('link', { name: new RegExp(escapeRegExp(DEMO_PROJECT_NAME), 'i') })
      .first()

    await expect(demoLink, 'Golden demo investigation fixture must be seeded').toBeVisible({ timeout: 15000 })
    await demoLink.click()
  })

  await test.step('Investigation detail page renders', async () => {
    await expect(
      page.getByText(new RegExp(escapeRegExp(DEMO_PROJECT_NAME.slice(0, 30)), 'i'))
        .or(page.getByRole('heading', { name: /DVC circadian/i }))
    ).toBeVisible({ timeout: 10000 })
  })

  await test.step('Navigate to Studies and open golden study', async () => {
    await page.getByRole('link', { name: 'Studies' }).first().click()
    await expect(page.getByRole('heading', { name: 'Studies' })).toBeVisible({ timeout: 10000 })

    await page.getByPlaceholder('Search study, protocol, investigation').fill('DVC circadian')

    const studyLink = page
      .getByRole('link', { name: new RegExp(escapeRegExp(DEMO_EXPERIMENT_NAME), 'i') })
      .first()

    await expect(studyLink, 'Golden demo DVC study fixture must be seeded').toBeVisible({ timeout: 15000 })
    await studyLink.click()

    await expect(page.getByRole('heading', { name: new RegExp(escapeRegExp(DEMO_EXPERIMENT_NAME), 'i') })).toBeVisible({ timeout: 10000 })
    await expect(page.getByText(/Shank3|DVC|Tecniplast|circadian/i).first()).toBeVisible({ timeout: 10000 })
  })

  await test.step('Golden DVC treatment and control evidence is non-empty', async () => {
    await expectGoldenDvcEvidence(page)
  })

  await test.step('Export page targets the golden fixture', async () => {
    await page.getByRole('link', { name: /FAIR Export|Export/i }).first().click()
    await expect(page.getByRole('heading', { name: /RO-Crate & data exports|Exports/i })).toBeVisible({ timeout: 10000 })

    const investigationSelect = page.locator('select').first()
    await expect(investigationSelect, 'Golden demo investigation must be selectable for exports').toContainText(DEMO_PROJECT_NAME, { timeout: 15000 })
    await investigationSelect.selectOption({ label: DEMO_PROJECT_NAME })

    const studySelect = page.locator('select').nth(1)
    await expect(studySelect, 'Golden demo study must be selectable for exports').toContainText(DEMO_EXPERIMENT_NAME, { timeout: 15000 })
    await studySelect.selectOption({ label: DEMO_EXPERIMENT_NAME })
  })

  await test.step('Export - JSON-LD download is valid', async () => {
    const saved = await downloadFromControl(
      page,
      testInfo,
      page.getByRole('button', { name: /^Export JSON-LD$/ }),
      /\.jsonld?$|fair2/i,
    )

    await expectJsonLdDownload(saved)
  })

  await test.step('Export - RO-Crate ZIP download is valid', async () => {
    const saved = await downloadFromControl(
      page,
      testInfo,
      page.getByRole('button', { name: /^Export as RO-Crate$/ }),
      /\.zip$/i,
    )

    await expectRoCrateDownload(saved)
  })

  await test.step('Export - ARRIVE PDF download is valid', async () => {
    const saved = await downloadFromControl(
      page,
      testInfo,
      page.getByRole('button', { name: /^Export ARRIVE PDF$/ }),
      /\.pdf$/i,
    )

    await expectPdfDownload(saved)
  })
})

test('Golden demo DVC evidence remains available through the live API', async ({ page }) => {
  test.setTimeout(60000)

  await openOsomaApp(page)
  await expect(page.getByRole('link', { name: 'Investigations' }).first()).toBeVisible({ timeout: 20000 })
  await expectGoldenDvcEvidence(page)
  await expect(page.getByText(/Something went wrong|Unhandled|TypeError|ReferenceError/i)).not.toBeVisible()
})
