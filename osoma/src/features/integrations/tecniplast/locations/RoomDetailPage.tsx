import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getRoom, getRacks, getCages } from './locations.api.ts'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { SegmentedTabs } from '@/components/SegmentedTabs.tsx'
import { ResourceAuditPanel } from '@/features/system/audit/ResourceAuditPanel.tsx'
import { Timeline } from '@/features/system/audit/Timeline.tsx'
import { useTimelineEvents } from '@/features/system/audit/useTimelineEvents.ts'
import { ConditionsPanel } from '@/features/integrations/tecniplast/conditions/ConditionsPanel.tsx'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { TimeTravelPanel } from '@/features/core/scenario/TimeTravelPanel.tsx'

export function RoomDetailPage() {
  const { roomId } = useParams()
  const queryClient = useQueryClient()

  const roomQuery = useQuery({
    queryKey: ['tp-room', roomId],
    queryFn: () => getRoom(roomId ?? ''),
    enabled: Boolean(roomId),
  })

  const racksQuery = useQuery({
    queryKey: ['tp-room-racks', roomId],
    queryFn: () => getRacks(roomId ?? ''),
    enabled: Boolean(roomId),
  })

  const cagesQuery = useQuery({
    queryKey: ['tp-room-cages', roomId],
    queryFn: async () => {
      const racks = await getRacks(roomId ?? '')
      const cageLists = await Promise.all(racks.map((rack) => getCages(rack.id)))
      return cageLists.flat()
    },
    enabled: Boolean(roomId),
  })

  const timeline = useTimelineEvents('room', roomId ?? '')
  const timeTravelEnabled = useFeatureFlag('studyal.timeTravelLab')

  if (roomQuery.isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (roomQuery.isError || !roomQuery.data) {
    return (
      <EmptyState
        title="Room unavailable"
        description={roomQuery.error?.message ?? 'Room record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['tp-room', roomId] })}
      />
    )
  }

  const room = roomQuery.data
  const racks = racksQuery.data ?? []
  const cages = cagesQuery.data ?? []
  const occupancy = cages.reduce((sum, cage) => sum + cage.occupancy, 0)

  const tabs = [
    {
      id: 'overview',
      label: 'Overview',
      content: (
        <div className="space-y-6">
          <div className="grid gap-4 lg:grid-cols-3">
            <Card>
              <CardHeader>
                <CardTitle>Room profile</CardTitle>
                <CardDescription>Core room attributes.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-3 text-sm text-slate-600">
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Type</p>
                  <p className="font-semibold text-slate-700">{room.roomType}</p>
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Status</p>
                  <p className="font-semibold text-slate-700">{room.status}</p>
                </div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Capacity</CardTitle>
                <CardDescription>Rack and cage coverage.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-2 text-sm text-slate-600">
                <p>Racks: {racks.length}</p>
                <p>Cages: {cages.length}</p>
                <p>Occupancy: {occupancy}</p>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Quick actions</CardTitle>
                <CardDescription>Operational shortcuts.</CardDescription>
              </CardHeader>
              <CardContent className="space-y-2">
                <Button size="sm" variant="outline">Schedule inspection</Button>
                <Button size="sm" variant="outline">Flag maintenance</Button>
              </CardContent>
            </Card>
          </div>
        </div>
      ),
    },
    {
      id: 'timeline',
      label: 'Timeline',
      content: (
        <Card>
          <CardHeader>
            <CardTitle>Room timeline</CardTitle>
            <CardDescription>Audit and schedule events.</CardDescription>
          </CardHeader>
          <CardContent>
            {timeline.isLoading ? (
              <div className="space-y-3">
                {Array.from({ length: 3 }).map((_, index) => (
                  <Skeleton key={index} className="h-16 w-full" />
                ))}
              </div>
            ) : timeline.isError ? (
              <EmptyState
                title="Timeline unavailable"
                description={timeline.error?.message ?? 'Unable to load timeline.'}
                actionLabel="Retry"
                onAction={timeline.refetch}
              />
            ) : (
              <Timeline events={timeline.events} />
            )}
          </CardContent>
        </Card>
      ),
    },
    {
      id: 'conditions',
      label: 'Conditions',
      content: <ConditionsPanel scopeType="room" scopeId={room.id} />,
    },
    {
      id: 'audit',
      label: 'Audit',
      content: <ResourceAuditPanel resourceType="room" resourceId={room.id} />,
    },
  ]

  if (timeTravelEnabled) {
    tabs.splice(tabs.length - 1, 0, {
      id: 'time-travel',
      label: 'Time travel',
      content: <TimeTravelPanel scopeType="room" scopeId={room.id} scopeLabel={room.name} />,
    })
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/tp/map">
            <ArrowLeft className="h-4 w-4" />
            Back
          </Link>
        </Button>
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Room</p>
          <h2 className="font-display text-2xl">{room.name}</h2>
          <p className="text-sm text-slate-500">{room.id}</p>
        </div>
      </div>

      <SegmentedTabs tabs={tabs} />
    </div>
  )
}
