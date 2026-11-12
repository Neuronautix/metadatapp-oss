import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import type { Assay } from '@/domain/resources.ts'
import type { MetaAssay } from '@/metadatapp/types.ts'
import { mapMetaAssay } from '@/metadatapp/adapters.ts'

export type AssayFilters = {
  page?: number
  pageSize?: number
  search?: string
  reviewStatus?: string
}

export function getAssays(filters: AssayFilters) {
  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.pageSize) params.set('itemsPerPage', String(filters.pageSize))
  if (filters.search) params.set('name', filters.search)
  if (filters.reviewStatus) params.set('reviewStatus', filters.reviewStatus)

  const query = params.toString()
  return apiFetchHydraMapped<MetaAssay, Assay>(
    `/procedures${query ? `?${query}` : ''}`,
    mapMetaAssay
  )
}

export function getAssay(assayId: string) {
  return apiFetch<MetaAssay>(`/procedures/${assayId}`).then(mapMetaAssay)
}

export function updateAssay(assayId: string, payload: Partial<Assay>) {
  return apiFetch<MetaAssay>(`/procedures/${assayId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  }).then(mapMetaAssay)
}
