import { http, HttpResponse } from 'msw'

/**
 * MSW handlers for the International Guidelines Reporting feature.
 *
 * These mirror the REAL backend wire contract EXACTLY (mixed case — there is no
 * global serializer name converter):
 *  - `GET /guidelines/templates` is an API Platform GetCollection → Hydra collection.
 *  - Resource TOP-LEVEL props are camelCase; nested arrays + controller JSON are snake_case.
 *  - `bySeverity`/`bySection` are name-keyed OBJECTS; `bySection.fields` are field-id strings,
 *    and the rich per-field records live in the top-level `fields[]`.
 *  - ai-draft is FLAT with `available` (not `aiAvailable`).
 *
 * An in-memory shared metadata dict keyed by `${res}:${id}:${fieldId}` keeps the
 * crosswalk live: saving `humane_endpoints` satisfies `prepare_humane_endpoints`
 * with `satisfied_by: { metadata: "humane_endpoints", via_crosswalk: true }`.
 */

type Severity = 'high' | 'medium' | 'low'
type Status = 'satisfied' | 'partial' | 'missing'

type MetaField = {
  id: string
  arriveSection: string | null
  prepareSection: string | null
  eqipdSection: string | null
  guidance: string | null
  prompt: string | null
  crosswalk: string[]
  severity: Severity
}

const ARRIVE_FIELDS: MetaField[] = [
  {
    id: 'study_title',
    arriveSection: 'Study details',
    prepareSection: null,
    eqipdSection: null,
    guidance: 'Provide a concise, descriptive title for the study.',
    prompt: null,
    crosswalk: [],
    severity: 'high',
  },
  {
    id: 'species',
    arriveSection: 'Experimental animals',
    prepareSection: null,
    eqipdSection: null,
    guidance: 'State the species (and strain where relevant) used.',
    prompt: null,
    crosswalk: [],
    severity: 'high',
  },
  {
    id: 'sample_size_justification',
    arriveSection: 'Study design and sample size',
    prepareSection: null,
    eqipdSection: null,
    guidance: 'Explain how the sample size was decided (power analysis or rationale).',
    prompt: null,
    crosswalk: [],
    severity: 'high',
  },
  {
    id: 'randomisation_method',
    arriveSection: 'Randomisation',
    prepareSection: null,
    eqipdSection: null,
    guidance: 'Describe the method used to allocate animals to groups.',
    prompt: null,
    crosswalk: [],
    severity: 'high',
  },
  {
    id: 'humane_endpoints',
    arriveSection: 'Animal care and monitoring',
    prepareSection: null,
    eqipdSection: null,
    guidance: 'Define the criteria used to determine humane endpoints.',
    prompt: null,
    crosswalk: [],
    severity: 'medium',
  },
]

const PREPARE_FIELDS: MetaField[] = [
  {
    id: 'prepare_literature_searches',
    arriveSection: null,
    prepareSection: '1. Literature searches',
    eqipdSection: null,
    guidance: null,
    prompt: 'Summarise the literature searches that informed this study plan.',
    crosswalk: [],
    severity: 'medium',
  },
  {
    id: 'prepare_humane_endpoints',
    arriveSection: 'Animal care and monitoring',
    prepareSection: '3. Ethical issues, harm-benefit assessment and humane endpoints',
    eqipdSection: null,
    guidance: null,
    prompt: 'Describe the humane endpoints planned before the study begins.',
    // Auto-satisfied if the ARRIVE humane_endpoints field is filled.
    crosswalk: ['humane_endpoints'],
    severity: 'high',
  },
  {
    id: 'prepare_pilot_power_significance',
    arriveSection: 'Study design and sample size',
    prepareSection: '4. Experimental design and statistical analysis',
    eqipdSection: null,
    guidance: null,
    prompt: 'State the planned power / pilot study and significance threshold.',
    crosswalk: ['sample_size_justification'],
    severity: 'high',
  },
  {
    id: 'prepare_staff_competence',
    arriveSection: null,
    prepareSection: '7. Education and training',
    eqipdSection: null,
    guidance: null,
    prompt: 'Confirm staff competence and training relevant to the procedures.',
    crosswalk: [],
    severity: 'medium',
  },
]

type TemplateDef = {
  id: string
  name: string
  version: string
  description: string
  identifier: string | null
  conformsTo: string[]
  fields: MetaField[]
}

const TEMPLATES: Record<string, TemplateDef> = {
  'arrive-v2': {
    id: 'arrive-v2',
    name: 'ARRIVE 2.0',
    version: '2.0',
    description: 'Reporting standard — what you must report when publishing in-vivo research.',
    identifier: 'https://arriveguidelines.org',
    conformsTo: [],
    fields: ARRIVE_FIELDS,
  },
  'prepare-v1': {
    id: 'prepare-v1',
    name: 'PREPARE',
    version: '1.0',
    description: 'Pre-study planning checklist — what you must plan before touching an animal.',
    identifier: 'https://norecopa.no/prepare',
    conformsTo: [],
    fields: PREPARE_FIELDS,
  },
}

const TEMPLATE_SUMMARIES = [
  {
    templateId: 'arrive-v2',
    name: 'ARRIVE 2.0',
    version: '2.0',
    description: 'Reporting standard — what you must report when publishing in-vivo research.',
    identifier: 'https://arriveguidelines.org',
    conformsTo: [],
    requiredColumnCount: 0,
    optionalColumnCount: 0,
    requiredMetadataCount: ARRIVE_FIELDS.length,
    fairBonus: { dimension: 'R', max_points: 5 },
  },
  {
    templateId: 'prepare-v1',
    name: 'PREPARE',
    version: '1.0',
    description: 'Pre-study planning checklist — what you must plan before touching an animal.',
    identifier: 'https://norecopa.no/prepare',
    conformsTo: [],
    requiredColumnCount: 0,
    optionalColumnCount: 0,
    requiredMetadataCount: PREPARE_FIELDS.length,
    fairBonus: { dimension: 'R', max_points: 5 },
  },
  {
    templateId: 'eqipd-v1',
    name: 'EQIPD Quality System',
    version: '1.0',
    description: 'Research-led quality framework — 18 Core Requirements for the unit.',
    identifier: null,
    conformsTo: [],
    requiredColumnCount: 0,
    optionalColumnCount: 0,
    requiredMetadataCount: 18,
    fairBonus: { dimension: 'R', max_points: 5 },
  },
  {
    templateId: 'mnms-v1',
    name: 'MNMS (Minimum Metadata Schema)',
    version: '1.0',
    description: 'Column-level standard — required CSV columns + units + roles.',
    identifier: null,
    conformsTo: ['arrive-v2'],
    requiredColumnCount: 20,
    optionalColumnCount: 2,
    requiredMetadataCount: ARRIVE_FIELDS.length,
    fairBonus: { dimension: 'R', max_points: 5 },
  },
  {
    templateId: 'arrive-prepare-crosswalk-v1',
    name: 'ARRIVE + PREPARE crosswalk',
    version: '1.0',
    description: 'Union of ARRIVE reporting and PREPARE planning fields.',
    identifier: null,
    conformsTo: ['arrive-v2', 'prepare-v1'],
    requiredColumnCount: 0,
    optionalColumnCount: 0,
    requiredMetadataCount: ARRIVE_FIELDS.length + PREPARE_FIELDS.length,
    fairBonus: { dimension: 'R', max_points: 5 },
  },
]

// Shared in-memory metadata dict keyed by `${res}:${id}:${fieldId}`.
const metadataStore = new Map<string, string>()
const keyFor = (res: string, id: string, fieldId: string) => `${res}:${id}:${fieldId}`
const isFilled = (res: string, id: string, fieldId: string) => {
  const v = metadataStore.get(keyFor(res, id, fieldId))
  return v != null && v !== ''
}

// ──────────────────────────────────────────────────────── QC review mocks ──

type FieldReviewState = { review_status: 'draft' | 'reviewed'; reviewed_by: string | null; reviewed_at: string | null }
const MOCK_REVIEWER = 'demo.user@metadatapp.test'
const fieldReviewStore = new Map<string, FieldReviewState>()
const fieldReviewFor = (res: string, id: string, fieldId: string): FieldReviewState =>
  fieldReviewStore.get(keyFor(res, id, fieldId)) ?? { review_status: 'draft', reviewed_by: null, reviewed_at: null }

type ReportReviewEvent = {
  action: string
  field_id: string | null
  actor: string
  occurred_at: string
  note: string | null
}
const reportReviewHistory = new Map<string, ReportReviewEvent[]>()
const reportKeyFor = (res: string, id: string, templateId: string) => `${res}:${id}:${templateId}`
const reportReviewSummary = (res: string, id: string, templateId: string) => {
  const history = reportReviewHistory.get(reportKeyFor(res, id, templateId)) ?? []
  const latest = [...history].reverse().find((e) => e.action === 'report_approved' || e.action === 'report_changes_requested')
  if (!latest) return { status: 'not_reviewed' as const, reviewed_by: null, reviewed_at: null, note: null }
  if (latest.action === 'report_approved') {
    return { status: 'approved' as const, reviewed_by: latest.actor, reviewed_at: latest.occurred_at, note: latest.note }
  }
  return { status: 'changes_requested' as const, reviewed_by: latest.actor, reviewed_at: latest.occurred_at, note: latest.note }
}

// ─────────────────────────────────────────────── deterministic intelligence ──

type EvidenceSource =
  | 'computed'
  | 'elabftw'
  | 'connected_link'
  | 'history'
  | 'fair'
  | 'entity'
  | 'reference'

type WireEvidenceItem = {
  source: EvidenceSource
  label: string
  value?: string | null
  provenance: string
  field_id?: string | null
  confidence?: number | null
  url?: string | null
}

type WireSuggestion = {
  value: string
  source: EvidenceSource
  provenance: string
  confidence?: number | null
}

/**
 * Per-field deterministic facts the backend would derive (computed totals,
 * connected-app links, prior-investigation history). Keyed by field id. Realistic
 * enough that at least one field is one-click satisfiable offline.
 */
const FIELD_SUGGESTIONS: Record<string, WireSuggestion[]> = {
  species: [
    {
      value: 'Mus musculus (C57BL/6J)',
      source: 'computed',
      provenance: 'computed from linked dataset columns',
      confidence: 0.95,
    },
    {
      value: 'Mus musculus',
      source: 'history',
      provenance: 'from Investigation "Cohort A 2024"',
      confidence: 0.8,
    },
  ],
  randomisation_method: [
    {
      value: 'Computer-generated random allocation sequence',
      source: 'history',
      provenance: 'from Investigation "Cohort A 2024"',
      confidence: 0.7,
    },
  ],
  sample_size_justification: [
    {
      value: 'Total subjects: 48 (power analysis, 80% power, alpha 0.05)',
      source: 'computed',
      provenance: 'computed total-subjects fact: 48',
      confidence: 0.9,
    },
  ],
  humane_endpoints: [
    {
      value: 'Body weight loss > 20% or persistent distress score ≥ 3',
      source: 'elabftw',
      provenance: 'from elabFTW protocol "Welfare monitoring SOP"',
      confidence: 0.85,
    },
  ],
}

const computedEvidenceFor = (fieldId: string): WireEvidenceItem[] => {
  const items: WireEvidenceItem[] = []
  if (fieldId === 'sample_size_justification') {
    items.push({
      source: 'computed',
      label: 'Total subjects',
      value: '48',
      provenance: 'computed from linked dataset rows',
      field_id: fieldId,
      confidence: 0.95,
    })
  }
  if (fieldId === 'humane_endpoints') {
    items.push({
      source: 'elabftw',
      label: 'Welfare monitoring SOP',
      value: 'Body weight loss > 20%',
      provenance: 'from elabFTW protocol',
      field_id: fieldId,
      confidence: 0.85,
    })
  }
  return items
}

const usedEvidenceFor = (fieldId: string): WireEvidenceItem[] => {
  const computed = computedEvidenceFor(fieldId).map(({ field_id: _fieldId, confidence: _c, ...rest }) => rest)
  // Always cite the FAIR provenance ledger so the citation list is never empty.
  return [
    ...computed,
    {
      source: 'fair',
      label: 'FAIR provenance ledger',
      value: null,
      provenance: 'reuse of prior validated metadata',
    },
    // Canonical standard text + official URL from the GuidelinesHub (recommendation #2).
    {
      source: 'reference',
      label: 'ARRIVE 2.0 — Experimental animals',
      value: 'Provide species-appropriate details of the animals used…',
      provenance: 'ARRIVE 2.0 reference (arriveguidelines.org)',
      confidence: 1.0,
      url: 'https://arriveguidelines.org/arrive-guidelines',
    },
  ]
}

const suggestionsFor = (fieldId: string): WireSuggestion[] => FIELD_SUGGESTIONS[fieldId] ?? []

const isTemplate = (templateId: string): boolean => templateId in TEMPLATES
const sectionsOf = (f: MetaField): string[] => {
  const s = [f.arriveSection, f.prepareSection, f.eqipdSection].filter(
    (x): x is string => x != null && x !== '',
  )
  return s.length > 0 ? s : ['(unsectioned)']
}
const firstSection = (f: MetaField): string | null => f.arriveSection ?? f.prepareSection ?? f.eqipdSection

type WireEntry = {
  standard: string
  section: string | null
  arrive_section: string | null
  prepare_section: string | null
  eqipd_section: string | null
  field_id: string
  status: Status
  satisfied_by: { column?: string; metadata?: string; via_crosswalk?: true } | null
  severity: Severity
  is_column_field: boolean
}

const computeEntries = (res: string, id: string, template: TemplateDef): WireEntry[] => {
  return template.fields.map((f): WireEntry => {
    const directlyFilled = isFilled(res, id, f.id)
    let status: Status = directlyFilled ? 'satisfied' : 'missing'
    let satisfiedBy: WireEntry['satisfied_by'] = directlyFilled ? { metadata: f.id } : null

    // Pass 2 / Step B — crosswalk auto-satisfaction (only upgrades).
    if (status !== 'satisfied' && f.crosswalk.length > 0) {
      for (const target of f.crosswalk) {
        if (isFilled(res, id, target)) {
          status = 'satisfied'
          satisfiedBy = { metadata: target, via_crosswalk: true }
          break
        }
      }
    }

    return {
      standard: template.name,
      section: firstSection(f),
      arrive_section: f.arriveSection,
      prepare_section: f.prepareSection,
      eqipd_section: f.eqipdSection,
      field_id: f.id,
      status,
      satisfied_by: satisfiedBy,
      severity: f.severity,
      is_column_field: false,
    }
  })
}

export const guidelinesHandlers = [
  // Hydra GetCollection.
  http.get('/api/guidelines/templates', () =>
    HttpResponse.json({
      '@context': '/api/contexts/GuidelineTemplate',
      '@id': '/api/guidelines/templates',
      '@type': 'hydra:Collection',
      'hydra:member': TEMPLATE_SUMMARIES,
      'hydra:totalItems': TEMPLATE_SUMMARIES.length,
    }),
  ),

  http.get('/api/guidelines/templates/:templateId', ({ params }) => {
    const templateId = String(params.templateId)
    if (!isTemplate(templateId)) {
      return HttpResponse.json({ message: 'Template not found' }, { status: 404 })
    }
    const t = TEMPLATES[templateId]
    const summary = TEMPLATE_SUMMARIES.find((s) => s.templateId === templateId)!
    return HttpResponse.json({
      ...summary,
      requiredColumns: [],
      optionalColumns: [],
      requiredMetadata: t.fields.map((f) => ({
        id: f.id,
        arrive_section: f.arriveSection,
        prepare_section: f.prepareSection,
        eqipd_section: f.eqipdSection,
        guidance: f.guidance,
        prompt: f.prompt,
        crosswalk: f.crosswalk,
        severity: f.severity,
      })),
    })
  }),

  http.get('/api/:res/:id/guidelines/:templateId/conformance', ({ params }) => {
    const res = String(params.res)
    const id = String(params.id)
    const templateId = String(params.templateId)
    if (!isTemplate(templateId)) {
      return HttpResponse.json({ message: 'Template not found' }, { status: 404 })
    }
    const template = TEMPLATES[templateId]
    const entries = computeEntries(res, id, template)
    const satisfied = entries.filter((e) => e.status === 'satisfied').length
    const total = entries.length
    const satisfiedPct = total === 0 ? 0 : Math.round((satisfied / total) * 100)
    const maxPoints = 5
    const points = Math.round((satisfiedPct / 100) * maxPoints)

    return HttpResponse.json({
      id: `${id}:${templateId}`,
      resourceType: res === 'investigations' ? 'investigation' : 'study',
      templateId,
      standard: template.name,
      version: template.version,
      score: { dimension: 'R', points, max_points: maxPoints, satisfied_pct: satisfiedPct },
      totals: { satisfied, total },
      entries,
    })
  }),

  http.get('/api/:res/:id/guidelines/:templateId/completion', ({ params }) => {
    const res = String(params.res)
    const id = String(params.id)
    const templateId = String(params.templateId)
    if (!isTemplate(templateId)) {
      return HttpResponse.json({ message: 'Template not found' }, { status: 404 })
    }
    const template = TEMPLATES[templateId]
    const entries = computeEntries(res, id, template)
    const byFieldId = new Map(template.fields.map((f) => [f.id, f]))

    const totals = { satisfied_direct: 0, satisfied_via_crosswalk: 0, partial: 0, missing: 0 }
    const bySeverity: Record<string, { satisfied: number; partial: number; missing: number }> = {}
    const bySection: Record<
      string,
      { satisfied: number; partial: number; missing: number; fields: string[] }
    > = {}

    const fields = entries.map((entry) => {
      const field = byFieldId.get(entry.field_id)!
      const viaCrosswalk = Boolean(entry.satisfied_by && entry.satisfied_by.via_crosswalk === true)

      if (entry.status === 'satisfied') {
        if (viaCrosswalk) totals.satisfied_via_crosswalk += 1
        else totals.satisfied_direct += 1
      } else if (entry.status === 'partial') {
        totals.partial += 1
      } else {
        totals.missing += 1
      }

      const bucket: 'satisfied' | 'partial' | 'missing' = entry.status
      bySeverity[entry.severity] ??= { satisfied: 0, partial: 0, missing: 0 }
      bySeverity[entry.severity][bucket] += 1

      // Multi-section membership: count under each declared section.
      for (const section of sectionsOf(field)) {
        bySection[section] ??= { satisfied: 0, partial: 0, missing: 0, fields: [] }
        bySection[section][bucket] += 1
        bySection[section].fields.push(entry.field_id)
      }

      const value = metadataStore.get(keyFor(res, id, entry.field_id)) ?? null
      const review = fieldReviewFor(res, id, entry.field_id)
      return {
        ...entry,
        value,
        has_value: value != null && value !== '',
        satisfied_via_crosswalk: viaCrosswalk,
        satisfying_sibling: viaCrosswalk && entry.satisfied_by?.metadata ? entry.satisfied_by.metadata : null,
        review_status: review.review_status,
        reviewed_by: review.reviewed_by,
        reviewed_at: review.reviewed_at,
      }
    })

    return HttpResponse.json({
      id: `${id}:${templateId}`,
      resourceType: res === 'investigations' ? 'investigation' : 'study',
      templateId,
      standard: template.name,
      totals,
      bySeverity,
      bySection,
      fields,
      reportReview: reportReviewSummary(res, id, templateId),
    })
  }),

  http.put('/api/:res/:id/guidelines/:templateId/fields/:fieldId', async ({ params, request }) => {
    const res = String(params.res)
    const id = String(params.id)
    const templateId = String(params.templateId)
    const fieldId = String(params.fieldId)
    const body = (await request.json().catch(() => ({}))) as { value?: unknown }
    const value = typeof body.value === 'string' ? body.value : body.value == null ? '' : String(body.value)
    metadataStore.set(keyFor(res, id, fieldId), value)
    // An edited value needs a fresh QC pass — mirrors the backend reset-on-edit rule.
    fieldReviewStore.set(keyFor(res, id, fieldId), { review_status: 'draft', reviewed_by: null, reviewed_at: null })
    return HttpResponse.json({
      resource_type: res === 'investigations' ? 'investigation' : 'study',
      resource_id: id,
      template_id: templateId,
      field_id: fieldId,
      value,
      source: 'manual',
      updated_at: new Date().toISOString(),
      review_status: 'draft',
    })
  }),

  http.post('/api/:res/:id/guidelines/:templateId/fields/:fieldId/ai-draft', ({ params }) => {
    const fieldId = String(params.fieldId)
    return HttpResponse.json({
      field_id: fieldId,
      value: `Drafted suggestion for ${fieldId}: animals were allocated to groups using a computer-generated random sequence.`,
      rationale: 'Grounded in the section context and severity; user should review and edit before saving.',
      available: true,
      provider: 'mock',
      model: 'mock-llm',
      message: '',
      persisted: false,
      used_evidence: usedEvidenceFor(fieldId),
      suggestions: suggestionsFor(fieldId),
    })
  }),

  http.get('/api/:res/:id/guidelines/:templateId/fields/:fieldId/evidence', ({ params }) => {
    const fieldId = String(params.fieldId)
    return HttpResponse.json({
      field_id: fieldId,
      computed: computedEvidenceFor(fieldId),
      suggestions: suggestionsFor(fieldId),
      evidence: usedEvidenceFor(fieldId),
    })
  }),

  http.post('/api/:res/:id/guidelines/:templateId/ai-draft-all', ({ params }) => {
    const res = String(params.res)
    const id = String(params.id)
    const templateId = String(params.templateId)
    if (!isTemplate(templateId)) {
      return HttpResponse.json({ message: 'Template not found' }, { status: 404 })
    }
    const template = TEMPLATES[templateId]
    // Draft only the still-missing fields (the crosswalk may already satisfy some).
    const entries = computeEntries(res, id, template)
    const missing = entries.filter((e) => e.status !== 'satisfied').map((e) => e.field_id)

    const drafted = missing.map((fieldId) => ({
      field_id: fieldId,
      value: `Drafted value for ${fieldId} grounded in deterministic facts.`,
      rationale: 'Synthesised from computed facts, history, and connected protocols.',
      available: true,
      used_evidence: usedEvidenceFor(fieldId),
      suggestions: suggestionsFor(fieldId),
    }))

    return HttpResponse.json({
      template_id: templateId,
      drafted,
      // Field ids that were already satisfied are not re-drafted.
      skipped: entries.filter((e) => e.status === 'satisfied').map((e) => e.field_id),
      capped: false,
    })
  }),

  http.get('/api/:res/:id/guidelines/:templateId/report.md', ({ params }) => {
    const templateId = String(params.templateId)
    const name = isTemplate(templateId) ? TEMPLATES[templateId].name : templateId
    return HttpResponse.text(`# ${name} report\n\nGenerated guideline conformance report (mock).\n`, {
      headers: { 'Content-Type': 'text/markdown; charset=UTF-8' },
    })
  }),

  http.get('/api/:res/:id/guidelines/:templateId/report.zip', () =>
    HttpResponse.arrayBuffer(new TextEncoder().encode('PK mock zip bytes').buffer, {
      headers: { 'Content-Type': 'application/zip' },
    }),
  ),

  http.post('/api/:res/:id/guidelines/:templateId/fields/:fieldId/review', async ({ params, request }) => {
    const res = String(params.res)
    const id = String(params.id)
    const templateId = String(params.templateId)
    const fieldId = String(params.fieldId)

    if (!isFilled(res, id, fieldId)) {
      return HttpResponse.json({ message: `Field "${fieldId}" has no value yet — nothing to review.` }, { status: 404 })
    }

    const body = (await request.json().catch(() => ({}))) as { status?: string }
    const status = body.status === 'reviewed' ? 'reviewed' : 'draft'
    const state: FieldReviewState =
      status === 'reviewed'
        ? { review_status: 'reviewed', reviewed_by: MOCK_REVIEWER, reviewed_at: new Date().toISOString() }
        : { review_status: 'draft', reviewed_by: null, reviewed_at: null }
    fieldReviewStore.set(keyFor(res, id, fieldId), state)

    return HttpResponse.json({
      resource_type: res === 'investigations' ? 'investigation' : 'study',
      resource_id: id,
      template_id: templateId,
      field_id: fieldId,
      ...state,
    })
  }),

  http.post('/api/:res/:id/guidelines/:templateId/review', async ({ params, request }) => {
    const res = String(params.res)
    const id = String(params.id)
    const templateId = String(params.templateId)
    const body = (await request.json().catch(() => ({}))) as { decision?: string; note?: string }
    const action =
      body.decision === 'approved'
        ? 'report_approved'
        : body.decision === 'changes_requested'
          ? 'report_changes_requested'
          : null
    if (!action) {
      return HttpResponse.json(
        { message: 'Body must be a JSON object with a "decision" key of "approved" or "changes_requested".' },
        { status: 400 },
      )
    }

    const key = reportKeyFor(res, id, templateId)
    const history = reportReviewHistory.get(key) ?? []
    history.push({
      action,
      field_id: null,
      actor: MOCK_REVIEWER,
      occurred_at: new Date().toISOString(),
      note: body.note ?? null,
    })
    reportReviewHistory.set(key, history)

    return HttpResponse.json({
      resource_type: res === 'investigations' ? 'investigation' : 'study',
      resource_id: id,
      template_id: templateId,
      ...reportReviewSummary(res, id, templateId),
      history: [...history].reverse(),
    })
  }),

  http.get('/api/:res/:id/guidelines/:templateId/review', ({ params }) => {
    const res = String(params.res)
    const id = String(params.id)
    const templateId = String(params.templateId)
    const history = reportReviewHistory.get(reportKeyFor(res, id, templateId)) ?? []

    return HttpResponse.json({
      resource_type: res === 'investigations' ? 'investigation' : 'study',
      resource_id: id,
      template_id: templateId,
      ...reportReviewSummary(res, id, templateId),
      history: [...history].reverse(),
    })
  }),
]
