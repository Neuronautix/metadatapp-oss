import { http, HttpResponse } from 'msw'
import { connectedApps } from '@/mocks/data/connectedApps.ts'
import { investigations } from '@/mocks/data/investigations.ts'
import { withLatency } from './utils.ts'
import type { JaxPhenomeMeasure, JaxPhenomeStrain } from '@/features/integrations/connected-apps/connected-apps.api.ts'

/** Mock JAX Phenome (MPD) phenotype measures. */
const jaxPhenomeMeasures: JaxPhenomeMeasure[] = [
    {
        id: 'mpd-1001',
        measnum: 1001,
        name: 'Body weight',
        description: 'Body weight measured at 8 weeks of age.',
        units: 'g',
        projectsym: 'Jaxwest1',
    },
    {
        id: 'mpd-1002',
        measnum: 1002,
        name: 'Fasting blood glucose',
        description: 'Blood glucose concentration after a 4-hour fast.',
        units: 'mg/dL',
        projectsym: 'Jaxwest2',
    },
    {
        id: 'mpd-1003',
        measnum: 1003,
        name: 'Open field activity',
        description: 'Total distance travelled in an open field test over 20 minutes.',
        units: 'cm',
        projectsym: 'CGDpheno3',
    },
]

/** Mock JAX Phenome (MPD) strains. */
const jaxPhenomeStrains: JaxPhenomeStrain[] = [
    { id: 'strain-1', strainid: 1, name: 'C57BL/6J', symbol: 'B6' },
    { id: 'strain-2', strainid: 2, name: 'BALB/cJ', symbol: 'C' },
    { id: 'strain-3', strainid: 3, name: 'DBA/2J', symbol: 'D2' },
]

/** Build a mock FDF JSON document for the given investigation mock data entry. */
function buildMockFdf(inv: (typeof investigations)[number]) {
    const fairTotal = typeof inv.fairScore === 'object' && inv.fairScore !== null ? inv.fairScore.total : (inv.fairScore ?? 75)
    const fairScore = typeof inv.fairScore === 'object' && inv.fairScore !== null
        ? inv.fairScore
        : { total: fairTotal, findable: 80, accessible: 70, interoperable: 75, reusable: 75 }
    return {
        '@context': 'https://fair3r.fr/schemas/fdf-dataset/1.0',
        '@type': 'schema:Dataset',
        id: inv.id,
        name: inv.id,
        title: inv.name,
        notes: inv.description ?? null,
        owner_org: 'metadatapp-demo',
        author: 'Demo Researcher',
        author_email: 'demo@metadatapp.example',
        license_id: 'CC-BY-4.0',
        license_title: 'Creative Commons Attribution 4.0 International',
        url: `http://localhost:4173/investigations/${inv.id}`,
        private: false,
        state: 'active',
        metadata_created: new Date().toISOString(),
        metadata_modified: new Date().toISOString(),
        tags: ['animal-welfare', 'FAIR'],
        resources: [],
        fair_score: fairScore,
        extras: [
            { key: 'fair_f1', value: 'pass' },
            { key: 'fair_f2', value: 'pass' },
            { key: 'fair_a1', value: 'pass' },
            { key: 'fair_i1', value: fairTotal >= 80 ? 'pass' : 'fail' },
        ],
    }
}

export const connectedAppsHandlers = [
    http.get('/api/connected_apps', async () => {
        await withLatency()
        return HttpResponse.json({
            'hydra:member': connectedApps,
            'hydra:totalItems': connectedApps.length,
        })
    }),

    http.get('/api/connected_apps/:appId', async ({ params }) => {
        await withLatency()
        const app = connectedApps.find((record) => record.id === params.appId)
        if (!app) {
            return HttpResponse.json({ message: 'Integration not found.' }, { status: 404 })
        }

        return HttpResponse.json(app)
    }),

    http.patch('/api/connected_apps/:appId', async ({ params, request }) => {
        await withLatency()
        const payload = await request.json() as Record<string, unknown>
        const index = connectedApps.findIndex((record) => record.id === params.appId)

        if (index === -1) {
            return HttpResponse.json({ message: 'Integration not found.' }, { status: 404 })
        }

        connectedApps[index] = {
            ...connectedApps[index],
            ...payload,
        }

        return HttpResponse.json(connectedApps[index])
    }),
    http.post('/api/connected_apps/:appId/sync', async ({ params }) => {
        await withLatency()
        const index = connectedApps.findIndex((record) => record.id === params.appId)

        if (index === -1) {
            return HttpResponse.json({ message: 'Integration not found.' }, { status: 404 })
        }

        connectedApps[index] = {
            ...connectedApps[index],
            lastSyncAt: new Date().toISOString(),
        }

        return HttpResponse.json({}, { status: 202 })
    }),

    // ------------------------------------------------------------------
    // JAX Phenome (MPD) proxy endpoints (mock)
    // ------------------------------------------------------------------

    http.get('/api/connected_apps/:appId/jax-phenome/test-connection', async () => {
        await withLatency()
        return HttpResponse.json({
            ok: true,
            externalUrl: 'https://phenome.jax.org',
            strainsCount: 642,
        })
    }),

    http.get('/api/connected_apps/:appId/jax-phenome/measures', async ({ request }) => {
        await withLatency()
        const url = new URL(request.url)
        const searchTerm = (url.searchParams.get('searchTerm') ?? '').toLowerCase()
        const limit = Number(url.searchParams.get('limit') ?? '20')
        const offset = Number(url.searchParams.get('offset') ?? '0')

        const all = jaxPhenomeMeasures.filter((measure) =>
            !searchTerm ||
            (measure.name ?? '').toLowerCase().includes(searchTerm) ||
            (measure.description ?? '').toLowerCase().includes(searchTerm)
        )

        return HttpResponse.json({
            totalItems: all.length,
            measures: all.slice(offset, offset + limit),
        })
    }),

    http.get('/api/connected_apps/:appId/jax-phenome/measures/:measureId', async ({ params }) => {
        await withLatency()
        const measure = jaxPhenomeMeasures.find((record) => String(record.id) === params.measureId)
        if (!measure) {
            return HttpResponse.json({ message: 'Measure not found.' }, { status: 404 })
        }
        return HttpResponse.json(measure)
    }),

    http.get('/api/connected_apps/:appId/jax-phenome/strains', async ({ request }) => {
        await withLatency()
        const url = new URL(request.url)
        const limit = Number(url.searchParams.get('limit') ?? '20')
        const offset = Number(url.searchParams.get('offset') ?? '0')
        return HttpResponse.json({
            totalItems: jaxPhenomeStrains.length,
            strains: jaxPhenomeStrains.slice(offset, offset + limit),
        })
    }),

    http.get('/api/connected_apps/:id/fair3r/datasets', async () => {
        await withLatency()
        return HttpResponse.json({
            datasets: investigations.map((inv) => inv.id),
        })
    }),

    // ------------------------------------------------------------------
    // FAIR3R FDF / DataCite exchange endpoints (mock)
    // ------------------------------------------------------------------

    http.get('/api/fair3r/investigations/:id/dataset.json', async ({ params }) => {
        await withLatency()
        const inv = investigations.find((i) => i.id === params.id) ?? investigations[0]
        if (!inv) {
            return HttpResponse.json({ error: 'Investigation not found.' }, { status: 404 })
        }
        return HttpResponse.json(buildMockFdf(inv), {
            headers: { 'Content-Type': 'application/ld+json', 'X-FDF-Schema': 'https://fair3r.fr/schemas/fdf-dataset/1.0' },
        })
    }),

    http.get('/api/fair3r/investigations/:id/datacite.xml', async ({ params }) => {
        await withLatency()
        const inv = investigations.find((i) => i.id === params.id) ?? investigations[0]
        const title = inv?.name ?? 'Unknown Investigation'
        const fairTotal = inv?.fairScore && typeof inv.fairScore === 'object' ? inv.fairScore.total : (inv?.fairScore ?? 75)
        const xml = `<?xml version="1.0" encoding="UTF-8"?>
<resource xmlns="http://datacite.org/schema/kernel-4"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://datacite.org/schema/kernel-4 https://schema.datacite.org/meta/kernel-4/metadata.xsd">
  <identifier identifierType="DOI">10.0000/${params.id}</identifier>
  <creators><creator><creatorName nameType="Personal">Demo Researcher</creatorName></creator></creators>
  <titles><title>${title}</title></titles>
  <publisher>Metadatapp / FAIR3R</publisher>
  <publicationYear>${new Date().getFullYear()}</publicationYear>
  <resourceType resourceTypeGeneral="Dataset">Dataset/Investigation</resourceType>
  <descriptions><description descriptionType="Abstract">Investigation ${title} exported from Metadatapp.</description></descriptions>
  <rightsList><rights rightsURI="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</rights></rightsList>
  <subjects>
    <subject subjectScheme="FAIR3R" schemeURI="https://fair3r.fr" valueURI="https://fair3r.fr/score">
      FAIR score: ${fairTotal}/100
    </subject>
  </subjects>
</resource>`
        return new HttpResponse(xml, {
            headers: {
                'Content-Type': 'application/xml; charset=UTF-8',
                'Content-Disposition': `attachment; filename="investigation-${params.id}-datacite.xml"`,
            },
        })
    }),

    http.get('/api/fair3r/studies/:id/dataset.json', async ({ params }) => {
        await withLatency()
        const mock = {
            '@context': 'https://fair3r.fr/schemas/fdf-dataset/1.0',
            '@type': 'schema:Dataset',
            id: params.id,
            name: `study-${params.id}`,
            title: `Study ${params.id}`,
            owner_org: 'metadatapp-demo',
            author: 'Demo Researcher',
            license_id: 'CC-BY-4.0',
            state: 'active',
            fair_score: { total: 80, findable: 85, accessible: 75, interoperable: 80, reusable: 80 },
        }
        return HttpResponse.json(mock, {
            headers: { 'Content-Type': 'application/ld+json' },
        })
    }),

    http.get('/api/fair3r/studies/:id/datacite.xml', async ({ params }) => {
        await withLatency()
        const xml = `<?xml version="1.0" encoding="UTF-8"?>
<resource xmlns="http://datacite.org/schema/kernel-4"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://datacite.org/schema/kernel-4 https://schema.datacite.org/meta/kernel-4/metadata.xsd">
  <identifier identifierType="DOI">10.0000/${params.id}</identifier>
  <creators><creator><creatorName nameType="Personal">Demo Researcher</creatorName></creator></creators>
  <titles><title>Study ${params.id}</title></titles>
  <publisher>Metadatapp / FAIR3R</publisher>
  <publicationYear>${new Date().getFullYear()}</publicationYear>
  <resourceType resourceTypeGeneral="Dataset">Dataset</resourceType>
  <rightsList><rights rightsURI="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</rights></rightsList>
</resource>`
        return new HttpResponse(xml, {
            headers: {
                'Content-Type': 'application/xml; charset=UTF-8',
                'Content-Disposition': `attachment; filename="study-${params.id}-datacite.xml"`,
            },
        })
    }),

    http.post('/api/fair3r/datasets/validate', async ({ request }) => {
        await withLatency()
        let payload: Record<string, unknown>
        try {
            payload = await request.json() as Record<string, unknown>
        } catch {
            return HttpResponse.json({ valid: false, errors: ['Request body is not valid JSON.'] }, { status: 422 })
        }

        const errors: string[] = []
        const titles = payload['titles']
        if (!Array.isArray(titles) || titles.length === 0) {
            errors.push('Required field "titles" is missing or not an array.')
        } else if (!(titles[0] as Record<string, unknown>)?.['title']) {
            errors.push('titles[0].title must be a non-empty string.')
        }
        const pubYear = payload['publicationYear']
        if (pubYear === undefined || pubYear === null) {
            errors.push('Required field "publicationYear" is missing.')
        } else if (typeof pubYear !== 'number' || pubYear < 2000 || pubYear > 2100) {
            errors.push('Field "publicationYear" must be an integer between 2000 and 2100.')
        }
        const creators = payload['creators']
        if (!Array.isArray(creators) || creators.length === 0) {
            errors.push('Required field "creators" is missing or not an array.')
        }
        if (!Array.isArray(payload['types']) || (payload['types'] as unknown[]).length === 0) {
            errors.push('Required field "types" is missing or not an array.')
        }

        if (errors.length > 0) {
            return HttpResponse.json({ valid: false, errors }, { status: 422 })
        }

        return HttpResponse.json({
            valid: true,
            errors: [],
            schema: 'https://fair3r.fr/schemas/datacite-dataset/3.0',
        })
    }),

    // Reference Hub library — import / list / delete.
    http.post('/api/imported_references/import', async ({ request }) => {
        await withLatency()
        const body = (await request.json()) as { items?: Array<Record<string, unknown>> }
        const items = Array.isArray(body.items) ? body.items : []
        for (const item of items) {
            const referenceId = String(item.id ?? '')
            if (!referenceId) continue
            const existing = importedReferencesStore.findIndex((r) => r.referenceId === referenceId)
            const record = { ...item, id: existing >= 0 ? importedReferencesStore[existing].id : `ir-${referenceId}`, referenceId, importedAt: '2026-06-29T12:00:00Z' }
            if (existing >= 0) importedReferencesStore[existing] = record
            else importedReferencesStore.push(record)
        }
        return HttpResponse.json({ count: items.length, imported: importedReferencesStore }, { status: 201 })
    }),

    http.get('/api/imported_references', async () => {
        await withLatency()
        return HttpResponse.json({
            '@context': '/contexts/ImportedReference',
            '@id': '/imported_references',
            '@type': 'Collection',
            'hydra:member': importedReferencesStore,
            'hydra:totalItems': importedReferencesStore.length,
        })
    }),

    http.delete('/api/imported_references/:id', async ({ params }) => {
        await withLatency()
        const idx = importedReferencesStore.findIndex((r) => r.id === params.id)
        if (idx >= 0) importedReferencesStore.splice(idx, 1)
        return new HttpResponse(null, { status: 204 })
    }),

    // Federated Reference Hub search across connected apps.
    http.get('/api/reference-search', async ({ request }) => {
        await withLatency()
        const url = new URL(request.url)
        const q = (url.searchParams.get('q') ?? '').toLowerCase()
        const appsParam = url.searchParams.get('apps')
        const apps = appsParam ? appsParam.split(',').map((c) => c.trim()).filter(Boolean) : null

        const all = referenceSearchResults.filter(
            (r) =>
                (!apps || apps.includes(r.source)) &&
                (!q || `${r.title} ${r.description ?? ''} ${r.subtitle ?? ''}`.toLowerCase().includes(q)),
        )

        return HttpResponse.json({
            '@context': '/contexts/ReferenceResult',
            '@id': '/reference-search',
            '@type': 'Collection',
            'hydra:member': all,
            'hydra:totalItems': all.length,
        })
    }),
]

// Mutable in-memory store for the Reference Hub library (import/list/delete).
const importedReferencesStore: Array<Record<string, unknown> & { id: string; referenceId: string }> = []

const referenceSearchResults = [
    {
        id: 'jax_phenome:measure:43003',
        source: 'jax_phenome',
        sourceName: 'JAX Phenome (MPD)',
        type: 'measure',
        title: 'homocysteine concentration (plasma)',
        subtitle: 'nmol/mL',
        description: 'high-fat diet and ethanol',
        externalUrl: 'https://phenome.jax.org/measureset/43003',
        identifiers: { measnum: 43003, projsym: 'Rusyn5' },
        raw: { measnum: 43003, descrip: 'homocysteine concentration (plasma)', units: 'nmol/mL' },
    },
    {
        id: 'impc:procedure:IMPC_HEM_001',
        source: 'impc',
        sourceName: 'IMPReSS (IMPC)',
        type: 'procedure',
        title: 'Hematology',
        subtitle: 'IMPC Pipeline',
        description: 'Standardized blood collection and hematology screen.',
        externalUrl: 'https://www.mousephenotype.org/impress/protocol/101',
        identifiers: { procedureKey: 'IMPC_HEM_001', procID: 101 },
        raw: { procedure_stable_id: 'IMPC_HEM_001', procedure_name: 'Hematology' },
    },
    {
        id: 'nih_cde:cde_element:abc123',
        source: 'nih_cde',
        sourceName: 'NIH CDE Repository',
        type: 'cde_element',
        title: 'Blood Specimen Collection Method Type',
        subtitle: 'NLM',
        description: 'The method used to collect a blood specimen.',
        externalUrl: 'https://cde.nlm.nih.gov/deView?tinyId=abc123',
        identifiers: { tinyId: 'abc123', version: '1.0' },
        raw: { tinyId: 'abc123' },
    },
]
