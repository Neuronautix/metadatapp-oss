import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { getFacilities } from '../facilities/facilities.api.ts'
import { getRooms, getRacks, getCages } from '../locations/locations.api.ts'
import { ConditionsPanel } from './ConditionsPanel.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'

const scopeOptions = [
  { value: 'room', label: 'Room' },
  { value: 'rack', label: 'Rack' },
  { value: 'cage', label: 'Cage' },
] as const

export function ConditionsPage() {
  const [facilityId, setFacilityId] = useState('')
  const [scopeType, setScopeType] = useState<(typeof scopeOptions)[number]['value']>('room')
  const [roomId, setRoomId] = useState('')
  const [rackId, setRackId] = useState('')
  const [cageId, setCageId] = useState('')

  const facilitiesQuery = useQuery({
    queryKey: ['tp-facilities'],
    queryFn: () => getFacilities(),
  })

  const activeFacilityId = facilityId || facilitiesQuery.data?.[0]?.id

  const roomsQuery = useQuery({
    queryKey: ['tp-rooms', activeFacilityId],
    queryFn: () => getRooms(activeFacilityId ?? ''),
    enabled: Boolean(activeFacilityId),
  })

  const rooms = roomsQuery.data ?? []
  const activeRoomId = rooms.some((room) => room.id === roomId) ? roomId : (rooms[0]?.id ?? '')

  const racksQuery = useQuery({
    queryKey: ['tp-racks', activeRoomId],
    queryFn: () => getRacks(activeRoomId ?? ''),
    enabled: Boolean(activeRoomId),
  })

  const racks = racksQuery.data ?? []
  const activeRackId = racks.some((rack) => rack.id === rackId) ? rackId : (racks[0]?.id ?? '')

  const cagesQuery = useQuery({
    queryKey: ['tp-cages', activeRackId],
    queryFn: () => getCages(activeRackId ?? ''),
    enabled: Boolean(activeRackId),
  })

  const cages = cagesQuery.data ?? []
  const activeCageId = cages.some((cage) => cage.id === cageId) ? cageId : (cages[0]?.id ?? '')

  const activeScopeId = useMemo(() => {
    if (scopeType === 'room') return activeRoomId
    if (scopeType === 'rack') return activeRackId
    return activeCageId
  }, [scopeType, activeCageId, activeRoomId, activeRackId])

  if (facilitiesQuery.isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (facilitiesQuery.isError || !facilitiesQuery.data?.length) {
    return (
      <EmptyState
        title="Conditions unavailable"
        description={facilitiesQuery.error?.message ?? 'Facilities could not be loaded.'}
        actionLabel="Retry"
        onAction={() => facilitiesQuery.refetch()}
      />
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Conditions"
        subtitle="Live environmental monitoring across rooms, racks, and cages."
        actions={
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={activeFacilityId ?? ''}
            onChange={(event) => {
              setFacilityId(event.target.value)
              setRoomId('')
              setRackId('')
              setCageId('')
            }}
          >
            {facilitiesQuery.data.map((facility) => (
              <option key={facility.id} value={facility.id}>
                {facility.name}
              </option>
            ))}
          </select>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>Scope selector</CardTitle>
          <CardDescription>Target the monitoring scope.</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-[160px_1fr_1fr_1fr]">
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={scopeType}
            onChange={(event) => setScopeType(event.target.value as (typeof scopeOptions)[number]['value'])}
          >
            {scopeOptions.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={activeRoomId}
            onChange={(event) => {
              setRoomId(event.target.value)
              setRackId('')
              setCageId('')
            }}
            disabled={!rooms.length}
          >
            {rooms.map((room) => (
              <option key={room.id} value={room.id}>
                {room.name}
              </option>
            ))}
          </select>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={activeRackId}
            onChange={(event) => {
              setRackId(event.target.value)
              setCageId('')
            }}
            disabled={scopeType === 'room' || !racks.length}
          >
            {racks.map((rack) => (
              <option key={rack.id} value={rack.id}>
                {rack.label}
              </option>
            ))}
          </select>
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={activeCageId}
            onChange={(event) => setCageId(event.target.value)}
            disabled={scopeType !== 'cage' || !cages.length}
          >
            {cages.map((cage) => (
              <option key={cage.id} value={cage.id}>
                {cage.label}
              </option>
            ))}
          </select>
        </CardContent>
      </Card>

      {activeScopeId ? (
        <ConditionsPanel scopeType={scopeType} scopeId={activeScopeId} />
      ) : (
        <EmptyState title="Select a scope" description="Choose a room, rack, or cage." />
      )}
    </div>
  )
}
