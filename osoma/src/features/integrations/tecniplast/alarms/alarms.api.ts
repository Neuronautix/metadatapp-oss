import type { Alarm } from '../tecniplast.types.ts'
import { buildQuery, tpFetch } from '../tp.api.ts'

export type AlarmFilters = {
  facilityId?: string
  status?: string
  severity?: string
  scopeType?: string
  scopeId?: string
  sensorType?: string
  q?: string
  page?: number
  pageSize?: number
  sortBy?: string
  sortDir?: 'asc' | 'desc'
  at?: string
  scenarioId?: string
}

export type AlarmListResponse = {
  data: Alarm[]
  page: number
  pageSize: number
  total: number
}

export const getAlarms = (filters: AlarmFilters) =>
  tpFetch<AlarmListResponse>(`/alarms${buildQuery(filters)}`)

export const getAlarm = (id: string) => tpFetch<Alarm>(`/alarms/${id}`)

export const acknowledgeAlarm = (id: string) =>
  tpFetch<Alarm>(`/alarms/${id}/ack`)

export const assignAlarm = (id: string, assignee: string) =>
  tpFetch<Alarm>(`/alarms/${id}/assign${buildQuery({ assignee })}`)

export const resolveAlarm = (id: string) =>
  tpFetch<Alarm>(`/alarms/${id}/resolve`)
