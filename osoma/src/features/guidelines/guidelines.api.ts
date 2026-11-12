import { apiFetch, apiFetchHydraCollection } from '@/lib/api.ts'
import type {
  BulkAiDraft,
  BulkAiDraftItem,
  BulkAiDraftItemWire,
  BulkAiDraftWire,
  CompletionField,
  CompletionFieldWire,
  CompletionReport,
  CompletionReportWire,
  CompletionSection,
  ConformanceEntry,
  ConformanceEntryWire,
  ConformanceReport,
  ConformanceReportWire,
  EvidenceItem,
  EvidenceItemWire,
  FieldEvidence,
  FieldEvidenceWire,
  GuidelineAiDraft,
  GuidelineAiDraftWire,
  GuidelineFieldRecord,
  GuidelineFieldRecordWire,
  GuidelineFieldReview,
  GuidelineFieldReviewWire,
  GuidelineReportReview,
  GuidelineReportReviewWire,
  GuidelineResourceType,
  GuidelineReviewEvent,
  GuidelineReviewEventWire,
  GuidelineTemplate,
  GuidelineTemplateId,
  GuidelineTemplateSummary,
  GuidelineTemplateSummaryWire,
  GuidelineTemplateWire,
  SatisfiedBy,
  SatisfiedByWire,
  Suggestion,
  SuggestionWire,
} from './guidelines.types.ts'

// ──────────────────────────────────────────────────── wire → domain mappers ──

function mapSatisfiedBy(wire: SatisfiedByWire): SatisfiedBy {
  if (!wire) return null
  const out: SatisfiedBy = {}
  if (wire.column != null) out.column = wire.column
  if (wire.metadata != null) out.metadata = wire.metadata
  if (wire.via_crosswalk === true) out.viaCrosswalk = true
  return out
}

function mapTemplateSummary(wire: GuidelineTemplateSummaryWire): GuidelineTemplateSummary {
  return {
    id: wire.templateId,
    name: wire.name,
    version: wire.version,
    description: wire.description,
    conformsTo: wire.conformsTo ?? [],
    requiredMetadataCount: wire.requiredMetadataCount ?? 0,
    columnCount: wire.requiredColumnCount ?? 0,
  }
}

function mapTemplate(wire: GuidelineTemplateWire): GuidelineTemplate {
  return {
    id: wire.templateId,
    name: wire.name,
    version: wire.version,
    description: wire.description,
    conformsTo: wire.conformsTo ?? [],
    requiredMetadata: (wire.requiredMetadata ?? []).map((m) => ({
      id: m.id,
      arriveSection: m.arrive_section,
      prepareSection: m.prepare_section,
      eqipdSection: m.eqipd_section,
      guidance: m.guidance,
      prompt: m.prompt,
      crosswalk: m.crosswalk ?? [],
      severity: m.severity,
    })),
    requiredColumns: (wire.requiredColumns ?? []).map((c) => ({
      id: c.id,
      namePatterns: c.name_patterns ?? [],
      role: c.role,
      arriveSection: c.arrive_section,
      unitRequired: c.unit_required,
      unitColumn: c.unit_column,
      severity: c.severity,
    })),
  }
}

function mapConformanceEntry(wire: ConformanceEntryWire): ConformanceEntry {
  return {
    standard: wire.standard,
    section: wire.section,
    arriveSection: wire.arrive_section,
    prepareSection: wire.prepare_section,
    eqipdSection: wire.eqipd_section,
    fieldId: wire.field_id,
    status: wire.status,
    satisfiedBy: mapSatisfiedBy(wire.satisfied_by),
    severity: wire.severity,
    isColumnField: wire.is_column_field,
  }
}

function mapConformance(wire: ConformanceReportWire): ConformanceReport {
  return {
    standard: wire.standard,
    version: wire.version,
    score: {
      dimension: wire.score.dimension,
      points: wire.score.points,
      maxPoints: wire.score.max_points,
      satisfiedPct: wire.score.satisfied_pct,
    },
    totals: { satisfied: wire.totals.satisfied, total: wire.totals.total },
    entries: (wire.entries ?? []).map(mapConformanceEntry),
  }
}

function mapCompletionField(wire: CompletionFieldWire): CompletionField {
  return {
    fieldId: wire.field_id,
    status: wire.status,
    severity: wire.severity,
    value: wire.value,
    hasValue: wire.has_value,
    viaCrosswalk: wire.satisfied_via_crosswalk,
    satisfyingSibling: wire.satisfying_sibling,
    // prompt/guidance are not on the conformance entry; carry them from the
    // resolved-template lookup if available, else leave null. The completion
    // wire `fields[]` records do not include prompt/guidance, so they are
    // resolved client-side from the template (see GuidelineDetailPage).
    prompt: null,
    guidance: null,
    reviewStatus: wire.review_status,
    reviewedBy: wire.reviewed_by,
    reviewedAt: wire.reviewed_at,
  }
}

/**
 * `bySection` is a name-keyed object whose `fields` are field-id strings; the
 * rich per-field records live in the top-level `fields[]`. Join them so the UI
 * gets a clean array of sections, each holding full field records.
 */
function mapCompletion(wire: CompletionReportWire): CompletionReport {
  const fieldById = new Map<string, CompletionField>(
    (wire.fields ?? []).map((f) => [f.field_id, mapCompletionField(f)]),
  )

  const bySection: CompletionSection[] = Object.entries(wire.bySection ?? {}).map(
    ([section, bucket]) => ({
      section,
      satisfied: bucket.satisfied,
      partial: bucket.partial,
      missing: bucket.missing,
      fields: bucket.fields
        .map((fieldId) => fieldById.get(fieldId))
        .filter((f): f is CompletionField => Boolean(f)),
    }),
  )

  return {
    totals: {
      satisfiedDirect: wire.totals.satisfied_direct,
      satisfiedViaCrosswalk: wire.totals.satisfied_via_crosswalk,
      partial: wire.totals.partial,
      missing: wire.totals.missing,
    },
    bySeverity: wire.bySeverity ?? {},
    bySection,
    reportReview: {
      status: wire.reportReview?.status ?? 'not_reviewed',
      reviewedBy: wire.reportReview?.reviewed_by ?? null,
      reviewedAt: wire.reportReview?.reviewed_at ?? null,
    },
  }
}

function mapFieldReview(wire: GuidelineFieldReviewWire): GuidelineFieldReview {
  return {
    fieldId: wire.field_id,
    reviewStatus: wire.review_status,
    reviewedBy: wire.reviewed_by,
    reviewedAt: wire.reviewed_at,
  }
}

function mapReviewEvent(wire: GuidelineReviewEventWire): GuidelineReviewEvent {
  return {
    action: wire.action,
    fieldId: wire.field_id,
    actor: wire.actor,
    occurredAt: wire.occurred_at,
    note: wire.note,
  }
}

function mapReportReview(wire: GuidelineReportReviewWire): GuidelineReportReview {
  return {
    status: wire.status,
    reviewedBy: wire.reviewed_by,
    reviewedAt: wire.reviewed_at,
    note: wire.note,
    history: (wire.history ?? []).map(mapReviewEvent),
  }
}

function mapFieldRecord(wire: GuidelineFieldRecordWire): GuidelineFieldRecord {
  const value = wire.value
  const hasValue = value != null && value !== ''
  return {
    fieldId: wire.field_id,
    value,
    source: wire.source,
    status: hasValue ? 'satisfied' : 'missing',
  }
}

function mapEvidenceItem(wire: EvidenceItemWire): EvidenceItem {
  return {
    source: wire.source,
    label: wire.label,
    value: wire.value ?? null,
    provenance: wire.provenance,
    fieldId: wire.field_id ?? null,
    confidence: wire.confidence ?? null,
    url: wire.url ?? null,
  }
}

function mapSuggestion(wire: SuggestionWire): Suggestion {
  return {
    value: wire.value,
    source: wire.source,
    provenance: wire.provenance,
    confidence: wire.confidence ?? null,
  }
}

function mapAiDraft(wire: GuidelineAiDraftWire): GuidelineAiDraft {
  return {
    fieldId: wire.field_id,
    value: wire.value,
    rationale: wire.rationale,
    available: wire.available,
    message: wire.message,
    usedEvidence: (wire.used_evidence ?? []).map(mapEvidenceItem),
    suggestions: (wire.suggestions ?? []).map(mapSuggestion),
  }
}

function mapFieldEvidence(wire: FieldEvidenceWire): FieldEvidence {
  return {
    fieldId: wire.field_id,
    computed: (wire.computed ?? []).map(mapEvidenceItem),
    suggestions: (wire.suggestions ?? []).map(mapSuggestion),
    evidence: (wire.evidence ?? []).map(mapEvidenceItem),
  }
}

function mapBulkAiDraftItem(wire: BulkAiDraftItemWire): BulkAiDraftItem {
  return {
    fieldId: wire.field_id,
    value: wire.value,
    rationale: wire.rationale,
    available: wire.available,
    usedEvidence: (wire.used_evidence ?? []).map(mapEvidenceItem),
    suggestions: (wire.suggestions ?? []).map(mapSuggestion),
  }
}

function mapBulkAiDraft(wire: BulkAiDraftWire): BulkAiDraft {
  return {
    templateId: wire.template_id,
    drafted: (wire.drafted ?? []).map(mapBulkAiDraftItem),
    skipped: wire.skipped ?? [],
    capped: wire.capped,
  }
}

// ───────────────────────────────────────────────────────────────── requests ──

/** List the available guideline templates (Hydra GetCollection). */
export async function getGuidelineTemplates(): Promise<GuidelineTemplateSummary[]> {
  const collection = await apiFetchHydraCollection<GuidelineTemplateSummaryWire>('/guidelines/templates')
  return collection.data.map(mapTemplateSummary)
}

/** Fetch a single resolved (merged) template. */
export async function getGuidelineTemplate(templateId: string): Promise<GuidelineTemplate> {
  const wire = await apiFetch<GuidelineTemplateWire>(`/guidelines/templates/${templateId}`)
  return mapTemplate(wire)
}

/** Live conformance dashboard for a resource + template. */
export async function getConformance(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
): Promise<ConformanceReport> {
  const wire = await apiFetch<ConformanceReportWire>(`/${resType}/${id}/guidelines/${templateId}/conformance`)
  return mapConformance(wire)
}

/** Enriched per-field completion roll-up for the fill workspace. */
export async function getCompletion(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
): Promise<CompletionReport> {
  const wire = await apiFetch<CompletionReportWire>(`/${resType}/${id}/guidelines/${templateId}/completion`)
  return mapCompletion(wire)
}

/**
 * Persist a single field value (manual fill). The controller json_decodes the raw
 * body, so a plain PUT with `{ value }` is correct (no merge-patch semantics).
 */
export async function setGuidelineField(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
  fieldId: string,
  value: string,
): Promise<GuidelineFieldRecord> {
  const wire = await apiFetch<GuidelineFieldRecordWire>(
    `/${resType}/${id}/guidelines/${templateId}/fields/${fieldId}`,
    {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ value }),
    },
  )
  return mapFieldRecord(wire)
}

/**
 * Ask the engine to draft a value for a field. Does NOT persist — the UI shows the
 * draft and the user accepts (which calls `setGuidelineField`). Degrades gracefully
 * when `available === false`.
 */
export async function aiDraftGuidelineField(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
  fieldId: string,
): Promise<GuidelineAiDraft> {
  const wire = await apiFetch<GuidelineAiDraftWire>(
    `/${resType}/${id}/guidelines/${templateId}/fields/${fieldId}/ai-draft`,
    { method: 'POST' },
  )
  return mapAiDraft(wire)
}

/**
 * Fetch the deterministic provenance for a field: `computed` facts, one-click
 * `suggestions`, and the broader `evidence` set. Returns even when no AI key is
 * configured, so the suggestions stay useful offline.
 */
export async function getFieldEvidence(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
  fieldId: string,
): Promise<FieldEvidence> {
  const wire = await apiFetch<FieldEvidenceWire>(
    `/${resType}/${id}/guidelines/${templateId}/fields/${fieldId}/evidence`,
  )
  return mapFieldEvidence(wire)
}

/**
 * Bulk-draft every missing field for a template. Persists NOTHING — the UI shows a
 * review queue and the user accepts each draft (which calls `setGuidelineField`).
 * When AI is unavailable `drafted[].available` is false but `suggestions` are still
 * populated so the queue remains useful offline.
 */
export async function aiDraftAll(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
): Promise<BulkAiDraft> {
  const wire = await apiFetch<BulkAiDraftWire>(
    `/${resType}/${id}/guidelines/${templateId}/ai-draft-all`,
    { method: 'POST' },
  )
  return mapBulkAiDraft(wire)
}

/** Download the human-readable markdown report (responseType 'text'). */
export async function downloadGuidelineReportMarkdown(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
): Promise<string> {
  return apiFetch<string>(`/${resType}/${id}/guidelines/${templateId}/report.md`, {
    responseType: 'text',
  })
}

/** Download the human-readable report bundle (responseType 'blob'). */
export async function downloadGuidelineReportZip(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
): Promise<Blob> {
  return apiFetch<Blob>(`/${resType}/${id}/guidelines/${templateId}/report.zip`, {
    responseType: 'blob',
  })
}

/**
 * Toggle QC review state on an already-filled field. Only valid once the field
 * has a value — the backend 404s otherwise.
 */
export async function reviewGuidelineField(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
  fieldId: string,
  status: GuidelineFieldReviewWire['review_status'],
): Promise<GuidelineFieldReview> {
  const wire = await apiFetch<GuidelineFieldReviewWire>(
    `/${resType}/${id}/guidelines/${templateId}/fields/${fieldId}/review`,
    {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status }),
    },
  )
  return mapFieldReview(wire)
}

/** Record a report-level QC decision (approve or send back for changes). */
export async function submitGuidelineReviewDecision(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
  decision: 'approved' | 'changes_requested',
  note?: string,
): Promise<GuidelineReportReview> {
  const wire = await apiFetch<GuidelineReportReviewWire>(`/${resType}/${id}/guidelines/${templateId}/review`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ decision, note }),
  })
  return mapReportReview(wire)
}

/** Current report-level sign-off plus the full audit history. */
export async function getGuidelineReview(
  resType: GuidelineResourceType,
  id: string,
  templateId: GuidelineTemplateId | string,
): Promise<GuidelineReportReview> {
  const wire = await apiFetch<GuidelineReportReviewWire>(`/${resType}/${id}/guidelines/${templateId}/review`)
  return mapReportReview(wire)
}
