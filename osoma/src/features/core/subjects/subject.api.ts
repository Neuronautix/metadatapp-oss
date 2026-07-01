import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import type { Subject } from '@/domain/resources.ts'
import type { MetaSubject } from '@/metadatapp/types.ts'
import { mapMetaSubject, mapSubjectUpdateInput } from '@/metadatapp/adapters.ts'
import type { PaginatedResponse } from '@/lib/types.ts'

export type SubjectFilters = {
  page?: number
  pageSize?: number
  search?: string
  species?: string
  consent?: string
}

export type SubjectCurationSuggestion = {
  id: string
  recordId: string
  resourceType: string
  resourceId: string
  taskType: 'missing_required_field' | 'value_normalization' | 'ontology_candidate' | 'cross_field_consistency'
  status: 'pending_review' | 'accepted' | 'rejected' | 'failed'
  fieldName: string
  currentValue: unknown
  proposedValue: unknown
  ontologyId?: string | null
  confidence?: number | null
  reason: string
  warningCode?: string | null
  providerName: string
  providerMetadata?: Record<string, unknown> | null
  failureDetails?: Record<string, unknown> | null
  rawLlmResponse?: string | null
  createdAt: string
  updatedAt: string
  reviewedAt?: string | null
  reviewedBy?: string | null
}

type SubjectCurationResponse = {
  recordId: string
  suggestions: SubjectCurationSuggestion[]
}

export function getSubjects(filters: SubjectFilters) {
  if (filters.search?.trim()) {
    return getSubjectsByMergedSearch(filters)
  }

  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.pageSize) params.set('itemsPerPage', String(filters.pageSize))
  if (filters.species) params.set('species', filters.species)
  if (filters.consent) params.set('consent', filters.consent)

  const query = params.toString()
  return apiFetchHydraMapped<MetaSubject, Subject>(
    `/subjects${query ? `?${query}` : ''}`,
    mapMetaSubject
  )
}

async function getSubjectsByMergedSearch(filters: SubjectFilters): Promise<PaginatedResponse<Subject>> {
  const search = filters.search?.trim() ?? ''
  const page = filters.page ?? 1
  const pageSize = filters.pageSize ?? 10
  const baseParams = new URLSearchParams()
  baseParams.set('itemsPerPage', '100')
  if (filters.species) baseParams.set('species', filters.species)
  if (filters.consent) baseParams.set('consent', filters.consent)

  const requests = ['name', 'externalId', 'softmouseExternalId'].map((field) => {
    const params = new URLSearchParams(baseParams)
    params.set(field, search)
    return apiFetchHydraMapped<MetaSubject, Subject>(`/subjects?${params.toString()}`, mapMetaSubject)
  })

  const results = await Promise.all(requests)
  const byId = new Map<string, Subject>()
  results.flatMap((result) => result.data).forEach((subject) => {
    byId.set(subject.id, subject)
  })
  const data = Array.from(byId.values())
  const start = (page - 1) * pageSize

  return {
    data: data.slice(start, start + pageSize),
    page,
    pageSize,
    total: data.length,
  }
}

export function getSubject(subjectId: string) {
  return apiFetch<MetaSubject>(`/subjects/${subjectId}`).then(mapMetaSubject)
}

export function updateSubject(subjectId: string, payload: Partial<Subject>) {
  return apiFetch<MetaSubject>(`/subjects/${subjectId}`, {
    method: 'PATCH',
    body: JSON.stringify(mapSubjectUpdateInput(payload)),
  }).then(mapMetaSubject)
}

export function getSubjectCurationSuggestions(subjectId: string) {
  return apiFetch<SubjectCurationResponse>(`/curation/suggestions/${subjectId}`).then((response) => response.suggestions)
}

export function requestSubjectCurationSuggestions(subjectId: string) {
  return apiFetch<SubjectCurationResponse>(`/curation/suggest/${subjectId}`, {
    method: 'POST',
    body: JSON.stringify({}),
  }).then((response) => response.suggestions)
}

export function acceptSubjectCurationSuggestion(suggestionId: string) {
  return apiFetch<{ suggestion: SubjectCurationSuggestion }>(`/curation/suggestions/${suggestionId}/accept`, {
    method: 'POST',
    body: JSON.stringify({}),
  }).then((response) => response.suggestion)
}

export function rejectSubjectCurationSuggestion(suggestionId: string) {
  return apiFetch<{ suggestion: SubjectCurationSuggestion }>(`/curation/suggestions/${suggestionId}/reject`, {
    method: 'POST',
    body: JSON.stringify({}),
  }).then((response) => response.suggestion)
}
