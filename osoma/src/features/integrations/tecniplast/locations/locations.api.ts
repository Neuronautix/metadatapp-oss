import type { Cage, Rack, Room } from '../tecniplast.types.ts'
import { buildQuery, tpFetch } from '../tp.api.ts'

export const getRooms = (facilityId: string) =>
  tpFetch<Room[]>(`/rooms${buildQuery({ facilityId })}`)

export const getRoom = (id: string) => tpFetch<Room>(`/rooms/${id}`)

export const getRacks = (roomId: string) =>
  tpFetch<Rack[]>(`/racks${buildQuery({ roomId })}`)

export const getRack = (id: string) => tpFetch<Rack>(`/racks/${id}`)

export const getCages = (rackId: string) =>
  tpFetch<Cage[]>(`/cages${buildQuery({ rackId })}`)

export const getCage = (id: string) => tpFetch<Cage>(`/cages/${id}`)
