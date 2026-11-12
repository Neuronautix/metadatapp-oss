import { expect, test, type Page, type Response } from '@playwright/test'
import { openOsomaApp } from './helpers'

const routes = [
  { path: '/', label: 'dashboard' },
  { path: '/investigations', label: 'investigations' },
  { path: '/studies', label: 'studies' },
  { path: '/samples', label: 'samples' },
  { path: '/subjects', label: 'subjects' },
  { path: '/assays', label: 'assays' },
  { path: '/datasets', label: 'datasets', expectedPath: '/metadata' },
  { path: '/calendar', label: 'calendar' },
  { path: '/assistant', label: 'assistant' },
  { path: '/metadata', label: 'metadata catalog' },
  { path: '/metadata/projects', label: 'metadata projects' },
  { path: '/metadata/experiments', label: 'metadata experiments' },
  { path: '/metadata/procedures', label: 'metadata procedures' },
  { path: '/metadata/subjects', label: 'metadata subjects' },
  { path: '/metadata/cages', label: 'metadata cages' },
  { path: '/metadata/connected_apps', label: 'metadata connected apps' },
  { path: '/metadata/laboratories', label: 'metadata laboratories' },
  { path: '/metadata/weight_measurements', label: 'metadata weight measurements' },
  { path: '/connected-apps', label: 'connected apps' },
  { path: '/settings/profile', label: 'settings profile' },
  { path: '/settings/appearance', label: 'settings appearance' },
  { path: '/settings/docs', label: 'settings docs' },
  { path: '/settings/api-keys', label: 'settings API keys' },
  { path: '/settings/AI-providers', label: 'settings AI providers' },
  { path: '/settings/ai-providers', label: 'lowercase settings AI providers', expectedPath: '/settings/AI-providers' },
  { path: '/settings/ai-provider', label: 'legacy settings AI provider', expectedPath: '/settings/AI-providers' },
  { path: '/settings/ai-proveider', label: 'misspelled settings AI provider', expectedPath: '/settings/AI-providers' },
  { path: '/admin/feature-flags', label: 'feature flags' },
  { path: '/import/csv', label: 'CSV import' },
  { path: '/data-entry', label: 'data entry' },
  { path: '/tp', label: 'operations' },
  { path: '/tp/map', label: 'facility map' },
  { path: '/tp/alarms', label: 'alarms' },
  { path: '/tp/conditions', label: 'conditions' },
  { path: '/tp/sensors', label: 'sensors' },
  { path: '/dvc/metadata-fusion', label: 'metadata fusion' },
  { path: '/hcm/comparison', label: 'HCM comparison' },
  { path: '/dvc/analytics', label: 'analytics hub' },
  { path: '/dvc/activity', label: 'activity explorer' },
  { path: '/atmp', label: 'ATMP' },
  { path: '/nam-vcg', label: 'NAM VCG' },
  { path: '/fair-navigator', label: 'FAIR navigator' },
  { path: '/zefix', label: 'ZEFIX' },
  { path: '/zefix/lines', label: 'ZEFIX lines' },
  { path: '/zefix/batches', label: 'ZEFIX batches' },
  { path: '/zefix/systems', label: 'ZEFIX systems' },
  { path: '/zefix/rooms', label: 'ZEFIX rooms' },
  { path: '/zefix/alerts', label: 'ZEFIX alerts' },
  { path: '/zefix/location', label: 'ZEFIX location' },
] as const

const failureText = [
  'Gateway Timeout',
  'Page not found',
  'Resource not found',
  'Something went wrong',
  'Schema missing',
  'Listing disabled',
  'Resource unavailable',
] as const

function isRelevantFailureResponse(response: Response) {
  const request = response.request()
  const status = response.status()
  const resourceType = request.resourceType()
  const url = response.url()

  if (status >= 500) {
    return true
  }

  if (status === 404 && ['document', 'script', 'stylesheet'].includes(resourceType)) {
    return true
  }

  if (status === 404 && url.includes('/api/')) {
    return true
  }

  return false
}

function installFrontendGuards(page: Page) {
  const failures: string[] = []

  page.on('pageerror', (error) => {
    failures.push(`pageerror: ${error.message}`)
  })

  page.on('console', (message) => {
    if (message.type() === 'error') {
      failures.push(`console.error: ${message.text()}`)
    }
  })

  page.on('requestfailed', (request) => {
    const errorText = request.failure()?.errorText ?? 'unknown error'
    if (errorText.includes('net::ERR_ABORTED')) {
      return
    }

    failures.push(`requestfailed ${request.resourceType()} ${request.url()}: ${errorText}`)
  })

  page.on('response', (response) => {
    if (isRelevantFailureResponse(response)) {
      failures.push(`response ${response.status()} ${response.request().resourceType()} ${response.url()}`)
    }
  })

  return failures
}

async function expectNoVisibleFailureState(page: Page) {
  for (const text of failureText) {
    await expect(page.getByText(text).first()).not.toBeVisible()
  }
}

test.describe('Osoma app route health', () => {
  test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
      localStorage.setItem('use_msw', 'false')
    })
  })

  for (const route of routes) {
    test(`${route.label} route loads directly and survives reload`, async ({ page }) => {
      const failures = installFrontendGuards(page)
      const expectedPath = 'expectedPath' in route ? route.expectedPath : route.path

      await openOsomaApp(page, route.path)
      await expect(page).toHaveURL(new RegExp(`${expectedPath.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?:[/?#].*)?$`))
      await expect(page.locator('main')).toBeVisible()
      await expectNoVisibleFailureState(page)

      await page.reload({ waitUntil: 'domcontentloaded' })
      await expect(page).toHaveURL(new RegExp(`${expectedPath.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?:[/?#].*)?$`))
      await expect(page.locator('main')).toBeVisible()
      await expectNoVisibleFailureState(page)

      expect(failures, failures.join('\n')).toEqual([])
    })
  }
})
