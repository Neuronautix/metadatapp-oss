import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { getFacilities } from '@/features/integrations/tecniplast/facilities/facilities.api.ts'
import { getRooms, getRacks, getCages } from '@/features/integrations/tecniplast/locations/locations.api.ts'
import { getAlarms } from '@/features/integrations/tecniplast/alarms/alarms.api.ts'
import { getSensors } from '@/features/integrations/tecniplast/sensors/sensors.api.ts'
import { getWorkOrders } from '@/features/integrations/tecniplast/work-orders/work-orders.api.ts'
import { getConditionSnapshot } from '@/features/integrations/tecniplast/conditions/conditions.api.ts'
import { getStudies } from '@/features/core/studies/study.api.ts'
import { getAuditEntries } from '@/features/system/audit/audit.api.ts'
import { listScenarios } from '@/features/core/scenario/scenario.api.ts'
import { HealthStrip } from './HealthStrip.tsx'
import { SystemPulse } from './SystemPulse.tsx'
import { ConditionsHeatmap } from './ConditionsHeatmap.tsx'
import { TopologyView } from './TopologyView.tsx'
import { InsightCards } from './InsightCards.tsx'
import type { PulseRange } from './observatory.types.ts'
import {
  buildHealthMetrics,
  buildHeatmapRows,
  buildInsights,
  buildMetricSeries,
  buildPulseLanes,
  buildTopologyTree,
} from './observatory.adapters.ts'

export function ObservatoryPage() {
  const [facilityId, setFacilityId] = useState('')
  const [range, setRange] = useState<PulseRange>('24h')

  const facilitiesQuery = useQuery({
    queryKey: ['tp-facilities'],
    queryFn: () => getFacilities(),
  })

  const activeFacilityId = facilityId || facilitiesQuery.data?.[0]?.id
  const facilityName = facilitiesQuery.data?.find((facility) => facility.id === activeFacilityId)?.name ?? 'Facility'

  useEffect(() => {
    if (activeFacilityId && activeFacilityId !== facilityId) {
      setFacilityId(activeFacilityId)
    }
  }, [activeFacilityId, facilityId])

  const roomsQuery = useQuery({
    queryKey: ['tp-rooms', activeFacilityId],
    queryFn: () => getRooms(activeFacilityId ?? ''),
    enabled: Boolean(activeFacilityId),
  })
  const roomIds = roomsQuery.data?.map((room) => room.id) ?? []

  const alarmsQuery = useQuery({
    queryKey: ['observatory-alarms', activeFacilityId],
    queryFn: () => getAlarms({ facilityId: activeFacilityId, page: 1, pageSize: 200 }),
    enabled: Boolean(activeFacilityId),
  })

  const sensorsQuery = useQuery({
    queryKey: ['observatory-sensors', activeFacilityId],
    queryFn: () => getSensors({ facilityId: activeFacilityId }),
    enabled: Boolean(activeFacilityId),
  })

  const workOrdersQuery = useQuery({
    queryKey: ['observatory-work-orders', activeFacilityId],
    queryFn: () => getWorkOrders(activeFacilityId ?? ''),
    enabled: Boolean(activeFacilityId),
  })

  const studiesQuery = useQuery({
    queryKey: ['observatory-studies'],
    queryFn: () => getStudies({ page: 1, pageSize: 120 }),
  })

  const auditQuery = useQuery({
    queryKey: ['observatory-audit'],
    queryFn: () => getAuditEntries({ page: 1, pageSize: 160 }),
  })

  const scenariosQuery = useQuery({
    queryKey: ['observatory-scenarios'],
    queryFn: () => listScenarios(),
  })

  const conditionsQuery = useQuery({
    queryKey: ['observatory-conditions', activeFacilityId, roomIds.join(',')],
    queryFn: async () => {
      const rooms = roomsQuery.data ?? []
      const snapshots = await Promise.all(rooms.map((room) => getConditionSnapshot('room', room.id)))
      return snapshots
    },
    enabled: Boolean(roomsQuery.data?.length),
  })

  const topologyQuery = useQuery({
    queryKey: ['observatory-topology', activeFacilityId, roomIds.join(',')],
    queryFn: async () => {
      const rooms = roomsQuery.data ?? []
      const racks = (await Promise.all(rooms.map((room) => getRacks(room.id)))).flat()
      const cages = (await Promise.all(racks.map((rack) => getCages(rack.id)))).flat()
      return { rooms, racks, cages }
    },
    enabled: Boolean(roomsQuery.data?.length),
  })

  if (facilitiesQuery.isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-60" />
        <Skeleton className="h-80 w-full" />
      </div>
    )
  }

  if (facilitiesQuery.isError || !facilitiesQuery.data?.length) {
    return (
      <EmptyState
        title="Observatory unavailable"
        description={facilitiesQuery.error?.message ?? 'Facilities could not be loaded.'}
        actionLabel="Retry"
        onAction={() => facilitiesQuery.refetch()}
      />
    )
  }

  const alarms = alarmsQuery.data?.data ?? []
  const sensors = sensorsQuery.data ?? []
  const workOrders = workOrdersQuery.data ?? []
  const studies = studiesQuery.data?.data ?? []
  const audits = auditQuery.data?.data ?? []
  const scenarios = scenariosQuery.data ?? []
  const rooms = roomsQuery.data ?? []
  const snapshots = conditionsQuery.data ?? []
  const racks = topologyQuery.data?.racks ?? []
  const cages = topologyQuery.data?.cages ?? []

  const outOfRangeRooms = snapshots.filter((snapshot) =>
    snapshot.metrics.some((metric) => metric.status === 'out-of-range')
  ).length

  const healthMetrics = useMemo(
    () =>
      buildHealthMetrics({
        alarms,
        sensors,
        workOrders,
        studies,
        outOfRangeRooms,
      }),
    [alarms, sensors, workOrders, studies, outOfRangeRooms]
  )

  const pulseLanes = useMemo(
    () => buildPulseLanes({ alarms, audits, range }),
    [alarms, audits, range]
  )

  const heatmapRows = useMemo(() => buildHeatmapRows(rooms, snapshots), [rooms, snapshots])
  const metricSeries = useMemo(() => buildMetricSeries(heatmapRows), [heatmapRows])

  const topology = useMemo(() => {
    if (!topologyQuery.data) return undefined
    return buildTopologyTree({
      facilityName,
      rooms: topologyQuery.data.rooms,
      racks,
      cages,
      alarms,
      sensors,
      snapshots,
    })
  }, [facilityName, topologyQuery.data, racks, cages, alarms, sensors, snapshots])

  const insights = useMemo(
    () => buildInsights({ alarms, audits, scenarios, rooms: roomsQuery.data ?? [], racks, cages }),
    [alarms, audits, scenarios, roomsQuery.data, racks, cages]
  )

  return (
    <div className="space-y-8">
      <PageHeader
        title="The Observatory"
        subtitle="A visual command center that surfaces health, risk, and causal momentum."
        actions={
          <select
            className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
            value={activeFacilityId}
            onChange={(event) => setFacilityId(event.target.value)}
          >
            {facilitiesQuery.data.map((facility) => (
              <option key={facility.id} value={facility.id}>
                {facility.name}
              </option>
            ))}
          </select>
        }
      />

      <HealthStrip
        metrics={healthMetrics}
        isLoading={
          alarmsQuery.isLoading ||
          sensorsQuery.isLoading ||
          workOrdersQuery.isLoading ||
          studiesQuery.isLoading ||
          conditionsQuery.isLoading
        }
      />

      <SystemPulse
        range={range}
        onRangeChange={setRange}
        lanes={pulseLanes}
        isLoading={auditQuery.isLoading || alarmsQuery.isLoading}
      />

      <div className="grid gap-6 lg:grid-cols-[1.3fr_0.9fr]">
        <ConditionsHeatmap
          rows={heatmapRows}
          series={metricSeries}
          isLoading={conditionsQuery.isLoading}
        />
        <TopologyView root={topology} isLoading={topologyQuery.isLoading} />
      </div>

      <InsightCards insights={insights} isLoading={auditQuery.isLoading || scenariosQuery.isLoading} />
    </div>
  )
}
