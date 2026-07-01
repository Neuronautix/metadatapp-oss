/**
 * Types for the International Guidelines Reporting feature.
 *
 * The backend wire contract is MIXED-case (there is no global serializer name
 * converter): API Platform Resource TOP-LEVEL properties serialize as camelCase,
 * but nested arrays inside those resources and the plain Controller JsonResponses
 * are snake_case. To keep components clean we keep two layers:
 *
 *  - `*Wire` types mirror the REAL backend response EXACTLY (mixed case).
 *  - The unsuffixed (domain) types are the clean camelCase shapes the React
 *    components consume.
 *
 * `guidelines.api.ts` adapts wire → domain (the `mapMetaX` adapter convention).
 */

/** The kind of host resource a guideline template is filled against. */
export type GuidelineResourceType = 'studies' | 'investigations'

/** Stable template slugs supported by the pipeline. */
export type GuidelineTemplateId =
  | 'arrive-v2'
  | 'prepare-v1'
  | 'eqipd-v1'
  | 'mnms-v1'
  | 'arrive-prepare-crosswalk-v1'

export type GuidelineSeverity = 'high' | 'medium' | 'low'

/** Conformance status vocabulary — exactly `satisfied | partial | missing`. */
export type ConformanceStatus = 'satisfied' | 'partial' | 'missing'

// ───────────────────────────────────────────────────────────── Wire types ──

/** Item in the Hydra collection at `GET /guidelines/templates` (camelCase top-level). */
export type GuidelineTemplateSummaryWire = {
  templateId: string
  name: string
  version: string
  description: string
  identifier: string | null
  conformsTo: string[]
  requiredColumnCount: number
  optionalColumnCount: number
  requiredMetadataCount: number
  fairBonus: { dimension: string; max_points: number } | null
}

/** Nested column item (snake_case) on the resolved template. */
export type RequiredColumnWire = {
  id: string
  name_patterns: string[]
  semantic_type: string | null
  role: string | null
  arrive_section: string | null
  unit_required: boolean
  unit_column: string | null
  required: boolean
  severity: GuidelineSeverity
}

/** Nested metadata item (snake_case) on the resolved template. */
export type RequiredMetadataWire = {
  id: string
  arrive_section: string | null
  prepare_section: string | null
  eqipd_section: string | null
  guidance: string | null
  prompt: string | null
  crosswalk: string[]
  severity: GuidelineSeverity
}

/** Resolved template item (camelCase top-level, snake_case nested arrays). */
export type GuidelineTemplateWire = GuidelineTemplateSummaryWire & {
  requiredColumns: RequiredColumnWire[]
  optionalColumns: RequiredColumnWire[]
  requiredMetadata: RequiredMetadataWire[]
}

/** What satisfied a conformance entry (snake_case), or `null`. */
export type SatisfiedByWire =
  | { column?: string; metadata?: string; via_crosswalk?: true }
  | null

/** A per-field conformance entry on the wire (snake_case). */
export type ConformanceEntryWire = {
  standard: string
  section: string | null
  arrive_section: string | null
  prepare_section: string | null
  eqipd_section: string | null
  field_id: string
  status: ConformanceStatus
  satisfied_by: SatisfiedByWire
  severity: GuidelineSeverity
  is_column_field: boolean
}

/** Response of `GET …/conformance` (camelCase top-level, snake_case score/entries). */
export type ConformanceReportWire = {
  id: string
  resourceType: string
  templateId: string
  standard: string
  version: string
  score: { dimension: string; points: number; max_points: number; satisfied_pct: number }
  totals: { satisfied: number; total: number }
  entries: ConformanceEntryWire[]
}

/** Field-level QC review status — exactly `draft | reviewed`. */
export type GuidelineReviewStatus = 'draft' | 'reviewed'

/** Report-level QC sign-off status. */
export type GuidelineReportReviewStatus = 'not_reviewed' | 'approved' | 'changes_requested'

/** A rich per-field record in the completion `fields[]` list (snake_case). */
export type CompletionFieldWire = ConformanceEntryWire & {
  value: string | null
  has_value: boolean
  satisfied_via_crosswalk: boolean
  satisfying_sibling: string | null
  review_status: GuidelineReviewStatus
  reviewed_by: string | null
  reviewed_at: string | null
}

/** A section bucket on the wire — `fields` are field-id strings. */
export type CompletionSectionWire = {
  satisfied: number
  partial: number
  missing: number
  fields: string[]
}

/** Response of `GET …/completion` (camelCase top-level; name-keyed objects). */
export type CompletionReportWire = {
  id: string
  resourceType: string
  templateId: string
  standard: string
  totals: {
    satisfied_direct: number
    satisfied_via_crosswalk: number
    partial: number
    missing: number
  }
  /** Object keyed by severity name. */
  bySeverity: Record<string, { satisfied: number; partial: number; missing: number }>
  /** Object keyed by human SECTION NAME. */
  bySection: Record<string, CompletionSectionWire>
  fields: CompletionFieldWire[]
  /** Nested array on a camelCase top-level prop — inner keys stay snake_case, same as `bySection`/`fields`. */
  reportReview: { status: GuidelineReportReviewStatus; reviewed_by: string | null; reviewed_at: string | null }
}

/** Response of the field-review PUT-equivalent POST (snake_case, flat). */
export type GuidelineFieldReviewWire = {
  resource_type: string
  resource_id: string
  template_id: string
  field_id: string
  review_status: GuidelineReviewStatus
  reviewed_by: string | null
  reviewed_at: string | null
}

/** One audit-log entry in the report-review history (snake_case). */
export type GuidelineReviewEventWire = {
  action: string
  field_id: string | null
  actor: string
  occurred_at: string
  note: string | null
}

/** Response of the report-review GET/POST endpoints (snake_case, flat). */
export type GuidelineReportReviewWire = {
  resource_type: string
  resource_id: string
  template_id: string
  status: GuidelineReportReviewStatus
  reviewed_by: string | null
  reviewed_at: string | null
  note: string | null
  history: GuidelineReviewEventWire[]
}

/** Response of the field PUT (snake_case). */
export type GuidelineFieldRecordWire = {
  resource_type: string
  resource_id: string
  template_id: string
  field_id: string
  value: unknown
  source: string
  updated_at: string
}

/**
 * Where a deterministic fact / suggestion / evidence item came from. Mirrors the
 * backend `source` vocabulary exactly.
 */
export type EvidenceSource =
  | 'computed'
  | 'elabftw'
  | 'connected_link'
  | 'history'
  | 'fair'
  | 'entity'
  | 'reference'

/** An evidence item the model cited (snake_case on the wire). */
export type EvidenceItemWire = {
  source: EvidenceSource
  label: string
  value?: string | null
  provenance: string
  field_id?: string | null
  confidence?: number | null
  /** Official URL for a `reference` standard text (GuidelinesHub), else null. */
  url?: string | null
}

/** A deterministic candidate value (snake_case on the wire). */
export type SuggestionWire = {
  value: string
  source: EvidenceSource
  provenance: string
  confidence?: number | null
}

/** Response of the ai-draft endpoint (snake_case, flat). */
export type GuidelineAiDraftWire = {
  field_id: string
  value: string
  rationale: string
  available: boolean
  provider: string
  model: string
  message: string
  persisted: false
  used_evidence?: EvidenceItemWire[]
  suggestions?: SuggestionWire[]
}

/** Response of `GET …/fields/:fieldId/evidence` (snake_case, flat). */
export type FieldEvidenceWire = {
  field_id: string
  computed: EvidenceItemWire[]
  suggestions: SuggestionWire[]
  evidence: EvidenceItemWire[]
}

/** A single drafted field in the bulk `ai-draft-all` response (snake_case). */
export type BulkAiDraftItemWire = {
  field_id: string
  value: string
  rationale: string
  available: boolean
  used_evidence?: EvidenceItemWire[]
  suggestions?: SuggestionWire[]
}

/** Response of `POST …/ai-draft-all` (snake_case). */
export type BulkAiDraftWire = {
  template_id: string
  drafted: BulkAiDraftItemWire[]
  skipped: string[]
  capped: boolean
}

// ─────────────────────────────────────────────────────────── Domain types ──

/** Summary of a template for the picker (clean camelCase). */
export type GuidelineTemplateSummary = {
  id: string
  name: string
  version: string
  description: string
  conformsTo: string[]
  requiredMetadataCount: number
  columnCount: number
}

export type RequiredMetadata = {
  id: string
  arriveSection: string | null
  prepareSection: string | null
  eqipdSection: string | null
  guidance: string | null
  prompt: string | null
  crosswalk: string[]
  severity: GuidelineSeverity
}

export type RequiredColumn = {
  id: string
  namePatterns: string[]
  role: string | null
  arriveSection: string | null
  unitRequired: boolean
  unitColumn: string | null
  severity: GuidelineSeverity
}

export type GuidelineTemplate = {
  id: string
  name: string
  version: string
  description: string
  conformsTo: string[]
  requiredMetadata: RequiredMetadata[]
  requiredColumns: RequiredColumn[]
}

/** What satisfied a conformance entry (clean camelCase), or `null`. */
export type SatisfiedBy = { column?: string; metadata?: string; viaCrosswalk?: true } | null

export type ConformanceEntry = {
  standard: string
  section: string | null
  arriveSection: string | null
  prepareSection: string | null
  eqipdSection: string | null
  fieldId: string
  status: ConformanceStatus
  satisfiedBy: SatisfiedBy
  severity: GuidelineSeverity
  isColumnField: boolean
}

export type ConformanceScore = {
  dimension: string
  points: number
  maxPoints: number
  satisfiedPct: number
}

/** Top-level conformance totals (the wire only exposes satisfied/total here). */
export type ConformanceTotals = {
  satisfied: number
  total: number
}

export type ConformanceReport = {
  standard: string
  version: string
  score: ConformanceScore
  totals: ConformanceTotals
  entries: ConformanceEntry[]
}

/** A clean per-field record consumed by the field row. */
export type CompletionField = {
  fieldId: string
  status: ConformanceStatus
  severity: GuidelineSeverity
  value: string | null
  hasValue: boolean
  viaCrosswalk: boolean
  satisfyingSibling: string | null
  prompt: string | null
  guidance: string | null
  reviewStatus: GuidelineReviewStatus
  reviewedBy: string | null
  reviewedAt: string | null
}

/** A section grouping (keyed by human section name) with full field records. */
export type CompletionSection = {
  section: string
  satisfied: number
  partial: number
  missing: number
  fields: CompletionField[]
}

export type CompletionTotals = {
  satisfiedDirect: number
  satisfiedViaCrosswalk: number
  partial: number
  missing: number
}

export type CompletionSeverityRoll = {
  satisfied: number
  partial: number
  missing: number
}

export type CompletionReport = {
  totals: CompletionTotals
  bySeverity: Record<string, CompletionSeverityRoll>
  bySection: CompletionSection[]
  reportReview: { status: GuidelineReportReviewStatus; reviewedBy: string | null; reviewedAt: string | null }
}

export type GuidelineFieldRecord = {
  fieldId: string
  value: unknown
  source: string
  status: ConformanceStatus
}

/** A clean evidence item the model cited (camelCase domain shape). */
export type EvidenceItem = {
  source: EvidenceSource
  label: string
  value: string | null
  provenance: string
  fieldId: string | null
  confidence: number | null
  /** Official URL for a `reference` standard text (GuidelinesHub), else null. */
  url: string | null
}

/** A clean deterministic candidate value (camelCase domain shape). */
export type Suggestion = {
  value: string
  source: EvidenceSource
  provenance: string
  confidence: number | null
}

/** Clean shape of the evidence endpoint. */
export type FieldEvidence = {
  fieldId: string
  computed: EvidenceItem[]
  suggestions: Suggestion[]
  evidence: EvidenceItem[]
}

export type GuidelineAiDraft = {
  fieldId: string
  value: string
  rationale: string
  available: boolean
  message: string
  usedEvidence: EvidenceItem[]
  suggestions: Suggestion[]
}

/** A single drafted field in the bulk review queue (camelCase domain shape). */
export type BulkAiDraftItem = {
  fieldId: string
  value: string
  rationale: string
  available: boolean
  usedEvidence: EvidenceItem[]
  suggestions: Suggestion[]
}

/** Clean shape of the bulk `ai-draft-all` response. */
export type BulkAiDraft = {
  templateId: string
  drafted: BulkAiDraftItem[]
  skipped: string[]
  capped: boolean
}

/** Result of a single-field review toggle (camelCase domain shape). */
export type GuidelineFieldReview = {
  fieldId: string
  reviewStatus: GuidelineReviewStatus
  reviewedBy: string | null
  reviewedAt: string | null
}

/** One audit-log entry in the report-review history (camelCase domain shape). */
export type GuidelineReviewEvent = {
  action: string
  fieldId: string | null
  actor: string
  occurredAt: string
  note: string | null
}

/** Clean shape of the report-review GET/POST endpoints. */
export type GuidelineReportReview = {
  status: GuidelineReportReviewStatus
  reviewedBy: string | null
  reviewedAt: string | null
  note: string | null
  history: GuidelineReviewEvent[]
}

/**
 * Result of an AI draft attempt for one field: the drafted value/rationale when
 * available, or the deterministic suggestions/evidence to fall back on when not.
 */
export type AiDraftResult = {
  value: string
  rationale: string
  available: boolean
  usedEvidence: EvidenceItem[]
  suggestions: Suggestion[]
} | null
