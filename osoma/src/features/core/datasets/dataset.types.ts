import type { Dataset } from '@/domain/resources.ts'
import type { PaginatedResponse } from '@/lib/types.ts'

export type DatasetsResponse = PaginatedResponse<Dataset>
