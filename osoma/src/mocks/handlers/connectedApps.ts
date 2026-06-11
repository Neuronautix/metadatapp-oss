import { http, HttpResponse } from 'msw'
import { connectedApps } from '@/mocks/data/connectedApps.ts'
import { investigations } from '@/mocks/data/investigations.ts'
import { withLatency } from './utils.ts'

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
]
