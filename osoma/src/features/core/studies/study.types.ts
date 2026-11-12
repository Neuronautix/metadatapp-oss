import type { Study } from '@/domain/resources.ts'
import type { PaginatedResponse } from '@/lib/types.ts'

export type StudiesResponse = PaginatedResponse<Study>

export type StudyFilters = {
    page?: number
    pageSize?: number
    search?: string
    status?: string
    investigation?: string
    qcStatus?: string
}
