import type { User } from '@/domain/resources.ts'
import type { PaginatedResponse } from '@/lib/types.ts'

export type UsersResponse = PaginatedResponse<User>
