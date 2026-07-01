import type { Sensor } from '../tecniplast.types.ts'
import { buildQuery, tpFetch } from '../tp.api.ts'

export const getSensors = (params?: { scopeType?: string; scopeId?: string; facilityId?: string }) =>
  tpFetch<Sensor[]>(`/sensors${buildQuery(params ?? {})}`)

export const getSensor = (id: string) => tpFetch<Sensor>(`/sensors/${id}`)
