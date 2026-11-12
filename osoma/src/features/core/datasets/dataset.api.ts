import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import type { Dataset } from '@/domain/resources.ts'
import type { MetaDataset } from '@/metadatapp/types.ts'
import { mapMetaDataset } from '@/metadatapp/adapters.ts'

export type DatasetFilters = {
  page?: number
  pageSize?: number
  search?: string
  status?: string
  format?: string
}

export function getDatasets(filters: DatasetFilters) {
  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.pageSize) params.set('pageSize', String(filters.pageSize))
  if (filters.search) params.set('search', filters.search)
  if (filters.status) params.set('status', filters.status)
  if (filters.format) params.set('format', filters.format)

  const query = params.toString()
  return apiFetchHydraMapped<MetaDataset, Dataset>(
    `/datasets${query ? `?${query}` : ''}`,
    mapMetaDataset
  )
}

export function getDataset(datasetId: string) {
  return apiFetch<MetaDataset>(`/datasets/${datasetId}`).then(mapMetaDataset)
}

export function updateDataset(datasetId: string, payload: Partial<Dataset>) {
  // NOTE: Assuming updating dataset sends plain object since `mapDatasetUpdateInput` doesn't exist yet, we could map it if needed.
  return apiFetch<MetaDataset>(`/datasets/${datasetId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  }).then(mapMetaDataset)
}
