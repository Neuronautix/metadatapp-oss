import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { guidelinesHandlers } from '@/mocks/handlers/guidelines.ts'
import {
  aiDraftAll,
  aiDraftGuidelineField,
  getCompletion,
  getConformance,
  getFieldEvidence,
  getGuidelineReview,
  getGuidelineTemplate,
  getGuidelineTemplates,
  reviewGuidelineField,
  setGuidelineField,
  submitGuidelineReviewDecision,
} from './guidelines.api.ts'

/**
 * Contract test: runs the REAL api functions (wire → domain mappers) against the
 * REAL MSW handlers, so a mock/contract divergence surfaces here rather than as a
 * live 4xx/shape break (the trap AGENTS.md warns about). The MSW handlers mirror
 * the mixed-case backend wire shape exactly; these assertions prove the mappers
 * reconcile that wire shape into the clean domain shape the components consume.
 */
const server = setupServer(...guidelinesHandlers)

const RES = 'investigations'
const ID = '11111111-1111-1111-1111-111111111111'

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

describe('guidelines.api wire→domain mapping', () => {
  it('maps the Hydra template collection (templateId → id)', async () => {
    const templates = await getGuidelineTemplates()
    expect(templates.length).toBeGreaterThanOrEqual(2)
    const arrive = templates.find((t) => t.id === 'arrive-v2')
    expect(arrive).toBeDefined()
    expect(arrive!.name).toBe('ARRIVE 2.0')
    // domain field names, not the wire ones.
    expect(arrive!.requiredMetadataCount).toBeGreaterThan(0)
    expect(arrive).not.toHaveProperty('templateId')
  })

  it('maps the resolved template (snake_case nested arrays → camelCase)', async () => {
    const template = await getGuidelineTemplate('arrive-v2')
    expect(template.id).toBe('arrive-v2')
    expect(template.requiredMetadata.length).toBeGreaterThan(0)
    const field = template.requiredMetadata[0]
    expect(field).toHaveProperty('arriveSection')
    expect(field).not.toHaveProperty('arrive_section')
  })

  it('maps conformance totals {satisfied,total} and score.max_points', async () => {
    const report = await getConformance(RES, ID, 'arrive-v2')
    expect(report.totals).toHaveProperty('satisfied')
    expect(report.totals).toHaveProperty('total')
    expect(report.score.maxPoints).toBe(5)
    const entry = report.entries[0]
    expect(entry).toHaveProperty('fieldId')
    expect(entry).toHaveProperty('arriveSection')
  })

  it('maps completion name-keyed bySection objects into a domain array with joined fields', async () => {
    const report = await getCompletion(RES, ID, 'arrive-v2')
    expect(Array.isArray(report.bySection)).toBe(true)
    expect(report.bySection.length).toBeGreaterThan(0)
    const section = report.bySection[0]
    expect(typeof section.section).toBe('string')
    // fields are the joined rich records, not bare id strings.
    expect(section.fields[0]).toHaveProperty('fieldId')
    expect(section.fields[0]).toHaveProperty('hasValue')
    expect(report.totals).toHaveProperty('satisfiedViaCrosswalk')
  })

  it('keeps the crosswalk live: saving an ARRIVE field satisfies its PREPARE sibling', async () => {
    // Before: prepare_humane_endpoints is missing on the crosswalk template.
    const before = await getConformance(RES, ID, 'prepare-v1')
    const prepBefore = before.entries.find((e) => e.fieldId === 'prepare_humane_endpoints')
    expect(prepBefore?.status).toBe('missing')

    // Save the ARRIVE humane_endpoints concept once.
    await setGuidelineField(RES, ID, 'arrive-v2', 'humane_endpoints', 'Body weight loss > 20%')

    // After: the PREPARE sibling is satisfied via crosswalk with the real shape.
    const after = await getCompletion(RES, ID, 'prepare-v1')
    const prepField = after.bySection
      .flatMap((s) => s.fields)
      .find((f) => f.fieldId === 'prepare_humane_endpoints')
    expect(prepField?.status).toBe('satisfied')
    expect(prepField?.viaCrosswalk).toBe(true)
    expect(prepField?.satisfyingSibling).toBe('humane_endpoints')
  })

  it('maps the flat ai-draft response (available, top-level value/rationale)', async () => {
    const draft = await aiDraftGuidelineField(RES, ID, 'arrive-v2', 'randomisation_method')
    expect(draft.available).toBe(true)
    expect(draft.fieldId).toBe('randomisation_method')
    expect(typeof draft.value).toBe('string')
    expect(typeof draft.rationale).toBe('string')
    expect(draft).not.toHaveProperty('aiAvailable')
  })

  it('maps the ai-draft evidence extras (used_evidence + suggestions → camelCase)', async () => {
    const draft = await aiDraftGuidelineField(RES, ID, 'arrive-v2', 'sample_size_justification')
    expect(Array.isArray(draft.usedEvidence)).toBe(true)
    expect(draft.usedEvidence.length).toBeGreaterThan(0)
    const computed = draft.usedEvidence.find((e) => e.source === 'computed')
    expect(computed).toBeDefined()
    // domain field names, not the wire ones.
    expect(computed).toHaveProperty('label')
    expect(draft.suggestions.length).toBeGreaterThan(0)
    expect(draft.suggestions[0]).toHaveProperty('value')
    expect(draft.suggestions[0]).toHaveProperty('provenance')
  })

  it('maps the field-evidence endpoint (computed/suggestions/evidence)', async () => {
    const evidence = await getFieldEvidence(RES, ID, 'arrive-v2', 'sample_size_justification')
    expect(evidence.fieldId).toBe('sample_size_justification')
    expect(evidence.computed.length).toBeGreaterThan(0)
    const computed = evidence.computed[0]
    expect(computed).toHaveProperty('fieldId') // mapped from field_id
    expect(computed).not.toHaveProperty('field_id')
    expect(computed).toHaveProperty('confidence')
    expect(evidence.suggestions.length).toBeGreaterThan(0)
    expect(evidence.evidence.length).toBeGreaterThan(0)
  })

  it('maps a `reference` evidence item, carrying its official `url`', async () => {
    const evidence = await getFieldEvidence(RES, ID, 'arrive-v2', 'species')
    const reference = evidence.evidence.find((e) => e.source === 'reference')
    expect(reference).toBeDefined()
    // The additive `url` key maps through and survives as a string.
    expect(reference!.url).toBe('https://arriveguidelines.org/arrive-guidelines')
    expect(reference!.label).toContain('ARRIVE 2.0')
    // Non-reference items default to a null url (undefined-safe mapping).
    const fair = evidence.evidence.find((e) => e.source === 'fair')
    expect(fair!.url).toBeNull()
  })

  it('maps the bulk ai-draft-all response (snake_case → domain)', async () => {
    const bulk = await aiDraftAll(RES, ID, 'arrive-v2')
    expect(bulk.templateId).toBe('arrive-v2')
    expect(Array.isArray(bulk.drafted)).toBe(true)
    expect(bulk.drafted.length).toBeGreaterThan(1)
    expect(Array.isArray(bulk.skipped)).toBe(true)
    expect(typeof bulk.capped).toBe('boolean')
    const item = bulk.drafted[0]
    expect(item).toHaveProperty('fieldId')
    expect(item).toHaveProperty('usedEvidence')
    expect(item).toHaveProperty('suggestions')
  })

  it('maps completion field review state and the report-review summary (snake_case → domain)', async () => {
    const report = await getCompletion(RES, ID, 'arrive-v2')
    const field = report.bySection[0].fields[0]
    expect(field).toHaveProperty('reviewStatus')
    expect(field).toHaveProperty('reviewedBy')
    expect(field).toHaveProperty('reviewedAt')
    expect(report.reportReview.status).toBe('not_reviewed')
  })

  it('404s reviewing a field with no value yet, then reviews it once filled', async () => {
    await expect(reviewGuidelineField(RES, ID, 'arrive-v2', 'species', 'reviewed')).rejects.toBeTruthy()

    await setGuidelineField(RES, ID, 'arrive-v2', 'species', 'Mus musculus')
    const review = await reviewGuidelineField(RES, ID, 'arrive-v2', 'species', 'reviewed')
    expect(review.reviewStatus).toBe('reviewed')
    expect(review.reviewedBy).toBeTruthy()
    expect(review.reviewedAt).toBeTruthy()

    // The completion roll-up reflects the reviewed state for that field.
    const after = await getCompletion(RES, ID, 'arrive-v2')
    const field = after.bySection.flatMap((s) => s.fields).find((f) => f.fieldId === 'species')
    expect(field?.reviewStatus).toBe('reviewed')
  })

  it('records a report-level decision and exposes it via both POST and GET, newest first', async () => {
    const approved = await submitGuidelineReviewDecision(RES, ID, 'arrive-v2', 'approved', 'Looks complete.')
    expect(approved.status).toBe('approved')
    expect(approved.history).toHaveLength(1)
    expect(approved.history[0]).toMatchObject({ action: 'report_approved', note: 'Looks complete.' })

    const changesRequested = await submitGuidelineReviewDecision(RES, ID, 'arrive-v2', 'changes_requested')
    expect(changesRequested.status).toBe('changes_requested')

    const fetched = await getGuidelineReview(RES, ID, 'arrive-v2')
    expect(fetched.status).toBe('changes_requested')
    expect(fetched.history).toHaveLength(2)
    // Newest first.
    expect(fetched.history[0].action).toBe('report_changes_requested')
    expect(fetched.history[1].action).toBe('report_approved')
  })
})
