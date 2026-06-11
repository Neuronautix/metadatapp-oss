import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import type { Study } from '@/domain/resources.ts'
import type { MetaStudy } from '@/metadatapp/types.ts'
import { mapMetaStudy } from '@/metadatapp/adapters.ts'
import type { ConnectedAppsResponse, ConnectedApp } from './connected-apps.types.ts'

type EnumLike = {
    value?: unknown
    name?: unknown
    code?: unknown
}

type RawConnectedApp = Omit<ConnectedApp, 'code' | 'isActive'> & {
    code?: ConnectedApp['code'] | EnumLike
    active?: boolean
    isActive?: boolean
}

const appCodes = new Set<ConnectedApp['code']>([
    'elabftw',
    'fair3r',
    'osf',
    'protocolio',
    'preclinicaltrials',
    'cedar',
    'bioportal',
    'tecniplast',
    'sensor_agent',
])

const CONNECTED_APP_CATALOG: ConnectedApp[] = [
    {
        id: 'elabftw',
        '@id': '/connected_apps/elabftw',
        name: 'ElabFTW',
        code: 'elabftw',
        description: 'Open source electronic lab notebook for researchers.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: '/images/apps/elabftw.svg',
        externalUrl: 'https://your-elabftw.example',
    },
    {
        id: 'fair3r',
        '@id': '/connected_apps/fair3r',
        name: 'Fair3R',
        code: 'fair3r',
        description: 'FAIR data repository integration for dataset validation and publication.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: '/images/apps/fair3r.svg',
        externalUrl: 'https://validation.fair3r.fr',
    },
    {
        id: 'osf',
        '@id': '/connected_apps/osf',
        name: 'OSF',
        code: 'osf',
        description: 'Open Science Framework repository integration for deposits and project metadata.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: '/images/apps/osf.svg',
        externalUrl: 'https://osf.io',
    },
    {
        id: 'protocolio',
        '@id': '/connected_apps/protocolio',
        name: 'protocols.io',
        code: 'protocolio',
        description: 'Protocol repository integration for live connection testing.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: '/images/apps/protocols_io.svg',
        externalUrl: 'https://www.protocols.io',
    },
    {
        id: 'preclinicaltrials',
        '@id': '/connected_apps/preclinicaltrials',
        name: 'PreclinicalTrials.eu',
        code: 'preclinicaltrials',
        description: 'Registry integration for importing preclinical trial protocol metadata.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: '/images/apps/preclinicaltrials.svg',
        externalUrl: 'https://preclinicaltrials.eu/api/external/viewable-protocols',
    },
    {
        id: 'cedar',
        '@id': '/connected_apps/cedar',
        name: 'CEDAR',
        code: 'cedar',
        description: 'Metadata template API integration.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: '/images/apps/cedar.svg',
        externalUrl: 'https://resource.metadatacenter.org',
    },
    {
        id: 'bioportal',
        '@id': '/connected_apps/bioportal',
        name: 'BioPortal',
        code: 'bioportal',
        description: 'Ontology API integration for live connection testing.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: '/images/apps/bioportal.svg',
        externalUrl: 'https://data.bioontology.org',
    },
    {
        id: 'tecniplast',
        '@id': '/connected_apps/tecniplast',
        name: 'Tecniplast DVC',
        code: 'tecniplast',
        description: 'Tecniplast DVC Analytics integration.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: '/images/apps/tecniplast.svg',
        externalUrl: 'https://your-dvc-api.example',
    },
    {
        id: 'sensor_agent',
        '@id': '/connected_apps/sensor_agent',
        name: 'Sovereign Sensor Agent',
        code: 'sensor_agent',
        description: 'Read-only live sensor proxy for Raspberry Pi observations.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: null,
    },
]

const toAppCode = (value: unknown): ConnectedApp['code'] | null => {
    if (typeof value !== 'string') return null
    const normalized = value.toLowerCase()
    return appCodes.has(normalized as ConnectedApp['code']) ? (normalized as ConnectedApp['code']) : null
}

const normalizeCode = (code: RawConnectedApp['code']): ConnectedApp['code'] => {
    const stringCode = toAppCode(code)
    if (stringCode) {
        return stringCode
    }

    if (code && typeof code === 'object') {
        return toAppCode(code.value) ?? toAppCode(code.code) ?? toAppCode(code.name) ?? 'elabftw'
    }

    return 'elabftw'
}

const normalizeActive = (app: RawConnectedApp): boolean => {
    if (typeof app.isActive === 'boolean') {
        return app.isActive
    }

    if (typeof app.active === 'boolean') {
        return app.active
    }

    return false
}

const catalogCodeIds = new Set(CONNECTED_APP_CATALOG.map((app) => app.id))

const normalizeAppName = (app: RawConnectedApp, code: ConnectedApp['code']) =>
    code === 'elabftw' && app.name.toLowerCase() === 'elabftw' ? 'ElabFTW' : app.name

const normalizeConnectedApp = (app: RawConnectedApp): ConnectedApp => ({
    ...app,
    code: normalizeCode(app.code),
    isActive: normalizeActive(app),
})

const toConnectedAppsCollection = (payload: ConnectedAppsResponse | RawConnectedApp[]): ConnectedAppsResponse => {
    const members = Array.isArray(payload)
        ? payload
        : Array.isArray(payload['hydra:member'])
            ? payload['hydra:member']
            : null

    if (!members) {
        throw new Error('Connected apps response is not a collection.')
    }

    const normalized = members.map((app) => {
        const connectedApp = normalizeConnectedApp(app as RawConnectedApp)
        return {
            ...connectedApp,
            name: normalizeAppName(connectedApp, connectedApp.code),
        }
    })
    const existingCodes = new Set(normalized.map((app) => app.code))
    const missingCatalogEntries = CONNECTED_APP_CATALOG.filter((app) => !existingCodes.has(app.code))
    const withCatalogFallback = [...normalized, ...missingCatalogEntries]

    return {
        'hydra:member': withCatalogFallback,
        'hydra:totalItems': withCatalogFallback.length,
    }
}

export function getConnectedApps() {
    return apiFetch<ConnectedAppsResponse | RawConnectedApp[]>('/connected_apps').then((payload) => toConnectedAppsCollection(payload))
}

export function getConnectedApp(appId: string) {
    return apiFetch<ConnectedApp>(`/connected_apps/${appId}`)
        .then((app) => normalizeConnectedApp(app as RawConnectedApp))
        .catch((error) => {
            const catalogEntry = CONNECTED_APP_CATALOG.find((app) => app.id === appId)
            if (catalogEntry) {
                return catalogEntry
            }

            throw error
        })
}

export function updateConnectedApp(appId: string, payload: Partial<ConnectedApp>) {
    if (catalogCodeIds.has(appId)) {
        const catalogEntry = CONNECTED_APP_CATALOG.find((app) => app.id === appId)
        if (!catalogEntry) {
            throw new Error(`Unknown connected app catalog entry: ${appId}`)
        }

        return apiFetch<ConnectedApp>('/connected_apps', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/ld+json',
            },
            body: JSON.stringify({
                code: catalogEntry.code,
                name: catalogEntry.name,
                description: catalogEntry.description,
                active: false,
                externalUrl: catalogEntry.externalUrl,
                ...payload,
            }),
        }).then((app) => normalizeConnectedApp(app as RawConnectedApp))
    }

    return apiFetch<ConnectedApp>(`/connected_apps/${appId}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/merge-patch+json',
        },
        body: JSON.stringify(payload),
    }).then((app) => normalizeConnectedApp(app as RawConnectedApp))
}
export function syncConnectedApp(appId: string) {
    return apiFetch<void>(`/connected_apps/${appId}/sync`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: '{}',
    })
}

export function pushConnectedApp(appId: string) {
    return apiFetch<void>(`/connected_apps/${appId}/push`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: '{}',
    })
}

export function testFair3rConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        datasetsCount: number
    }>(`/connected_apps/${appId}/fair3r/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: '{}',
    })
}

export function testElabftwConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        user: {
            userid: number
            firstname: string | null
            lastname: string | null
            email: string | null
        }
    }>(`/connected_apps/${appId}/elabftw/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: '{}',
    })
}

export function testOsfConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        user: {
            id: string | null
            fullName: string | null
            email: string | null
        }
    }>(`/connected_apps/${appId}/osf/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: '{}',
    })
}

export function testProtocolsIoConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        user: {
            username: string | null
            name: string | null
            affiliation: string | null
        }
    }>(`/connected_apps/${appId}/protocols-io/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: '{}',
    })
}

export function testPreclinicalTrialsConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        protocolsCount: number
    }>(`/connected_apps/${appId}/preclinicaltrials/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: '{}',
    })
}

export function testCedarConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        usersCount: number | null
    }>(`/connected_apps/${appId}/cedar/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: '{}',
    })
}

export function testBioPortalConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        ontologiesCount: number | null
    }>(`/connected_apps/${appId}/bioportal/test-connection`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: '{}',
    })
}

export function fetchFair3rDatasets(appId: string) {
    return apiFetch<{ datasets: string[] }>(`/connected_apps/${appId}/fair3r/datasets`)
}

export type Fair3rDatasetResource = {
    id: string | null
    name: string | null
    description: string | null
    format: string | null
    mimetype: string | null
    url: string | null
}

export type Fair3rDatasetDetails = {
    dataset: {
        id: string | null
        name: string | null
        title: string | null
        notes: string | null
        licenseId: string | null
        author: string | null
        authorEmail: string | null
        metadataCreated: string | null
        metadataModified: string | null
        resources: Fair3rDatasetResource[]
    }
    fdf: Record<string, unknown> | null
    ckan: Record<string, unknown>
}

export function fetchFair3rDataset(appId: string, datasetName: string) {
    return apiFetch<Fair3rDatasetDetails>(`/connected_apps/${appId}/fair3r/datasets/${encodeURIComponent(datasetName)}`)
}

export function pushInvestigationToFair3r(appId: string, investigationId: string, targetDatasetName?: string, studyIds: string[] = []) {
    return apiFetch<{
        ok: boolean
        ckanName: string
        packageUrl: string | null
        resourceId?: string | null
        demoCsvResourceIds?: Record<string, string | null>
    }>(`/connected_apps/${appId}/fair3r/push-investigation`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ investigationId, targetDatasetName, studyIds }),
    })
}

export function getStudiesForInvestigation(investigationId: string) {
    return apiFetchHydraMapped<MetaStudy, Study>(
        `/experiments?investigation=${encodeURIComponent(investigationId)}`,
        mapMetaStudy
    )
}

export type FdfValidationResult = {
    valid: boolean
    errors: string[]
    schema?: string
}

/**
 * Fetch the DataCite 4.x JSON for an investigation from the FAIR3R exchange endpoint.
 * Output is aligned with the Open Science Dataset Registration Wizard
 * (https://github.com/precliniverse/Dynamic_Metadata_form, v3.0.0).
 */
export function fetchInvestigationFdf(investigationId: string) {
    return apiFetch<Record<string, unknown>>(`/fair3r/investigations/${investigationId}/dataset.json`)
}

/**
 * Fetch the DataCite 4.x JSON for a study (experiment) from the FAIR3R exchange endpoint.
 */
export function fetchStudyFdf(studyId: string) {
    return apiFetch<Record<string, unknown>>(`/fair3r/studies/${studyId}/dataset.json`)
}

/**
 * Download the DataCite XML for an investigation.
 * Returns a Blob so the caller can trigger a browser download.
 */
export function fetchInvestigationDataciteXml(investigationId: string) {
    return apiFetch<Blob>(`/fair3r/investigations/${investigationId}/datacite.xml`, {
        responseType: 'blob',
    })
}

/**
 * Download the DataCite XML for a study.
 */
export function fetchStudyDataciteXml(studyId: string) {
    return apiFetch<Blob>(`/fair3r/studies/${studyId}/datacite.xml`, {
        responseType: 'blob',
    })
}

/**
 * POST an FDF JSON payload to the Metadatapp validation endpoint.
 * Returns validation results including any semantic/schema errors.
 */
export function validateFdfPayload(payload: Record<string, unknown>) {
    return apiFetch<FdfValidationResult>('/fair3r/datasets/validate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/ld+json' },
        body: JSON.stringify(payload),
    })
}
