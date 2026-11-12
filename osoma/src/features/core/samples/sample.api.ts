import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import type { Sample } from '@/domain/resources.ts'
import type { MetaWeightMeasurement } from '@/metadatapp/types.ts'
import { mapMetaSample } from '@/metadatapp/adapters.ts'
import type { SampleFilters } from './sample.types.ts'

export function getSamples(filters: SampleFilters) {
  const params = new URLSearchParams()
  params.set('page', '1')
  params.set('itemsPerPage', '500')

  const query = params.toString()
  return apiFetchHydraMapped<MetaWeightMeasurement, Sample>(
    `/weight_measurements${query ? `?${query}` : ''}`,
    mapMetaSample
  ).then((response) => {
    const search = filters.search?.trim().toLowerCase()
    const filtered = response.data.filter((sample) => {
      if (search) {
        const haystack = [
          sample.id,
          sample.label,
          sample.subjectId,
          sample.subjectLabel,
          sample.investigationId,
          sample.investigationName,
          sample.storageLocation,
          ...sample.identifiers.map((identifier) => identifier.value),
        ].join(' ').toLowerCase()
        if (!haystack.includes(search)) return false
      }
      if (filters.status && sample.status !== filters.status) return false
      if (filters.type && sample.type !== filters.type) return false
      if (filters.storage && sample.storageLocation !== filters.storage) return false
      return true
    })

    const sortBy = filters.sortBy ?? 'collectedAt'
    const sortDirection = filters.sortDirection ?? 'desc'
    const sorted = [...filtered].sort((left, right) => {
      const leftValue = left[sortBy]
      const rightValue = right[sortBy]
      const result = String(leftValue ?? '').localeCompare(String(rightValue ?? ''), undefined, {
        numeric: true,
        sensitivity: 'base',
      })
      return sortDirection === 'asc' ? result : -result
    })

    const page = filters.page ?? 1
    const pageSize = filters.pageSize ?? 10
    const start = (page - 1) * pageSize
    return {
      ...response,
      data: sorted.slice(start, start + pageSize),
      page,
      pageSize,
      total: sorted.length,
    }
  })
}

export function getSample(sampleId: string) {
  return apiFetch<MetaWeightMeasurement>(`/weight_measurements/${sampleId}`).then(mapMetaSample)
}

export function updateSample(sampleId: string, payload: Partial<Sample>) {
  return apiFetch<Sample>(`/weight_measurements/${sampleId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}
