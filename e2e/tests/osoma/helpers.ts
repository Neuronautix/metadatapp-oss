import { expect, Page } from '@playwright/test'

const GATEWAY_TIMEOUT_TEXT = 'Gateway Timeout'
const NAVIGATION_TIMEOUT = 12000
const APP_READY_TEXT = /METADATAPP|All-in-One Metadata Management Platform|Logout|Mock User|User/

async function isGatewayTimeoutVisible(page: Page, timeout = 1500) {
    return page.getByText(GATEWAY_TIMEOUT_TEXT).isVisible({ timeout }).catch(() => false)
}

async function navigate(page: Page, path: string) {
    await page.goto(path, { waitUntil: 'domcontentloaded', timeout: NAVIGATION_TIMEOUT })
}

export async function recoverFromGatewayTimeout(page: Page, path = '/') {
    for (let attempt = 0; attempt < 3; attempt += 1) {
        if (!(await isGatewayTimeoutVisible(page))) {
            return
        }

        if (attempt === 2) {
            break
        }

        await page.waitForTimeout(1000)
        await navigate(page, path).catch(() => undefined)
    }

    await expect(page.getByText(GATEWAY_TIMEOUT_TEXT)).not.toBeVisible()
}

export async function openOsomaApp(page: Page, path = '/') {
    const appReadyMarker = page.getByText(APP_READY_TEXT).first()
    const loginButton = page.getByRole('button', { name: 'Login with Keycloak' })

    await navigate(page, path).catch(() => undefined)
    await recoverFromGatewayTimeout(page, path)

    if (await appReadyMarker.isVisible({ timeout: 5000 }).catch(() => false)) {
        return
    }

    if (await loginButton.isVisible({ timeout: 5000 }).catch(() => false)) {
        await loginButton.click()

        await page.waitForURL(/\/auth\/callback|osoma\.metadatapp\.test/, {
            timeout: 30000,
        }).catch(() => undefined)

        await expect(appReadyMarker).toBeVisible({ timeout: 30000 })
        return
    }

    await expect(appReadyMarker).toBeVisible({ timeout: 30000 })
}
