import { test, expect, type Locator, type Page } from '@playwright/test';
import { openOsomaApp } from './helpers';
import { ROLE_STORAGE_KEY } from '../../../osoma/src/lib/rbac';

type ZefixUserRole = 'VIEWER' | 'EDITOR' | 'ADMIN';
type ZefixAlertStatus = 'active' | 'acknowledged' | 'resolved';
type ZefixAlertSummary = {
    alertID: string;
    type: string;
    status: ZefixAlertStatus;
};
type ApiResponse = {
    status: number;
    ok: boolean;
    payload: unknown;
    text: string;
};

const getCardByHeading = (page: Page, title: string): Locator =>
    page.getByRole('heading', { name: title, exact: true }).locator('..').locator('..');

const getNumberInputForLabel = (page: Page | Locator, label: string): Locator =>
    page.locator('div').filter({ has: page.getByText(label, { exact: true }) }).locator('input[type="number"]').first();

async function switchRole(page: Page, role: ZefixUserRole) {
    await page.addInitScript(({ storageKey, nextRole }: { storageKey: string; nextRole: ZefixUserRole }) => {
        localStorage.setItem(storageKey, nextRole);
    }, { storageKey: ROLE_STORAGE_KEY, nextRole: role });
}

async function selectEmptyNorthPool(page: Page) {
    await getCardByHeading(page, 'Plateaux').getByRole('button', { name: /^North Aquatics Platform\b/ }).click();
    await getCardByHeading(page, 'Rooms').getByRole('button', { name: /^Room 101\b/ }).click();
    await getCardByHeading(page, 'Racks').getByRole('button', { name: /^N-01\b/ }).click();
    await getCardByHeading(page, 'Pools').getByRole('button', { name: /^A2\b/ }).click();
}

async function apiRequest(
    page: Page,
    path: string,
    init: { method?: string; body?: unknown } = {},
): Promise<ApiResponse> {
    return page.evaluate(async ({ path, init }) => {
        const rawSession = localStorage.getItem('metadatapp_auth_session');
        let accessToken: string | undefined;
        if (rawSession) {
            try {
                const session = JSON.parse(rawSession) as { accessToken?: unknown };
                accessToken = typeof session.accessToken === 'string' ? session.accessToken : undefined;
            } catch {
                accessToken = undefined;
            }
        }
        const headers: Record<string, string> = {
            Accept: 'application/ld+json, application/json;q=0.9',
        };

        if (accessToken) {
            headers.Authorization = `Bearer ${accessToken}`;
        }

        if (init.body !== undefined) {
            headers['Content-Type'] = 'application/ld+json';
        }

        const response = await fetch(`/api${path}`, {
            method: init.method ?? 'GET',
            headers,
            body: init.body !== undefined ? JSON.stringify(init.body) : undefined,
        });

        const text = await response.text();
        let payload: unknown = null;
        if (text !== '') {
            try {
                payload = JSON.parse(text);
            } catch {
                payload = null;
            }
        }

        return {
            status: response.status,
            ok: response.ok,
            payload,
            text,
        };
    }, { path, init });
}

function isZefixAlertSummary(value: unknown): value is ZefixAlertSummary {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const alert = value as Record<string, unknown>;
    return typeof alert.alertID === 'string'
        && typeof alert.type === 'string'
        && (alert.status === 'active' || alert.status === 'acknowledged' || alert.status === 'resolved');
}

async function ensureNorthPoolIsEmpty(page: Page) {
    await selectEmptyNorthPool(page);

    const poolDetail = getCardByHeading(page, 'Pool detail');
    const noBatchMessage = poolDetail.getByText('No active batch present in this pool.');
    if (await noBatchMessage.isVisible()) {
        return;
    }

    const batchId = await extractBatchId(poolDetail);
    const deleteResponse = await apiRequest(page, `/zebrafish_batches/${batchId}`, { method: 'DELETE' });
    expect(deleteResponse.status).toBe(204);

    await page.goto('/zefix/location', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Location Explorer' })).toBeVisible();
    await selectEmptyNorthPool(page);
    await expect(getCardByHeading(page, 'Pool detail').getByText('No active batch present in this pool.')).toBeVisible();
}

async function resolveOpenTemperatureAlerts(page: Page) {
    const alertsResponse = await apiRequest(page, '/zefix/alerts');
    expect(alertsResponse.ok).toBeTruthy();

    const alerts = Array.isArray(alertsResponse.payload)
        ? alertsResponse.payload.filter(isZefixAlertSummary)
        : [];
    const unresolvedTemperatureAlerts = alerts.filter((alert) =>
        alert.type === 'temperature outside range' && alert.status !== 'resolved'
    );

    for (const alert of unresolvedTemperatureAlerts) {
        if (alert.status === 'active') {
            const acknowledgeResponse = await apiRequest(page, `/zefix/alerts/${alert.alertID}/acknowledge`, { method: 'POST' });
            expect(acknowledgeResponse.ok).toBeTruthy();
        }

        const resolveResponse = await apiRequest(page, `/zefix/alerts/${alert.alertID}/resolve`, { method: 'POST' });
        expect(resolveResponse.ok).toBeTruthy();
    }
}

async function extractBatchId(poolDetail: Locator) {
    const batchLine = poolDetail.locator('p').filter({ hasText: /^Batch:/ }).first();
    await expect(batchLine).toBeVisible();

    const batchText = await batchLine.textContent();
    const batchId = batchText?.match(/Batch:\s*(.+)$/)?.[1]?.trim();

    expect(batchId).toBeTruthy();

    return batchId as string;
}

test.beforeEach(async ({ page }) => {
    await page.addInitScript(() => {
        localStorage.setItem('use_msw', 'false');
    });
});

test('admin can run the main Zefix workflows through the real UI', async ({ page }) => {
    test.setTimeout(120_000);

    await switchRole(page, 'ADMIN');
    await openOsomaApp(page, '/zefix/location');
    await expect(page.getByRole('heading', { name: 'Location Explorer' })).toBeVisible();

    await resolveOpenTemperatureAlerts(page);
    await ensureNorthPoolIsEmpty(page);

    const poolDetail = getCardByHeading(page, 'Pool detail');
    await expect(poolDetail.getByText('No active batch present in this pool.')).toBeVisible();

    const [occupancyExportResponse] = await Promise.all([
        page.waitForResponse((response) =>
            response.request().method() === 'GET' &&
            response.url().includes('/zefix/exports/pool-occupancy.csv')
        ),
        page.getByRole('button', { name: 'Export occupancy CSV' }).click(),
    ]);
    expect(occupancyExportResponse.ok()).toBeTruthy();

    await poolDetail.getByRole('button', { name: 'Create batch here' }).click();

    const createBatchDialog = page.getByRole('dialog');
    await expect(createBatchDialog.getByRole('heading', { name: /Create batch in A2/ })).toBeVisible();

    const lineSelect = createBatchDialog.locator('select').first();
    await expect(lineSelect).not.toHaveValue('');
    await createBatchDialog.locator('input[type="number"]').nth(0).fill('2');
    await createBatchDialog.locator('input[type="number"]').nth(1).fill('1');
    await createBatchDialog.locator('input[type="number"]').nth(2).fill('0');

    const [createBatchResponse] = await Promise.all([
        page.waitForResponse((response) =>
            response.request().method() === 'POST' &&
            response.url().includes('/zebrafish_batches.jsonld')
        ),
        createBatchDialog.getByRole('button', { name: 'Create batch' }).click(),
    ]);
    expect(createBatchResponse.status()).toBe(201);

    await expect(page.getByText('Batch created')).toBeVisible();
    await expect(poolDetail.getByText(/Fish count:\s*3/)).toBeVisible();

    const batchId = await extractBatchId(poolDetail);

    await page.goto(`/zefix/batches/${batchId}`, { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: `Batch ${batchId}` })).toBeVisible();

    const [mortalityExportResponse] = await Promise.all([
        page.waitForResponse((response) =>
            response.request().method() === 'GET' &&
            response.url().includes('/zefix/exports/mortality-logs.csv')
        ),
        page.getByRole('button', { name: 'Export mortality CSV' }).click(),
    ]);
    expect(mortalityExportResponse.ok()).toBeTruthy();

    await getNumberInputForLabel(page, 'Count').fill('2');
    await page.locator('textarea').fill('Playwright mortality check');

    const [mortalityCreateResponse] = await Promise.all([
        page.waitForResponse((response) =>
            response.request().method() === 'POST' &&
            response.url().includes('/mortality_records.jsonld')
        ),
        page.getByRole('button', { name: 'Save mortality entry' }).click(),
    ]);
    expect(mortalityCreateResponse.status()).toBe(201);

    await expect(page.getByText('Mortality recorded')).toBeVisible();
    await expect(page.getByText('Playwright mortality check')).toBeVisible();
    await expect(page.locator('div').filter({ has: page.getByText('Current population') }).first().getByText('1')).toBeVisible();
    await expect(page.locator('div').filter({ has: page.getByText('Mortalities') }).first().getByText('2')).toBeVisible();

    await page.goto('/zefix/systems', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Systems' })).toBeVisible();

    await getNumberInputForLabel(page, 'Value').fill('30.4');
    await page.getByPlaceholder('Optional context').fill('Playwright heat spike');

    const [observationResponse] = await Promise.all([
        page.waitForResponse((response) =>
            response.request().method() === 'POST' &&
            response.url().includes('/zefix/environmental-observations')
        ),
        page.getByRole('button', { name: 'Save observation' }).click(),
    ]);
    expect(observationResponse.status()).toBe(201);

    await expect(page.getByText('Observation recorded')).toBeVisible();
    await expect(page.getByText('Playwright heat spike')).toBeVisible();
    await expect(page.getByText(/30\.4/)).toBeVisible();

    await page.goto('/zefix/alerts', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Alerts' })).toBeVisible();

    const temperatureAlert = page.locator('div').filter({
        has: page.getByRole('button', { name: 'Acknowledge' }),
        hasText: 'temperature outside range',
    }).first();

    await expect(temperatureAlert).toBeVisible();

    const [acknowledgeResponse] = await Promise.all([
        page.waitForResponse((response) =>
            response.request().method() === 'POST' &&
            response.url().includes('/acknowledge')
        ),
        temperatureAlert.getByRole('button', { name: 'Acknowledge' }).click(),
    ]);
    expect(acknowledgeResponse.ok()).toBeTruthy();
    await expect(temperatureAlert.getByText('acknowledged')).toBeVisible();

    const [resolveResponse] = await Promise.all([
        page.waitForResponse((response) =>
            response.request().method() === 'POST' &&
            response.url().includes('/resolve')
        ),
        temperatureAlert.getByRole('button', { name: 'Resolve' }).click(),
    ]);
    expect(resolveResponse.ok()).toBeTruthy();
    await expect(temperatureAlert.getByText('resolved')).toBeVisible();

    const deleteBatchResponse = await apiRequest(page, `/zebrafish_batches/${batchId}`, { method: 'DELETE' });
    expect(deleteBatchResponse.status).toBe(204);
});

test.describe('viewer restrictions', () => {
    test.use({ storageState: 'storageState/user.json' });

    test('viewer role stays read-only across Zefix workflows', async ({ page }) => {
        await switchRole(page, 'VIEWER');
        await openOsomaApp(page, '/zefix/location');
        await expect(page.getByRole('heading', { name: 'Location Explorer' })).toBeVisible();

        await selectEmptyNorthPool(page);

        const poolDetail = getCardByHeading(page, 'Pool detail');
        await expect(poolDetail.getByRole('button', { name: 'Create batch here' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Export occupancy CSV' })).toHaveCount(0);

        await page.goto('/zefix/systems', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: 'Systems' })).toBeVisible();
        await expect(page.getByText('Recording environmental observations requires editor access.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Save observation' })).toHaveCount(0);

        await page.goto('/zefix/lines', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: 'Line Management' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Export CSV' })).toHaveCount(0);

        await page.goto('/zefix/batches', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: 'Batch / Lot Management' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Export CSV' })).toHaveCount(0);
        await page.getByRole('table').locator('tbody').getByRole('link').first().click();

        await expect(page.getByText('Recording mortalities and exporting mortality logs require editor access.')).toBeVisible();
        await expect(page.getByRole('button', { name: 'Save mortality entry' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Export mortality CSV' })).toHaveCount(0);

        await page.goto('/zefix/alerts', { waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('heading', { name: 'Alerts' })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Acknowledge' })).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Resolve' })).toHaveCount(0);
    });
});
