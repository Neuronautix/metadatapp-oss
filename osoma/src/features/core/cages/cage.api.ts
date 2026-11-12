import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import type { CageHcmResponse, CageSubjectsResponse, CageSummary } from './cage.types.ts'
import type { MetaCage } from '@/metadatapp/types.ts'
import { mapMetaCageDetail, mapMetaCageSummary } from '@/metadatapp/adapters.ts'

export type CageFilters = {
  page?: number
  pageSize?: number
  search?: string
}

export const getCages = (filters: CageFilters) => {
  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.pageSize) params.set('pageSize', String(filters.pageSize))
  if (filters.search) params.set('search', filters.search)

  const query = params.toString()
  return apiFetchHydraMapped<MetaCage, CageSummary>(
    `/cages${query ? `?${query}` : ''}`,
    mapMetaCageSummary
  )
}

export const getCageDetail = (id: string) =>
  apiFetch<MetaCage>(`/cages/${id}`).then(mapMetaCageDetail)

export const getCageSubjects = (id: string) => apiFetch<CageSubjectsResponse>(`/cages/${id}/subjects`)

export const getCageHcm = (id: string) => apiFetch<CageHcmResponse>(`/cages/${id}/hcm`)
