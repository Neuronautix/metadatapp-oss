import { apiFetch } from '@/lib/api.ts'
import type { SearchResponse } from './search.types.ts'

export const getSearchResults = (query: string) => {
  const params = new URLSearchParams()
  params.set('query', query)
  return apiFetch<SearchResponse>(`/search?${params.toString()}`)
}
