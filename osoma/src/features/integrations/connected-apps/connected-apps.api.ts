import { apiFetch, apiFetchHydraCollection, apiFetchHydraMapped } from '@/lib/api.ts'
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
    'softmouse',
    'protocolio',
    'preclinicaltrials',
    'cedar',
    'bioportal',
    'tecniplast',
    'sensor_agent',
    'nih_cde',
    'jax_phenome',
    'impc',
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
        id: 'softmouse',
        '@id': '/connected_apps/softmouse',
        name: 'SoftMouse',
        code: 'softmouse',
        description: 'Colony management software for mouse research.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: '/images/apps/softmouse.svg',
        externalUrl: 'https://your-softmouse.example',
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
    {
        id: 'nih_cde',
        '@id': '/connected_apps/nih_cde',
        name: 'NIH CDE Repository',
        code: 'nih_cde',
        description: 'NIH Common Data Elements repository. Browse, inspect permissible values, and import standardized metadata elements into Metadatapp.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: null,
        externalUrl: 'https://cde.nlm.nih.gov',
    },
    {
        id: 'jax_phenome',
        '@id': '/connected_apps/jax_phenome',
        name: 'JAX Phenome (MPD)',
        code: 'jax_phenome',
        description: 'Mouse Phenome Database (MPD). Browse curated mouse phenotype measures and strains for physiology and behavior genetics/genomics research.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: null,
        externalUrl: 'https://phenome.jax.org',
    },
    {
        id: 'impc',
        '@id': '/connected_apps/impc',
        name: 'IMPReSS (IMPC)',
        code: 'impc',
        description: 'International Mouse Phenotyping Resource of Standardised Screens (IMPReSS). Search standardized phenotyping procedures and parameters used in behavioral studies.',
        isActive: false,
        lastSyncAt: null,
        logoUrl: null,
        externalUrl: 'https://api.mousephenotype.org',
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
        return toAppCode(code.value) ?? toAppCode(code.code) ?? toAppCode(code.name) ?? 'softmouse'
    }

    return 'softmouse'
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

export function updateConnectedApp(appId: string, payload: Partial<ConnectedApp> & { active?: boolean }) {
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
                // `code` is an API Platform resource enum (AppCode), so the
                // write payload must reference it by IRI, not the bare value —
                // otherwise the server rejects it with `Invalid IRI "<code>"`.
                code: `/app_codes/${catalogEntry.code}`,
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

export type PreclinicalTrialsProtocolSummary = {
    id: string
    title: string | null
    status: string | null
    registrationDate: string | null
    startDate: string | null
    endDate: string | null
    researchField: string | null
    species: string | null
    strain: string | null
    sex: string | null
    animals: string | null
    repositoryLink: string | null
    linkedInvestigation: { id: string; name: string } | null
    linkedStudy: { id: string; name: string } | null
}

export type PreclinicalTrialsImportTarget =
    | { target: 'new-investigation' }
    | { target: 'existing-investigation'; investigationId: string }
    | { target: 'existing-study'; studyId: string }

export function fetchPreclinicalTrialsProtocols(appId: string) {
    return apiFetch<{ protocols: PreclinicalTrialsProtocolSummary[] }>(`/connected_apps/${appId}/preclinicaltrials/protocols`)
}

export function importPreclinicalTrialsProtocol(appId: string, protocolId: string, target: PreclinicalTrialsImportTarget) {
    return apiFetch<{
        ok: boolean
        target: {
            type: 'investigation' | 'study'
            id: string
            name: string
            externalId?: string
            linkId?: string
        }
    }>(`/connected_apps/${appId}/preclinicaltrials/import-protocol`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ protocolId, ...target }),
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

// --- CEDAR artifact surface (mirrors cedar-artifact-rest-mcp) -----------------

export type CedarArtifactKind = 'template' | 'element' | 'field' | 'instance'

export type CedarArtifact = Record<string, unknown>

export type CedarValidationReport = {
    validates: boolean
    warnings: unknown[]
    errors: unknown[]
}

export function getCedarArtifact(appId: string, type: CedarArtifactKind, iri: string) {
    return apiFetch<{ artifact: CedarArtifact }>(
        `/connected_apps/${appId}/cedar/artifacts/${type}?iri=${encodeURIComponent(iri)}`,
    )
}

export function createCedarArtifact(appId: string, type: CedarArtifactKind, artifact: CedarArtifact) {
    return apiFetch<{ artifact: CedarArtifact }>(`/connected_apps/${appId}/cedar/artifacts/${type}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(artifact),
    })
}

export function updateCedarArtifact(appId: string, type: CedarArtifactKind, iri: string, artifact: CedarArtifact) {
    return apiFetch<{ artifact: CedarArtifact }>(`/connected_apps/${appId}/cedar/artifacts/${type}/update`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ iri, artifact }),
    })
}

export function deleteCedarArtifact(appId: string, type: CedarArtifactKind, iri: string) {
    return apiFetch<{ ok: boolean; deleted: string }>(`/connected_apps/${appId}/cedar/artifacts/${type}/delete`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ iri }),
    })
}

export function validateCedarArtifact(appId: string, artifact: CedarArtifact, type?: CedarArtifactKind) {
    return apiFetch<CedarValidationReport>(`/connected_apps/${appId}/cedar/validate`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(type ? { artifact, type } : { artifact }),
    })
}

export function importCedarTemplate(appId: string, iri: string) {
    return apiFetch<{
        ok: boolean
        id: string
        title: string
        externalUrl: string
        fieldCount: number
        crosswalkId: string | null
    }>(
        `/connected_apps/${appId}/cedar/import-template`,
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ iri }),
        },
    )
}

export type CedarFormExportResult = {
    ok: boolean
    id: string | null
    title: string
    fieldCount: number
    artifact: CedarArtifact
}

/**
 * Write a Metadatapp canonical form back to CEDAR as a template. Creates a new
 * template, or updates an existing one when `iri` is supplied.
 */
export function exportCanonicalFormToCedar(appId: string, canonicalFormId: string, iri?: string) {
    const body: { canonicalFormId: string; iri?: string } = { canonicalFormId }
    if (iri && iri.trim() !== '') body.iri = iri.trim()
    return apiFetch<CedarFormExportResult>(`/connected_apps/${appId}/cedar/export-form`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    })
}

export type CanonicalFormSummary = { id: string; title: string }

/** Account's canonical forms, used to pick one to push to CEDAR. */
export async function listCanonicalForms(): Promise<CanonicalFormSummary[]> {
    const collection = await apiFetchHydraCollection<CanonicalFormSummary>('/canonical-forms')
    return collection.data
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

// ─── NIH CDE ───────────────────────────────────────────────────────────────

export type NihCdePermissibleValue = {
    permissibleValue: string | null
    valueMeaningName: string | null
    valueMeaningCode: string | null
    valueMeaningDefinition: string | null
}

export type NihCdeElement = {
    tinyId: string
    version: string | null
    registrationStatus: string | null
    stewardOrg: { name: string } | null
    designations: Array<{ designation: string; tags: string[] }>
    definitions: Array<{ definition: string; sources: string[] }>
    permissibleValues: NihCdePermissibleValue[]
    valueDomain?: {
        datatype: string | null
        uom: string | null
    }
    [key: string]: unknown
}

export type NihCdeSearchResult = {
    totalItems: number
    elements: NihCdeElement[]
}

export function testNihCdeConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        cdesCount: number
    }>(`/connected_apps/${appId}/nih-cde/test-connection`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: '{}',
    })
}

export function searchNihCdeElements(appId: string, searchTerm: string, resultPerPage = 20, skip = 0) {
    const params = new URLSearchParams({
        searchTerm,
        resultPerPage: String(resultPerPage),
        skip: String(skip),
    })
    return apiFetch<NihCdeSearchResult>(`/connected_apps/${appId}/nih-cde/search?${params}`)
}

export function getNihCdeElement(appId: string, tinyId: string) {
    return apiFetch<NihCdeElement>(`/connected_apps/${appId}/nih-cde/elements/${encodeURIComponent(tinyId)}`)
}

export function importNihCdeElement(appId: string, tinyId: string) {
    return apiFetch<{ ok: boolean; tinyId: string; name: string; id: string }>(
        `/connected_apps/${appId}/nih-cde/import`,
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tinyId }),
        }
    )
}

// ─── JAX Phenome (MPD) ───────────────────────────────────────────────────────

// Upstream shapes vary, so keep all fields optional and tolerate extra keys.
export type JaxPhenomeMeasure = {
    id?: string | number | null
    measnum?: string | number | null
    name?: string | null
    description?: string | null
    units?: string | null
    projectsym?: string | null
    [key: string]: unknown
}

export type JaxPhenomeStrain = {
    id?: string | number | null
    strainid?: string | number | null
    name?: string | null
    symbol?: string | null
    [key: string]: unknown
}

export type JaxPhenomeOntologyTerm = {
    id: string
    descrip: string
}

export type JaxPhenomeMeasureSearchResult = {
    totalItems: number
    measures: JaxPhenomeMeasure[]
    // MPD has no keyword measure search: free text is resolved to ontology terms
    // (these), and the measures shown are those mapped to `resolvedTerm`.
    ontologyTerms?: JaxPhenomeOntologyTerm[]
    resolvedTerm?: (Record<string, unknown> & { id?: string; descrip?: string }) | null
}

export type JaxPhenomeStrainListResult = {
    totalItems: number
    strains: JaxPhenomeStrain[]
}

export function testJaxPhenomeConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        strainsCount: number
    }>(`/connected_apps/${appId}/jax-phenome/test-connection`)
}

export function searchJaxPhenomeMeasures(appId: string, searchTerm: string, limit = 20, offset = 0) {
    const params = new URLSearchParams({
        searchTerm,
        limit: String(limit),
        offset: String(offset),
    })
    return apiFetch<JaxPhenomeMeasureSearchResult>(`/connected_apps/${appId}/jax-phenome/measures?${params}`)
}

export function getJaxPhenomeMeasure(appId: string, measureId: string) {
    return apiFetch<JaxPhenomeMeasure>(`/connected_apps/${appId}/jax-phenome/measures/${encodeURIComponent(measureId)}`)
}

export function listJaxPhenomeStrains(appId: string, limit = 20, offset = 0) {
    const params = new URLSearchParams({
        limit: String(limit),
        offset: String(offset),
    })
    return apiFetch<JaxPhenomeStrainListResult>(`/connected_apps/${appId}/jax-phenome/strains?${params}`)
}

// ─── IMPReSS (IMPC) ──────────────────────────────────────────────────────────

// Upstream shapes vary, so keep all fields optional and tolerate extra keys.
export type ImpcOntologyMapping = {
    id?: string | null
    term?: string | null
    [key: string]: unknown
}

export type ImpcParameter = {
    stableKey?: string | null
    name?: string | null
    datatype?: string | null
    unit?: string | null
    isMetadata?: boolean | null
    ontologyMapping?: ImpcOntologyMapping[] | null
    [key: string]: unknown
}

export type ImpcProcedure = {
    // IMPReSS keys procedure detail/parameter REST calls by the numeric procedure
    // id (procID), so search results carry it for the detail lookup.
    procedureId?: string | number | null
    procID?: string | number | null
    stableKey?: string | null
    name?: string | null
    isMandatory?: boolean | null
    level?: string | null
    pipelineKey?: string | null
    description?: string | null
    parameters?: ImpcParameter[] | null
    [key: string]: unknown
}

export type ImpcPipeline = {
    stableKey?: string | null
    name?: string | null
    description?: string | null
    [key: string]: unknown
}

export type ImpcProcedureSearchResult = {
    totalItems: number
    procedures: ImpcProcedure[]
}

export type ImpcPipelineListResult = {
    totalItems: number
    pipelines: ImpcPipeline[]
}

export function testImpcConnection(appId: string) {
    return apiFetch<{
        ok: boolean
        externalUrl: string
        pipelinesCount: number
    }>(`/connected_apps/${appId}/impc/test-connection`)
}

export function searchImpcProcedures(appId: string, searchTerm: string, limit = 20, offset = 0) {
    const params = new URLSearchParams({
        searchTerm,
        limit: String(limit),
        offset: String(offset),
    })
    return apiFetch<ImpcProcedureSearchResult>(`/connected_apps/${appId}/impc/procedures?${params}`)
}

export function getImpcProcedure(appId: string, procedureId: string) {
    return apiFetch<ImpcProcedure>(`/connected_apps/${appId}/impc/procedures/${encodeURIComponent(procedureId)}`)
}

export function listImpcPipelines(appId: string, limit = 20, offset = 0) {
    const params = new URLSearchParams({
        limit: String(limit),
        offset: String(offset),
    })
    return apiFetch<ImpcPipelineListResult>(`/connected_apps/${appId}/impc/pipelines?${params}`)
}
