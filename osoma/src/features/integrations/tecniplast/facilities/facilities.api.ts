import type { Facility } from '../tecniplast.types.ts'
import { buildQuery, tpFetch } from '../tp.api.ts'

export const getFacilities = () => tpFetch<Facility[]>('/facilities')

export const getFacility = (id: string) => tpFetch<Facility>(`/facilities/${id}`)

export type FacilityFilter = {
  status?: string
}

export const getFacilitiesFiltered = (filters: FacilityFilter) =>
  tpFetch<Facility[]>(`/facilities${buildQuery(filters)}`)
