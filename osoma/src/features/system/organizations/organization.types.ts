import type { Organization } from '@/domain/resources.ts'
import type { PaginatedResponse } from '@/lib/types.ts'

export type OrganizationsResponse = PaginatedResponse<Organization>
