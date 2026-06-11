import { apiFetch } from '@/lib/api.ts'
import type { AtmpStudy } from '@/domain/atmp/atmp.types.ts'

export interface AtmpStudiesResponse {
  data: AtmpStudy[]
  total: number
}

export type AtmpStudyFilters = {
  search?: string
  atmpCategory?: string
  species?: string
}

export function getAtmpStudies(filters: AtmpStudyFilters) {
  const params = new URLSearchParams()
  if (filters.search) params.set('search', filters.search)
  if (filters.atmpCategory) params.set('atmpCategory', filters.atmpCategory)
  if (filters.species) params.set('species', filters.species)
  const query = params.toString()
  return apiFetch<AtmpStudiesResponse>(`/atmp/studies${query ? `?${query}` : ''}`)
}

export function getAtmpStudy(investigationID: string) {
  return apiFetch<AtmpStudy>(`/atmp/studies/${investigationID}`)
}

export function createAtmpStudy(payload: AtmpStudy) {
  return apiFetch<AtmpStudy>('/atmp/studies', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function updateAtmpStudy(investigationID: string, payload: Partial<AtmpStudy>) {
  return apiFetch<AtmpStudy>(`/atmp/studies/${investigationID}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}
