import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiFetch } from '@/lib/api.ts'

// ---------------------------------------------------------------------------
// Enums (mirror api/src/Enum/* string-backed values from the design spec)
// ---------------------------------------------------------------------------

export type ExternalTemplateSource = 'eln_community' | 'eln_file' | 'elabftw' | 'custom'
export type ExternalTemplateFormat = 'eln_ro_crate' | 'custom'
export type ExternalFormSource = 'nih_cde' | 'metadatapp' | 'custom'
export type CdeSource = 'nih_cde' | 'metadatapp' | 'hcmo' | 'mbo' | 'custom'
export type CdeStatus = 'draft' | 'recommended' | 'endorsed' | 'deprecated'
export type CanonicalFormStatus = 'draft' | 'active' | 'deprecated'
export type CrosswalkMappingType =
  | 'exact'
  | 'close'
  | 'partial'
  | 'composite'
  | 'broader'
  | 'narrower'
  | 'related'
  | 'no_match'
export type MappingStatus = 'suggested' | 'accepted' | 'rejected' | 'deprecated'

// The mapping types the field-level UI exposes per the spec.
export const FIELD_MAPPING_TYPES: CrosswalkMappingType[] = [
  'exact',
  'close',
  'broader',
  'narrower',
  'related',
  'no_match',
]

// ---------------------------------------------------------------------------
// Shared shapes (mirror entity JSON output)
// ---------------------------------------------------------------------------

export type OntologyTerm = {
  iri: string
  label?: string
  source?: string
}

export type PermissibleValue = {
  value: string
  code?: string
  ontologyTerm?: string
}

export type ExtractedTemplateField = {
  id: string
  jsonldId?: string | null
  jsonldPath?: string | null
  label: string
  propertyId?: string | null
  datatype?: string | null
  exampleValue?: string | null
  unit?: string | null
  ontologyTerms: OntologyTerm[]
  rawNode: Record<string, unknown>
  orderIndex: number
  mapped: boolean
}

export type ExternalTemplate = {
  id: string
  source: ExternalTemplateSource
  externalUrl?: string | null
  title: string
  description?: string | null
  version?: string | null
  format: ExternalTemplateFormat
  roCrateMetadata?: Record<string, unknown> | null
  rawPayload?: Record<string, unknown> | null
  fileHash?: string | null
  importedAt: string
  extractedFields: ExtractedTemplateField[]
}

export type ExternalForm = {
  id: string
  source: ExternalFormSource
  externalId?: string | null
  externalUrl?: string | null
  title: string
  description?: string | null
  version?: string | null
  rawPayload?: Record<string, unknown> | null
  importedAt: string
}

export type CommonDataElement = {
  id: string
  source: CdeSource
  externalId?: string | null
  externalUrl?: string | null
  version?: string | null
  label: string
  definition?: string | null
  questionText?: string | null
  datatype?: string | null
  unitConstraint?: string | null
  permissibleValues: PermissibleValue[]
  ontologyMappings: OntologyTerm[]
  provenance: Record<string, unknown>
  rawPayload?: Record<string, unknown> | null
  status: CdeStatus
  localKey?: string | null
}

export type CanonicalFormField = {
  id: string
  localKey: string
  label: string
  description?: string | null
  datatype?: string | null
  required: boolean
  unitConstraint?: string | null
  allowedValues: PermissibleValue[]
  ontologyMappings: OntologyTerm[]
  jsonldPath?: string | null
  sourceFieldReference?: string | null
  orderIndex: number
}

export type CanonicalFormSection = {
  id: string
  title: string
  description?: string | null
  orderIndex: number
}

export type CanonicalForm = {
  id: string
  title: string
  description?: string | null
  domain?: string | null
  version: string
  status: CanonicalFormStatus
  sourceLinks: Record<string, unknown>[]
  createdAt: string
  updatedAt: string
  sections: CanonicalFormSection[]
  fields: CanonicalFormField[]
}

export type ValueCrosswalk = {
  id: string
  sourceValue?: string | null
  targetValue?: string | null
  targetCode?: string | null
  ontologyTerm?: string | null
  mappingType: CrosswalkMappingType
  mappingStatus: MappingStatus
}

export type FieldCrosswalk = {
  id: string
  canonicalFormField?: CanonicalFormField | string | null
  cde?: CommonDataElement | string | null
  elnField?: ExtractedTemplateField | string | null
  elnJsonldPath?: string | null
  elnPropertyId?: string | null
  cdeFormFieldId?: string | null
  mappingType: CrosswalkMappingType
  mappingStatus: MappingStatus
  confidence?: number | null
  evidence?: string | null
  datatypeMapping: Record<string, unknown>
  unitMapping: Record<string, unknown>
  valueMapping: Record<string, unknown>
  curatorNotes?: string | null
  valueCrosswalks: ValueCrosswalk[]
}

export type TemplateFormCrosswalk = {
  id: string
  externalTemplate: ExternalTemplate | string
  externalForm?: ExternalForm | string | null
  canonicalForm: CanonicalForm | string
  mappingType: CrosswalkMappingType
  mappingStatus: MappingStatus
  confidence?: number | null
  evidence?: string | null
  reviewedBy?: string | null
  reviewedAt?: string | null
  version: string
  notes?: string | null
  createdAt: string
  fieldCrosswalks: FieldCrosswalk[]
}

// ---------------------------------------------------------------------------
// Mapping suggestions / CDE candidates / validation report
// ---------------------------------------------------------------------------

export type CdeCandidate = {
  source: CdeSource
  externalId?: string | null
  externalUrl?: string | null
  version?: string | null
  label: string
  definition?: string | null
  questionText?: string | null
  datatype?: string | null
  unitConstraint?: string | null
  permissibleValues: PermissibleValue[]
  ontologyMappings: OntologyTerm[]
  provenance: Record<string, unknown>
  status?: CdeStatus | null
  localKey?: string | null
}

export type FormCandidate = {
  source: ExternalFormSource
  externalId?: string | null
  externalUrl?: string | null
  title: string
  description?: string | null
  version?: string | null
  provenance: Record<string, unknown>
}

export type MappingSuggestion = {
  cde: CdeCandidate | CommonDataElement
  confidence: number
  reasons: string[]
  risks: string[]
  mappingType: CrosswalkMappingType
  // The extracted field this suggestion targets, when the engine returns it per-field.
  fieldId?: string
}

export type SuggestMappingsResponse = {
  suggestions: MappingSuggestion[]
}

export type ValidationIssueSeverity = 'error' | 'warning' | 'info'

export type ValidationIssueCode =
  | 'MISSING_DATATYPE'
  | 'MISSING_UNIT'
  | 'INVALID_UNIT'
  | 'VALUE_NOT_PERMISSIBLE'
  | 'AMBIGUOUS_MAPPING'
  | 'NO_MATCHING_CDE_FORM'
  | 'DEPRECATED_CDE_USED'
  | 'MAPPING_NOT_REVIEWED'
  | 'UNMAPPED_FIELD'

export type ValidationIssue = {
  code: ValidationIssueCode
  severity: ValidationIssueSeverity
  message: string
  fieldRef?: string
}

export type ValidationReport = {
  fieldsExtracted: number
  fieldsMappedToCdes: number
  unmappedFields: number
  numericFields: number
  numericFieldsWithUnits: number
  categoricalFields: number
  categoricalFieldsWithControlledValues: number
  fieldsRequiringReview: number
  issues: ValidationIssue[]
  semanticCompletenessScore: number
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

type HydraCollectionLike<T> = { 'hydra:member'?: T[]; member?: T[] }

function unwrapCollection<T>(result: HydraCollectionLike<T> | T[] | null | undefined): T[] {
  if (!result) return []
  if (Array.isArray(result)) return result
  return result['hydra:member'] ?? result.member ?? []
}

// ---------------------------------------------------------------------------
// Query keys
// ---------------------------------------------------------------------------

export const crosswalkKeys = {
  all: ['template-crosswalks'] as const,
  list: () => [...crosswalkKeys.all, 'list'] as const,
  detail: (id?: string | null) => [...crosswalkKeys.all, 'detail', id] as const,
  validation: (id?: string | null) => [...crosswalkKeys.all, 'validation', id] as const,
  cdeSearch: (query: string) => [...crosswalkKeys.all, 'cde-search', query] as const,
}

// ---------------------------------------------------------------------------
// Queries
// ---------------------------------------------------------------------------

export function useListCrosswalks() {
  return useQuery({
    queryKey: crosswalkKeys.list(),
    queryFn: async () => {
      const result = await apiFetch<HydraCollectionLike<TemplateFormCrosswalk> | TemplateFormCrosswalk[]>(
        '/template-crosswalks',
      )
      return unwrapCollection(result)
    },
  })
}

export function useGetCrosswalk(id?: string | null) {
  return useQuery({
    queryKey: crosswalkKeys.detail(id),
    queryFn: async () => apiFetch<TemplateFormCrosswalk>(`/template-crosswalks/${id}`),
    enabled: !!id,
  })
}

export function useSearchCdes(query: string, enabled = true) {
  return useQuery({
    queryKey: crosswalkKeys.cdeSearch(query),
    queryFn: async () => {
      const result = await apiFetch<HydraCollectionLike<CommonDataElement> | CommonDataElement[]>(
        `/cdes?label=${encodeURIComponent(query)}`,
      )
      return unwrapCollection(result)
    },
    enabled: enabled && query.trim().length > 0,
  })
}

// ---------------------------------------------------------------------------
// Import mutations
// ---------------------------------------------------------------------------

export function useImportEln() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData()
      formData.append('file', file)
      return apiFetch<ExternalTemplate>('/external-templates/import-eln', {
        method: 'POST',
        body: formData,
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: crosswalkKeys.all })
    },
  })
}

export function useImportElnUrl() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (url: string) => {
      return apiFetch<ExternalTemplate>('/external-templates/import-url', {
        method: 'POST',
        body: JSON.stringify({ url }),
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: crosswalkKeys.all })
    },
  })
}

export type ImportCdePayload =
  | { provider: string; externalId: string }
  | { payload: Record<string, unknown> | Record<string, unknown>[] }

export function useImportCde() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: ImportCdePayload) => {
      return apiFetch<CommonDataElement | CommonDataElement[]>('/cdes/import', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: crosswalkKeys.all })
    },
  })
}

export type ImportFormPayload =
  | { provider: string; externalId: string }
  | { payload: Record<string, unknown> }

export function useImportExternalForm() {
  return useMutation({
    mutationFn: async (payload: ImportFormPayload) => {
      return apiFetch<ExternalForm>('/external-forms/import', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
    },
  })
}

// ---------------------------------------------------------------------------
// Suggest / validate
// ---------------------------------------------------------------------------

export function useSuggestMappings() {
  return useMutation({
    mutationFn: async (crosswalkId: string) => {
      return apiFetch<SuggestMappingsResponse>(
        `/template-crosswalks/${crosswalkId}/suggest-mappings`,
        { method: 'POST' },
      )
    },
  })
}

/**
 * The backend serializes counts nested under `counts`
 * (`ValidationReport::toArray()`), while the UI reads them flat. Accept either
 * shape and return the flat {@link ValidationReport} the components expect.
 */
type ValidationReportResponse = Partial<ValidationReport> & {
  counts?: Partial<Omit<ValidationReport, 'issues' | 'semanticCompletenessScore'>>
}

function normalizeValidationReport(raw: ValidationReportResponse): ValidationReport {
  const counts = raw.counts ?? {}
  const num = (nested: number | undefined, flat: number | undefined) => nested ?? flat ?? 0
  return {
    fieldsExtracted: num(counts.fieldsExtracted, raw.fieldsExtracted),
    fieldsMappedToCdes: num(counts.fieldsMappedToCdes, raw.fieldsMappedToCdes),
    unmappedFields: num(counts.unmappedFields, raw.unmappedFields),
    numericFields: num(counts.numericFields, raw.numericFields),
    numericFieldsWithUnits: num(counts.numericFieldsWithUnits, raw.numericFieldsWithUnits),
    categoricalFields: num(counts.categoricalFields, raw.categoricalFields),
    categoricalFieldsWithControlledValues: num(
      counts.categoricalFieldsWithControlledValues,
      raw.categoricalFieldsWithControlledValues,
    ),
    fieldsRequiringReview: num(counts.fieldsRequiringReview, raw.fieldsRequiringReview),
    issues: raw.issues ?? [],
    semanticCompletenessScore: raw.semanticCompletenessScore ?? 0,
  }
}

export function useValidateCrosswalk() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (crosswalkId: string) => {
      const raw = await apiFetch<ValidationReportResponse>(
        `/template-crosswalks/${crosswalkId}/validate`,
        { method: 'POST' },
      )
      return normalizeValidationReport(raw)
    },
    onSuccess: (data, crosswalkId) => {
      queryClient.setQueryData(crosswalkKeys.validation(crosswalkId), data)
    },
  })
}

// ---------------------------------------------------------------------------
// Field crosswalk mutations
// ---------------------------------------------------------------------------

export type FieldCrosswalkPatch = Partial<{
  canonicalFormField: string | null
  cde: string | null
  elnField: string | null
  cdeFormFieldId: string | null
  mappingType: CrosswalkMappingType
  mappingStatus: MappingStatus
  confidence: number | null
  evidence: string | null
  datatypeMapping: Record<string, unknown>
  unitMapping: Record<string, unknown>
  valueMapping: Record<string, unknown>
  curatorNotes: string | null
}>

export function useUpdateFieldCrosswalk() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, patch }: { id: string; patch: FieldCrosswalkPatch }) => {
      return apiFetch<FieldCrosswalk>(`/field-crosswalks/${id}`, {
        method: 'PATCH',
        body: JSON.stringify(patch),
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: crosswalkKeys.all })
    },
  })
}

export function useAcceptFieldCrosswalk() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (fieldCrosswalkId: string) => {
      return apiFetch<FieldCrosswalk>(`/field-crosswalks/${fieldCrosswalkId}/accept`, {
        method: 'POST',
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: crosswalkKeys.all })
    },
  })
}

export function useRejectFieldCrosswalk() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (fieldCrosswalkId: string) => {
      return apiFetch<FieldCrosswalk>(`/field-crosswalks/${fieldCrosswalkId}/reject`, {
        method: 'POST',
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: crosswalkKeys.all })
    },
  })
}

// ---------------------------------------------------------------------------
// Create new Metadatapp CDE
// ---------------------------------------------------------------------------

export type CreateCdeInput = {
  label: string
  definition?: string
  datatype?: string
  unitConstraint?: string
  localKey?: string
  permissibleValues?: PermissibleValue[]
  ontologyMappings?: OntologyTerm[]
}

export function useCreateCde() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (input: CreateCdeInput) => {
      return apiFetch<CommonDataElement>('/cdes', {
        method: 'POST',
        body: JSON.stringify({ source: 'metadatapp', status: 'draft', ...input }),
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: crosswalkKeys.all })
    },
  })
}

// ---------------------------------------------------------------------------
// Exports
// ---------------------------------------------------------------------------

export function useExportRoCrate() {
  return useMutation({
    mutationFn: async (crosswalkId: string) => {
      return apiFetch<Record<string, unknown>>(
        `/template-crosswalks/${crosswalkId}/export-ro-crate`,
        { method: 'POST' },
      )
    },
  })
}

export function useExportJsonLd() {
  return useMutation({
    mutationFn: async (crosswalkId: string) => {
      return apiFetch<Record<string, unknown>>(
        `/template-crosswalks/${crosswalkId}/export-jsonld`,
        { method: 'POST' },
      )
    },
  })
}
