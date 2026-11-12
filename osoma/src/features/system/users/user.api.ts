import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import { getDataMode } from '@/lib/mode.ts'
import type { User } from '@/domain/resources.ts'
import type { MetaUser } from '@/metadatapp/types.ts'
import { mapMetaUser, mapUserUpdateInput } from '@/metadatapp/adapters.ts'

export type UsersFilters = {
  page?: number
  pageSize?: number
  search?: string
  status?: string
  accessLevel?: string
}

export function getUsers(filters: UsersFilters) {
  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.pageSize) params.set('pageSize', String(filters.pageSize))
  if (filters.search) params.set('search', filters.search)
  if (filters.status) params.set('status', filters.status)
  if (filters.accessLevel) params.set('accessLevel', filters.accessLevel)

  const query = params.toString()
  return apiFetchHydraMapped<MetaUser, User>(
    `/users${query ? `?${query}` : ''}`,
    mapMetaUser
  )
}

export function getUser(userId: string) {
  return apiFetch<MetaUser>(`/users/${userId}`).then(mapMetaUser)
}

const buildUserPayload = (payload: Partial<User>) => (
  getDataMode() === 'mock' ? payload : mapUserUpdateInput(payload)
)

export function updateUser(userId: string, payload: Partial<User>) {
  return apiFetch<MetaUser>(`/users/${userId}`, {
    method: 'PATCH',
    body: JSON.stringify(buildUserPayload(payload)),
  }).then(mapMetaUser)
}

export function createUser(payload: Partial<User>) {
  return apiFetch<MetaUser>('/users', {
    method: 'POST',
    body: JSON.stringify(buildUserPayload(payload)),
  }).then(mapMetaUser)
}
