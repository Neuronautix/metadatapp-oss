import type { Sample } from '@/domain/resources.ts'
import type { PaginatedResponse } from '@/lib/types.ts'

export type SamplesResponse = PaginatedResponse<Sample>

export type SampleFilters = {
    page?: number
    pageSize?: number
    search?: string
    status?: string
    type?: string
    storage?: string
    sortBy?: keyof Sample
    sortDirection?: 'asc' | 'desc'
}
