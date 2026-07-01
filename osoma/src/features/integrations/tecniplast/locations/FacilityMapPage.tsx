import { useCallback, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ChevronRight, Filter } from 'lucide-react'
import { getFacilities } from '../facilities/facilities.api.ts'
import { getRooms, getRacks, getCages } from './locations.api.ts'
import { getAlarms } from '../alarms/alarms.api.ts'
import { getSensors } from '../sensors/sensors.api.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { cn } from '@/lib/utils.ts'

type FilterState = {
  alarmsOnly: boolean
  outOfRange: boolean
  needsAttention: boolean
  sensorOffline: boolean
}

export function FacilityMapPage() {
  const [facilityId, setFacilityId] = useState<string>('')
  const [roomId, setRoomId] = useState<string>('')
  const [rackId, setRackId] = useState<string>('')
  const [cageId, setCageId] = useState<string>('')
  const [filters, setFilters] = useState<FilterState>({
    alarmsOnly: false,
    outOfRange: false,
    needsAttention: false,
    sensorOffline: false,
  })

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

  const activeRoomId = roomId || roomsQuery.data?.[0]?.id

  const racksQuery = useQuery({
    queryKey: ['tp-racks', activeRoomId],
    queryFn: () => getRacks(activeRoomId ?? ''),
    enabled: Boolean(activeRoomId),
  })

  const activeRackId = rackId || racksQuery.data?.[0]?.id

  const cagesQuery = useQuery({
    queryKey: ['tp-cages', activeRackId],
    queryFn: () => getCages(activeRackId ?? ''),
    enabled: Boolean(activeRackId),
  })

  const alarmsQuery = useQuery({
    queryKey: ['tp-alarms', activeFacilityId, 'active-map'],
    queryFn: () => getAlarms({ facilityId: activeFacilityId, status: 'active', page: 1, pageSize: 200 }),
    enabled: Boolean(activeFacilityId),
  })

  const sensorsQuery = useQuery({
    queryKey: ['tp-sensors', activeFacilityId, 'offline'],
    queryFn: () => getSensors({ facilityId: activeFacilityId }),
    enabled: Boolean(activeFacilityId),
  })

  // State is derived inline during render (activeFacilityId, etc.)
  // No need to synchronize back to local state variables via useEffect.

  const alarmScopeSet = useMemo(() => {
    const data = alarmsQuery.data?.data ?? []
    return new Set(data.map((alarm) => `${alarm.scopeType}:${alarm.scopeId}`))
  }, [alarmsQuery.data?.data])

  const offlineScopeSet = useMemo(() => {
    const data = (sensorsQuery.data ?? []).filter((sensor) => sensor.status === 'offline')
    return new Set(data.map((sensor) => `${sensor.scopeType}:${sensor.scopeId}`))
  }, [sensorsQuery.data])

  const filterItem = useCallback(
    (scopeType: string, scopeId: string, needsAttention: boolean) => {
      const key = `${scopeType}:${scopeId}`
      if (filters.alarmsOnly && !alarmScopeSet.has(key)) return false
      if (filters.outOfRange && !alarmScopeSet.has(key)) return false
      if (filters.needsAttention && !needsAttention) return false
      if (filters.sensorOffline && !offlineScopeSet.has(key)) return false
      return true
    },
    [filters, alarmScopeSet, offlineScopeSet]
  )

  const filteredRooms = useMemo(() => {
    return (roomsQuery.data ?? []).filter((room) =>
      filterItem('room', room.id, room.status !== 'operational')
    )
  }, [roomsQuery.data, filterItem])

  const filteredRacks = useMemo(() => {
    return (racksQuery.data ?? []).filter((rack) =>
      filterItem('rack', rack.id, rack.status !== 'operational')
    )
  }, [racksQuery.data, filterItem])

  const filteredCages = useMemo(() => {
    return (cagesQuery.data ?? []).filter((cage) =>
      filterItem('cage', cage.id, cage.status !== 'occupied')
    )
  }, [cagesQuery.data, filterItem])

  const selectedRoom = roomsQuery.data?.find((room) => room.id === roomId)
  const selectedRack = racksQuery.data?.find((rack) => rack.id === rackId)
  const selectedCage = cagesQuery.data?.find((cage) => cage.id === cageId)

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
        title="Facility map unavailable"
        description={facilitiesQuery.error?.message ?? 'Facilities could not be loaded.'}
        actionLabel="Retry"
        onAction={() => facilitiesQuery.refetch()}
      />
    )
  }

  const activeFacility = facilitiesQuery.data.find((facility) => facility.id === activeFacilityId)

  return (
    <div className="space-y-6">
      <PageHeader
        title="Facility Map"
        subtitle="Drill down from site to cage with fast filters and keyboard navigation."
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <select
              className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
              value={activeFacilityId}
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
          </div>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
          <CardDescription>Focus on urgent issues.</CardDescription>
        </CardHeader>
        <CardContent className="flex flex-wrap items-center gap-2">
          <Filter className="h-4 w-4 text-slate-400" />
          <ToggleFilter
            label="Alarm only"
            active={filters.alarmsOnly}
            onToggle={() => setFilters((prev) => ({ ...prev, alarmsOnly: !prev.alarmsOnly }))}
          />
          <ToggleFilter
            label="Out-of-range"
            active={filters.outOfRange}
            onToggle={() => setFilters((prev) => ({ ...prev, outOfRange: !prev.outOfRange }))}
          />
          <ToggleFilter
            label="Needs attention"
            active={filters.needsAttention}
            onToggle={() => setFilters((prev) => ({ ...prev, needsAttention: !prev.needsAttention }))}
          />
          <ToggleFilter
            label="Sensor offline"
            active={filters.sensorOffline}
            onToggle={() => setFilters((prev) => ({ ...prev, sensorOffline: !prev.sensorOffline }))}
          />
        </CardContent>
      </Card>

      <div className="flex flex-wrap items-center gap-2 text-sm text-slate-500">
        <span>{activeFacility?.name}</span>
        {activeRoomId ? (
          <>
            <ChevronRight className="h-4 w-4" />
            <span>{activeRoomId}</span>
          </>
        ) : null}
        {activeRackId ? (
          <>
            <ChevronRight className="h-4 w-4" />
            <span>{activeRackId}</span>
          </>
        ) : null}
        {cageId ? (
          <>
            <ChevronRight className="h-4 w-4" />
            <span>{cageId}</span>
          </>
        ) : null}
      </div>

      <div className="grid gap-4 lg:grid-cols-[repeat(4,minmax(0,1fr))]">
        <ListColumn
          title="Rooms"
          items={filteredRooms}
          selectedId={activeRoomId ?? ''}
          onSelect={(id) => {
            setRoomId(id)
            setRackId('')
            setCageId('')
          }}
          renderLabel={(room) => room.name}
          renderMeta={(room) => `${room.rackCount} racks`}
        />
        <ListColumn
          title="Racks"
          items={filteredRacks}
          selectedId={activeRackId ?? ''}
          onSelect={(id) => {
            setRackId(id)
            setCageId('')
          }}
          renderLabel={(rack) => rack.label}
          renderMeta={(rack) => `${rack.cageCount} cages`}
        />
        <ListColumn
          title="Cages"
          items={filteredCages}
          selectedId={cageId}
          onSelect={(id) => setCageId(id)}
          renderLabel={(cage) => cage.label}
          renderMeta={(cage) => `${cage.occupancy} animals`}
        />
        <Card className="h-full">
          <CardHeader>
            <CardTitle>Detail panel</CardTitle>
            <CardDescription>Quick view for the selected scope.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            {selectedCage ? (
              <>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Status</p>
                  <p className="font-semibold text-slate-700">{selectedCage.status}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Occupancy</p>
                  <p className="font-semibold text-slate-700">{selectedCage.occupancy}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Species</p>
                  <p className="font-semibold text-slate-700">{selectedCage.species}</p>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button size="sm" variant="outline" asChild>
                    <Link to={`/tp/rooms/${selectedRoom?.id}`}>Open room</Link>
                  </Button>
                  <Button size="sm" asChild>
                    <Link to={`/tp/cages/${selectedCage.id}`}>Open cage</Link>
                  </Button>
                </div>
              </>
            ) : (
              <div className="space-y-2">
                <p>Select a cage to see details.</p>
                {selectedRoom ? (
                  <Button size="sm" variant="outline" asChild>
                    <Link to={`/tp/rooms/${selectedRoom.id}`}>Open room detail</Link>
                  </Button>
                ) : null}
                {selectedRack ? (
                  <p className="text-xs text-slate-500">Rack {selectedRack.label} selected.</p>
                ) : null}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  )
}

function ToggleFilter({ label, active, onToggle }: { label: string; active: boolean; onToggle: () => void }) {
  return (
    <Button size="sm" variant={active ? 'default' : 'outline'} onClick={onToggle}>
      {label}
    </Button>
  )
}

function ListColumn<T extends { id: string }>(
  { title, items, selectedId, onSelect, renderLabel, renderMeta }: {
    title: string
    items: T[]
    selectedId: string
    onSelect: (id: string) => void
    renderLabel: (item: T) => string
    renderMeta: (item: T) => string
  }
) {
  const activeIndex = useMemo(() => {
    const index = items.findIndex((item) => item.id === selectedId)
    return index >= 0 ? index : 0
  }, [items, selectedId])

  const handleKey = (event: React.KeyboardEvent<HTMLDivElement>) => {
    if (!items.length) return
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      const nextIndex = Math.min(activeIndex + 1, items.length - 1)
      onSelect(items[nextIndex].id)
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault()
      const nextIndex = Math.max(activeIndex - 1, 0)
      onSelect(items[nextIndex].id)
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{items.length} available</CardDescription>
      </CardHeader>
      <CardContent>
        <div
          tabIndex={0}
          onKeyDown={handleKey}
          className="space-y-2 rounded-xl border border-line bg-white p-2 outline-none"
        >
          {items.length ? (
            items.map((item, index) => (
              <button
                key={item.id}
                type="button"
                className={cn(
                  'flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm transition',
                  item.id === selectedId || index === activeIndex ? 'bg-surface-2' : 'hover:bg-surface-3'
                )}
                onClick={() => onSelect(item.id)}
              >
                <span className="font-medium text-slate-700">{renderLabel(item)}</span>
                <span className="text-xs text-slate-500">{renderMeta(item)}</span>
              </button>
            ))
          ) : (
            <p className="text-xs text-slate-500">No items match the filter.</p>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
