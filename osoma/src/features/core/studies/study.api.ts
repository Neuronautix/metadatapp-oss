import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import type { Study } from '@/domain/resources.ts'
import type { MetaStudy } from '@/metadatapp/types.ts'
import { mapStudyUpdateInput, mapMetaStudy } from '@/metadatapp/adapters.ts'

import { type StudyFilters } from './study.types.ts'

export function getStudies(filters: StudyFilters) {
  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.pageSize) params.set('pageSize', String(filters.pageSize))
  if (filters.search) params.set('search', filters.search)
  if (filters.status) params.set('status', filters.status)
  if (filters.investigation) params.set('investigation', filters.investigation)
  if (filters.qcStatus) params.set('qcStatus', filters.qcStatus)

  const query = params.toString()
  return apiFetchHydraMapped<MetaStudy, Study>(
    `/experiments${query ? `?${query}` : ''}`,
    mapMetaStudy
  )
}

export function getStudy(studyId: string) {
  return apiFetch<MetaStudy>(`/experiments/${studyId}`).then(mapMetaStudy)
}

export function updateStudy(studyId: string, payload: Partial<Study>) {
  return apiFetch<MetaStudy>(`/experiments/${studyId}`, {
    method: 'PATCH',
    body: JSON.stringify(mapStudyUpdateInput(payload)),
  }).then(mapMetaStudy)
}

export function signStudy(studyId: string) {
  return apiFetch<Study>(`/studies/${studyId}/sign`, {
    method: 'POST',
  })
}

export function downloadStudyFairReportPdf(studyId: string) {
  return apiFetch<Blob>(`/studies/${studyId}/fair-report.pdf`, {
    headers: { Accept: 'application/pdf' },
    responseType: 'blob',
  })
}

export function exportStudyFair2JsonLd(studyId: string) {
  return apiFetch<Blob>(`/studies/${studyId}/fair2.json`, {
    responseType: 'blob',
  })
}

export type ConnectedResourceLink = {
  id: string
  provider: string
  externalId: string
  resourceType: string
  label: string | null
  url: string | null
  subjectExternalId: string | null
  syncedAt: string | null
}

type HydraConnectedResourceLinkCollection = {
  'hydra:member'?: Array<Record<string, unknown>>
}

function mapLink(raw: Record<string, unknown>): ConnectedResourceLink {
  return {
    id: String(raw.id ?? raw['@id'] ?? ''),
    provider: String(raw.provider ?? ''),
    externalId: String(raw.externalId ?? ''),
    resourceType: String(raw.resourceType ?? ''),
    label: typeof raw.label === 'string' ? raw.label : null,
    url: typeof raw.url === 'string' ? raw.url : null,
    subjectExternalId:
      typeof raw.subjectExternalId === 'string' ? raw.subjectExternalId : null,
    syncedAt: typeof raw.syncedAt === 'string' ? raw.syncedAt : null,
  }
}

export async function getConnectedResourceLinksForStudy(
  studyId: string
): Promise<ConnectedResourceLink[]> {
  const params = new URLSearchParams({ 'experiment.id': studyId })
  const response = await apiFetch<HydraConnectedResourceLinkCollection>(
    `/connected_resource_links?${params.toString()}`
  )
  return (response['hydra:member'] ?? []).map(mapLink)
}
