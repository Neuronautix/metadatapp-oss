import { apiFetch } from '@/lib/api.ts'
import type { AuditAction, AuditLogResponse, AuditResourceType } from '@/domain/audit.ts'

export type AuditFilters = {
  page?: number
  pageSize?: number
  actor?: string
  action?: AuditAction | ''
  resourceType?: AuditResourceType | ''
  resourceId?: string
  startDate?: string
  endDate?: string
}

export function getAuditEntries(filters: AuditFilters) {
  const params = new URLSearchParams()
  if (filters.page) params.set('page', String(filters.page))
  if (filters.pageSize) params.set('pageSize', String(filters.pageSize))
  if (filters.actor) params.set('actor', filters.actor)
  if (filters.action) params.set('action', filters.action)
  if (filters.resourceType) params.set('resourceType', filters.resourceType)
  if (filters.resourceId) params.set('resourceId', filters.resourceId)
  if (filters.startDate) params.set('startDate', filters.startDate)
  if (filters.endDate) params.set('endDate', filters.endDate)

  const query = params.toString()
  return apiFetch<AuditLogResponse>(`/audit${query ? `?${query}` : ''}`)
}
