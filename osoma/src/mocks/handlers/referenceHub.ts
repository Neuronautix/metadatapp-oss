import { http, HttpResponse } from 'msw'

type ReferenceItem = {
  id: string
  title?: string
  type?: string
  description?: string | null
}

const sourceOf = (id: string) => id.split(':')[0] ?? 'source'

const normalize = (title: string) =>
  title
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim()

/**
 * Deterministic stand-in for the AI "Compare & Link" endpoint. Groups the posted
 * results by normalized title (mirroring the backend's heuristic fallback) and
 * attaches a synthetic canonical term so the Compare view renders end-to-end in
 * mock mode without a real provider.
 */
export const referenceHubHandlers = [
  http.post('/api/references/standardize', async ({ request }) => {
    const body = (await request.json()) as { items?: ReferenceItem[] }
    const items = Array.isArray(body.items) ? body.items : []

    const groups = new Map<string, ReferenceItem[]>()
    items.forEach((item, index) => {
      const key = normalize(item.title ?? '') || `ref-${index}`
      groups.set(key, [...(groups.get(key) ?? []), item])
    })

    let position = 0
    const clusters = Array.from(groups.entries()).map(([signature, members]) => {
      position += 1
      return {
        clusterId: `cluster-${position}`,
        canonicalTerm: {
          iri: `http://purl.obolibrary.org/obo/MOCK_${position}`,
          label: members[0]?.title ?? signature,
          ontology: 'vt',
          confidence: 0.88,
        },
        memberRefIds: members.map((m) => m.id),
        evidence: [`Mock standardization grouped ${members.length} result(s) by normalized title.`],
        proposedRecord: { title: members[0]?.title ?? signature, type: members[0]?.type ?? '' },
        confidence: 0.88,
        harmonizedFields: [
          {
            field: 'type',
            value: members[0]?.type ?? '',
            conflict: new Set(members.map((m) => m.type ?? '')).size > 1,
            sourceValues: members.map((m) => ({ source: sourceOf(m.id), value: m.type ?? '' })),
          },
          {
            field: 'description',
            value: members[0]?.description ?? '',
            conflict: new Set(members.map((m) => m.description ?? '')).size > 1,
            sourceValues: members.map((m) => ({ source: sourceOf(m.id), value: m.description ?? '' })),
            rationale: 'Mock: chose the first source description as canonical.',
          },
        ],
      }
    })

    return HttpResponse.json({ aiAvailable: true, clusters, warnings: [], provenance: [], usage: { provider: 'mock' } })
  }),

  http.post('/api/imported_references/:id/promote-to-cde', () =>
    HttpResponse.json({ id: 'mock-cde', label: 'Mock CDE', source: 'custom', localKey: 'mock_cde' }, { status: 201 })
  ),

  // "Best match" — ranks the posted results by query-term overlap (deterministic).
  http.post('/api/references/best-match', async ({ request }) => {
    const body = (await request.json()) as { query?: string; items?: ReferenceItem[] }
    const terms = (body.query ?? '').toLowerCase().split(/\s+/).filter(Boolean)
    const items = Array.isArray(body.items) ? body.items : []
    const matches = items
      .map((item) => {
        const text = `${item.title ?? ''} ${item.description ?? ''}`.toLowerCase()
        const overlap = terms.filter((t) => text.includes(t)).length
        return {
          id: item.id,
          score: Math.min(1, overlap / Math.max(1, terms.length)),
          reason: overlap > 0 ? 'Overlaps your query terms.' : 'Weak overlap with your query terms.',
        }
      })
      .sort((a, b) => b.score - a.score)
      .slice(0, 3)
    return HttpResponse.json({ matches })
  }),

  // Semantic "Find similar" — returns a deterministic Hydra collection of library
  // neighbours ranked by a mock cosine score.
  http.get('/api/similar-references', () => {
    const member = [
      { '@id': '/similar-references/impc:measure:2', id: 'impc:measure:2', source: 'impc', sourceName: 'IMPReSS (IMPC)', type: 'measure', title: 'Blood glucose level', description: 'Fasted blood glucose', score: 0.94, identifiers: {} },
      { '@id': '/similar-references/nih_cde:cde_element:3', id: 'nih_cde:cde_element:3', source: 'nih_cde', sourceName: 'NIH CDE Repository', type: 'cde_element', title: 'Glucose, blood', description: 'Glucose measured in blood', score: 0.88, identifiers: {} },
    ]
    return HttpResponse.json({ 'hydra:member': member, 'hydra:totalItems': member.length })
  }),

  // Federated reference search — returns mock results covering the new source types.
  http.get('*/api/reference-search', ({ request }) => {
    const url = new URL(request.url)
    const q = (url.searchParams.get('q') ?? '').toLowerCase()
    return HttpResponse.json({
      'hydra:member': [
        {
          id: 'cedar:schema:mock-cedar-1',
          source: 'cedar',
          sourceName: 'CEDAR Workbench',
          type: 'schema',
          title: `CEDAR template: ${q}`,
          subtitle: 'Template',
          description: 'A CEDAR metadata schema for preclinical studies.',
          externalUrl: 'https://cedar.metadatacenter.org/tools/template-editor/mock-cedar-1',
          identifiers: { iri: 'https://repo.metadatacenter.org/templates/mock-cedar-1', version: '1.0.0' },
          raw: { properties: { animalId: { 'schema:name': 'Animal ID', 'schema:description': 'Unique animal identifier', '_valueConstraints': {} }, bodyWeight: { 'schema:name': 'Body weight', 'schema:description': 'Animal body weight in grams', '_valueConstraints': {} } } },
        },
        {
          id: 'elabftw:template:42',
          source: 'elabftw',
          sourceName: 'ElabFTW',
          type: 'template',
          title: `ElabFTW experiment template: ${q}`,
          subtitle: 'Experiment template',
          description: 'Standard experiment template.',
          externalUrl: null,
          identifiers: { elabftw_id: 42, kind: 'template' },
          raw: { id: 42, title: 'Experiment template', metadata: JSON.stringify({ extra_fields: { dosage: { name: 'Dosage', type: 'number', units: 'mg/kg' }, route: { name: 'Route of administration', type: 'select', description: 'How the compound was administered' } } }) },
        },
        {
          id: 'guidelines_hub:guideline:arrive:1',
          source: 'guidelines_hub',
          sourceName: 'ARRIVE / PREPARE / EQIPD',
          type: 'guideline',
          title: 'ARRIVE 2.0 — Study design',
          subtitle: 'ARRIVE 2.0',
          description: 'For each experiment, provide brief details of the study design including: the groups being compared, and the experimental unit.',
          externalUrl: 'https://arriveguidelines.org/arrive-guidelines',
          identifiers: { guideline_id: 'arrive:1', source: 'arrive' },
          raw: {},
        },
        {
          id: 'mnms:schema:RepetitionTime',
          source: 'mnms',
          sourceName: 'MNMS (Neuroimaging)',
          type: 'schema',
          title: 'RepetitionTime',
          subtitle: 'Unit: s',
          description: 'MRI repetition time in seconds.',
          externalUrl: 'https://bids-specification.readthedocs.io/en/stable/',
          identifiers: { field: 'RepetitionTime', datatype: 'number', unit: 's' },
          raw: { name: 'RepetitionTime', description: 'MRI repetition time in seconds.', type: 'number', unit: 's' },
        },
        {
          id: 'bioportal:ontology_class:HCMO_0000001',
          source: 'bioportal',
          sourceName: 'BioPortal',
          type: 'ontology_class',
          title: 'home cage monitoring assay',
          subtitle: 'HCMO',
          description: 'A behavioral assay performed in the animal\'s home cage.',
          externalUrl: 'https://bioportal.bioontology.org/ontologies/HCMO?p=classes&conceptid=http%3A%2F%2Fpurl.obolibrary.org%2Fobo%2FHCMO_0000001',
          identifiers: { iri: 'http://purl.obolibrary.org/obo/HCMO_0000001', ontology: 'HCMO' },
          raw: {},
        },
      ],
      'hydra:totalItems': 5,
    })
  }),

  // Schema field comparison — returns a deterministic harmonization result.
  http.post('*/api/references/compare-schemas', async () => {
    return HttpResponse.json({
      fieldGroups: [
        {
          groupId: 'fg-1',
          canonicalLabel: 'Animal ID',
          canonicalDatatype: 'string',
          canonicalUnit: null,
          confidence: 0.92,
          members: [
            { sourceRef: 'cedar:schema:mock-cedar-1/properties/animalId', mappingType: 'exact', label: 'Animal ID', evidence: 'Identical label' },
            { sourceRef: 'elabftw:template:42/extra_fields/animal_id', mappingType: 'close', label: 'animal_id', evidence: 'Label matches after normalization' },
          ],
          conflicts: [],
        },
        {
          groupId: 'fg-2',
          canonicalLabel: 'Body weight',
          canonicalDatatype: 'number',
          canonicalUnit: 'g',
          confidence: 0.88,
          members: [
            { sourceRef: 'cedar:schema:mock-cedar-1/properties/bodyWeight', mappingType: 'exact', label: 'Body weight', evidence: 'Identical label' },
            { sourceRef: 'elabftw:template:42/extra_fields/dosage', mappingType: 'related', label: 'Dosage', evidence: 'Related numeric measurement' },
          ],
          conflicts: [{ field: 'unit', values: ['g', 'mg/kg'], recommendation: 'Use g for body weight' }],
        },
      ],
      aiAvailable: true,
      warnings: [],
      usage: { inputTokens: 1200, outputTokens: 350 },
    })
  }),
]
