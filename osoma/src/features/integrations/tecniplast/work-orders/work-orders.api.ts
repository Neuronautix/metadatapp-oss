import type { WorkOrder } from '../tecniplast.types.ts'
import { buildQuery, tpFetch } from '../tp.api.ts'

export const getWorkOrders = (facilityId?: string) =>
  tpFetch<WorkOrder[]>(`/work-orders${buildQuery({ facilityId })}`)
