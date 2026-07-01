import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { AlarmClock, AlertTriangle, MapPinned, ShieldAlert } from 'lucide-react'
import { getFacilities, getFacility } from './facilities.api.ts'
import { getAlarms } from '../alarms/alarms.api.ts'
import { getWorkOrders } from '../work-orders/work-orders.api.ts'
import { getSensors } from '../sensors/sensors.api.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { StatCard } from '@/components/StatCard.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDateTime } from '@/lib/format.ts'

export function OperationsOverviewPage() {
  const [facilityId, setFacilityId] = useState('')

  const facilitiesQuery = useQuery({
    queryKey: ['tp-facilities'],
    queryFn: () => getFacilities(),
  })

  const activeFacilityId = facilityId || facilitiesQuery.data?.[0]?.id

  const facilityQuery = useQuery({
    queryKey: ['tp-facility', activeFacilityId],
    queryFn: () => getFacility(activeFacilityId ?? ''),
    enabled: Boolean(activeFacilityId),
  })

  const alarmsQuery = useQuery({
    queryKey: ['tp-alarms', activeFacilityId, 'active'],
    queryFn: () => getAlarms({ facilityId: activeFacilityId, status: 'active', page: 1, pageSize: 6 }),
    enabled: Boolean(activeFacilityId),
  })

  const workOrdersQuery = useQuery({
    queryKey: ['tp-work-orders', activeFacilityId],
    queryFn: () => getWorkOrders(activeFacilityId),
    enabled: Boolean(activeFacilityId),
  })

  const sensorsQuery = useQuery({
    queryKey: ['tp-sensors', activeFacilityId],
    queryFn: () => getSensors({ facilityId: activeFacilityId }),
    enabled: Boolean(activeFacilityId),
  })

  const facilityStats = facilityQuery.data?.stats

  const offlineSensors = useMemo(() => {
    if (!activeFacilityId) return []
    return (sensorsQuery.data ?? []).filter((sensor) => sensor.status === 'offline')
  }, [sensorsQuery.data, activeFacilityId])

  const [now] = useState(() => Date.now())
  const dayAhead = now + 24 * 3600000
  const todaysWork = (workOrdersQuery.data ?? []).filter((order) => {
    const due = new Date(order.dueAt).getTime()
    return order.status !== 'done' && due >= now && due <= dayAhead
  })

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
        title="Operations unavailable"
        description={facilitiesQuery.error?.message ?? 'Facilities could not be loaded.'}
        actionLabel="Retry"
        onAction={() => facilitiesQuery.refetch()}
      />
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Operations Overview"
        subtitle="Daily operator control room for animal facility operations."
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

      <div className="grid gap-4 md:grid-cols-4">
        <StatCard title="Active alarms" value={`${facilityStats?.activeAlarms ?? 0}`} helper="Requires response" />
        <StatCard title="Cages out-of-range" value={`${facilityStats?.cagesOutOfRange ?? 0}`} helper="Threshold breach" />
        <StatCard title="Tasks due" value={`${facilityStats?.tasksDue ?? 0}`} helper="Next 24 hours" />
        <StatCard title="Sensors offline" value={`${facilityStats?.sensorsOffline ?? 0}`} helper="Last 12 hours" />
      </div>

      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>Today</CardTitle>
            <CardDescription>Upcoming checks and recent incidents.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-6">
            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Upcoming checks</p>
              <div className="mt-3 space-y-3">
                {todaysWork.length ? (
                  todaysWork.map((order) => (
                    <div key={order.id} className="rounded-2xl border border-line bg-white p-3 text-sm">
                      <div className="flex items-center justify-between">
                        <span className="font-medium text-slate-700">{order.title}</span>
                        <span className="text-xs text-slate-500">{order.scopeLabel}</span>
                      </div>
                      <p className="mt-1 text-xs text-slate-500">Due {formatDateTime(order.dueAt)}</p>
                    </div>
                  ))
                ) : (
                  <p className="text-sm text-slate-500">No upcoming checks in the next 24 hours.</p>
                )}
              </div>
            </div>

            <div>
              <p className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Recent incidents</p>
              <div className="mt-3 space-y-3">
                {(alarmsQuery.data?.data ?? []).map((alarm) => (
                  <div key={alarm.id} className="rounded-2xl border border-line bg-white p-3 text-sm">
                    <div className="flex items-center justify-between">
                      <span className="font-medium text-slate-700">{alarm.message}</span>
                      <span className="text-xs text-slate-500">{alarm.scopeLabel}</span>
                    </div>
                    <p className="mt-1 text-xs text-slate-500">{formatDateTime(alarm.createdAt)}</p>
                  </div>
                ))}
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Quick actions</CardTitle>
              <CardDescription>Jump to high-velocity workflows.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <Button className="w-full" asChild>
                <Link to="/tp/alarms">
                  <ShieldAlert className="h-4 w-4" />
                  Open Alarms Center
                </Link>
              </Button>
              <Button variant="outline" className="w-full" asChild>
                <Link to="/tp/map">
                  <MapPinned className="h-4 w-4" />
                  Facility Map
                </Link>
              </Button>
              <Button variant="outline" className="w-full" asChild>
                <Link to="/tp/conditions">
                  <AlertTriangle className="h-4 w-4" />
                  Conditions Dashboard
                </Link>
              </Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Sensor health</CardTitle>
              <CardDescription>Offline devices detected.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 text-sm text-slate-600">
              {offlineSensors.length ? (
                offlineSensors.slice(0, 4).map((sensor) => (
                  <div key={sensor.id} className="flex items-center justify-between">
                    <span>{sensor.scopeLabel}</span>
                    <span className="text-xs text-slate-500">{sensor.metric}</span>
                  </div>
                ))
              ) : (
                <div className="flex items-center gap-2 text-xs text-slate-500">
                  <AlarmClock className="h-4 w-4" />
                  All sensors reporting.
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
