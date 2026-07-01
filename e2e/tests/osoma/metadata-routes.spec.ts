import { test, expect, type Page } from '@playwright/test'
import { openOsomaApp } from './helpers'

const routes = [
  '/metadata',
  '/metadata/projects',
  '/metadata/experiments',
  '/metadata/procedures',
  '/metadata/subjects',
  '/metadata/cages',
  '/metadata/connected_apps',
  '/metadata/laboratories',
  '/metadata/weight_measurements',
  '/metadata/species',
] as const

const failureStates = [
  'Gateway Timeout',
  'Page not found',
  'Resource not found',
  'Schema missing',
  'Listing disabled',
  'Resource unavailable',
  'Something went wrong',
] as const

async function disableMsw(page: Page) {
  await page.addInitScript(() => {
    localStorage.setItem('use_msw', 'false')
  })
}

async function expectNoFailureState(page: Page) {
  for (const message of failureStates) {
    await expect(page.getByText(message)).not.toBeVisible()
  }
}

async function expectMetadataIndex(page: Page) {
  await expect(page.getByRole('heading', { name: 'Metadata Catalog' })).toBeVisible()
  await expect(page.getByPlaceholder('Search collections by name or API key')).toBeVisible()
  await expect(page.getByText('Catalog overview')).toBeVisible()
  await expect(page.getByRole('link', { name: /Open collection/ }).first()).toBeVisible()
}

async function expectMetadataCollection(page: Page) {
  await expect(page.locator('h1').first()).toBeVisible()
  await expect(page.getByText('MAPP API collection:')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Draft review filters' })).toBeVisible()
  await expect(page.getByText('Collection').first()).toBeVisible()
  await expect(page.getByText(/rows on this page/)).toBeVisible()
  await expect(page.getByRole('columnheader', { name: 'Actions' })).toBeVisible()

  const createAction = page.getByRole('link', { name: /^New / }).first()
  if (await createAction.isVisible().catch(() => false)) {
    await expect(createAction).toBeVisible()
  }
}

async function expectIsaCollectionLabel(page: Page, route: string) {
  const expectedLabels: Record<string, string> = {
    '/metadata/projects': 'Investigations',
    '/metadata/experiments': 'Studies',
    '/metadata/procedures': 'Assays',
  }
  const label = expectedLabels[route]
  if (!label) return

  await expect(page.getByRole('heading', { name: label }).first()).toBeVisible()
}

test.describe('Osoma metadata smoke coverage', () => {
  test.beforeEach(async ({ page }) => {
    await disableMsw(page)
  })

  test('current metadata routes render catalog and collections', async ({ page }) => {
    for (const route of routes) {
      await test.step(`open ${route}`, async () => {
        await openOsomaApp(page, route)
        await expect(page).toHaveURL(new RegExp(`${route.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?:[/?#].*)?$`))

        if (route === '/metadata') {
          await expectMetadataIndex(page)
        } else {
          await expectMetadataCollection(page)
          await expectIsaCollectionLabel(page, route)
        }

        await expectNoFailureState(page)
      })
    }
  })
})
