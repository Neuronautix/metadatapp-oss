export type AuditResourceType =
  | 'investigation'
  | 'study'
  | 'sample'
  | 'subject'
  | 'assay'
  | 'dataset'
  | 'user'
  | 'organization'
  | 'calendar'
  | 'facility'
  | 'room'
  | 'rack'
  | 'cage'
  | 'alarm'
  | 'sensor'
  | 'workorder'

export type AuditAction =
  | 'created'
  | 'updated'
  | 'linked'
  | 'unlinked'
  | 'status.changed'
  | 'exported'
  | 'scheduled'
  | 'access.changed'

export type AuditDiff = {
  field: string
  before: string | number | null
  after: string | number | null
}

export type AuditLogEntry = {
  id: string
  at: string
  actor: string
  action: AuditAction
  resourceType: AuditResourceType
  resourceId: string
  summary: string
  diff?: AuditDiff[]
  correlationId: string
}

export type AuditLogResponse = {
  data: AuditLogEntry[]
  page: number
  pageSize: number
  total: number
}
