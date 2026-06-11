import { apiFetch } from '@/lib/api.ts'
import type { OrganizationsResponse } from './organization.types.ts'
import type { Organization } from '@/domain/resources.ts'

export type OrganizationFilters = {
  page?: number
  pageSize?: number
  search?: string
  type?: string
}

export function getOrganizations(filters: OrganizationFilters) {
  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.pageSize) params.set('pageSize', String(filters.pageSize))
  if (filters.search) params.set('search', filters.search)
  if (filters.type) params.set('type', filters.type)

  const query = params.toString()
  return apiFetch<OrganizationsResponse>(`/organizations${query ? `?${query}` : ''}`)
}

export function getOrganization(organizationId: string) {
  return apiFetch<Organization>(`/organizations/${organizationId}`)
}

export function updateOrganization(organizationId: string, payload: Partial<Organization>) {
  return apiFetch<Organization>(`/organizations/${organizationId}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
}
