import { useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { getCageDetail, getCageHcm, getCageSubjects } from './cage.api.ts'
import { CageOverview } from './components/CageOverview.tsx'
import { CageSubjectsTable } from './components/CageSubjectsTable.tsx'
import { CageHcmTab } from './components/CageHcmTab.tsx'
import { getDvcActivity } from '@/features/integrations/tecniplast/dvc/dvc.api.ts'
import { buildCircadianTrace, smoothTrace } from '@/domain/dvc/dvc.circadian.ts'
import { computeCircadianStability, computeDayNightRatio, computeFragmentation } from '@/domain/dvc/dvc.metrics.ts'
import { Circadian24hChart } from '@/features/integrations/tecniplast/dvc-observatory/Circadian24hChart.tsx'
import { ResourceAuditPanel } from '@/features/system/audit/ResourceAuditPanel.tsx'
import { Timeline } from '@/features/system/audit/Timeline.tsx'
import { useTimelineEvents } from '@/features/system/audit/useTimelineEvents.ts'
import { ConditionsPanel } from '@/features/integrations/tecniplast/conditions/ConditionsPanel.tsx'
import { DVCObservatoryTab } from '@/features/integrations/tecniplast/dvc-observatory/DVCObservatoryTab.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { DetailLayout } from '@/components/DetailLayout.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { StatCard } from '@/components/StatCard.tsx'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { formatDateTime } from '@/lib/format.ts'

const HOUR = 60 * 60 * 1000

const buildLast24Series = (series: { ts: string[]; activity: number[] }) => {
  const endTs = new Date(series.ts[series.ts.length - 1] ?? new Date().toISOString()).getTime()
  const cutoff = endTs - 24 * HOUR
  const ts: string[] = []
  const activity: number[] = []
  series.ts.forEach((timestamp, index) => {
    const time = new Date(timestamp).getTime()
    if (time >= cutoff) {
      ts.push(timestamp)
      activity.push(series.activity[index] ?? 0)
    }
  })
  return { ts, activity }
}

const formatEventType = (value: string) =>
  value
    .split('_')
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')

export function CageDetailPage() {
  const { id } = useParams()
  const cageId = id ?? ''
  const queryClient = useQueryClient()
  const dvcObservatoryEnabled = useFeatureFlag('dvc.observatory.enabled')
  const circadianEnabled = useFeatureFlag('dvc.circadian.enabled')
  const [smoothing, setSmoothing] = useState<'none' | 'light'>('none')
  const timeline = useTimelineEvents('cage', cageId)

  const cageQuery = useQuery({
    queryKey: ['cage-detail', cageId],
    queryFn: () => getCageDetail(cageId),
    enabled: Boolean(cageId),
  })

  const subjectsQuery = useQuery({
    queryKey: ['cage-subjects', cageId],
    queryFn: () => getCageSubjects(cageId),
    enabled: Boolean(cageId),
  })

  const hcmQuery = useQuery({
    queryKey: ['cage-hcm', cageId],
    queryFn: () => getCageHcm(cageId),
    enabled: Boolean(cageId),
  })

  const activityQuery = useQuery({
    queryKey: ['cage-dvc-activity', cageId],
    queryFn: () => {
      const to = new Date()
      const from = new Date(to.getTime() - 7 * 24 * HOUR)
      return getDvcActivity(cageId, { from: from.toISOString(), to: to.toISOString(), interval: 15 })
    },
    enabled: Boolean(cageId) && circadianEnabled,
  })

  const activity = activityQuery.data
  const last24Series = useMemo(() => (activity ? buildLast24Series(activity) : null), [activity])

  const circadianToday = useMemo(() => {
    if (!activity) return null
    const trace = buildCircadianTrace(activity, 24)
    return smoothing === 'light' ? smoothTrace(trace, 3) : trace
  }, [activity, smoothing])

  const circadianBaseline = useMemo(() => {
    if (!activity) return null
    const trace = buildCircadianTrace(activity)
    return smoothing === 'light' ? smoothTrace(trace, 3) : trace
  }, [activity, smoothing])

  if (cageQuery.isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (cageQuery.isError || !cageQuery.data) {
    return (
      <EmptyState
        title="Cage unavailable"
        description={cageQuery.error?.message ?? 'Cage record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['cage-detail', cageId] })}
      />
    )
  }

  const cage = cageQuery.data
  const subjects = subjectsQuery.data?.subjects ?? []
  const dayNightRatio =
    last24Series && activity
      ? computeDayNightRatio({ ...activity, ...last24Series })
      : 0
  const stability =
    last24Series && activity
      ? computeCircadianStability({ ...activity, ...last24Series })
      : 0
  const fragmentation =
    last24Series && activity
      ? computeFragmentation({ ...activity, ...last24Series })
      : 0

  const dvcContent = !circadianEnabled ? (
    <EmptyState title="Circadian view disabled" description="Enable dvc.circadian.enabled to view the trace." />
  ) : activityQuery.isLoading ? (
    <div className="space-y-3">
      <Skeleton className="h-40 w-full" />
      <Skeleton className="h-24 w-full" />
    </div>
  ) : activityQuery.isError || !activity || !circadianToday || !circadianBaseline ? (
    <EmptyState
      title="DVC activity unavailable"
      description={(activityQuery.error as Error)?.message ?? 'Activity feed missing.'}
      actionLabel="Retry"
      onAction={() => queryClient.invalidateQueries({ queryKey: ['cage-dvc-activity', cageId] })}
    />
  ) : (
    <div className="space-y-6">
      <div className="grid gap-4 md:grid-cols-3">
        <StatCard
          title="Day/Night ratio"
          value={`${dayNightRatio.toFixed(2)}x`}
          helper="Daytime activity vs night."
        />
        <StatCard title="Circadian stability" value={`${stability}%`} helper="Consistency across cycles." />
        <StatCard title="Sleep fragmentation" value={`${fragmentation}%`} helper="Nighttime transitions." />
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Circadian 24h trace</CardTitle>
          <CardDescription>Today vs 7-day baseline with day/night shading.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Smoothing</span>
            <Button
              size="sm"
              variant={smoothing === 'none' ? 'default' : 'outline'}
              onClick={() => setSmoothing('none')}
            >
              None
            </Button>
            <Button
              size="sm"
              variant={smoothing === 'light' ? 'default' : 'outline'}
              onClick={() => setSmoothing('light')}
            >
              Light
            </Button>
          </div>
          <Circadian24hChart title="Circadian 24h" target={circadianToday} baseline={circadianBaseline} />
        </CardContent>
      </Card>
    </div>
  )

  const eventsContent = (
    <Card>
      <CardHeader>
        <CardTitle>Recent cage events</CardTitle>
        <CardDescription>Moves, bedding changes, cleaning, and enrichment.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3 text-sm text-slate-600">
        {cage.events.length ? (
          cage.events.map((event) => (
            <div key={event.id} className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-line bg-surface-2 p-3">
              <div>
                <p className="font-semibold text-slate-800">{formatEventType(event.type)}</p>
                <p className="text-xs text-slate-500">{formatDateTime(event.ts)}</p>
              </div>
              <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <Badge variant="outline">{event.type}</Badge>
                {event.payload.beddingBatch ? (
                  <span>Bedding {event.payload.beddingBatch}</span>
                ) : null}
                {event.payload.fromRoom ? <span>From {event.payload.fromRoom}</span> : null}
                {event.payload.toRoom ? <span>To {event.payload.toRoom}</span> : null}
              </div>
            </div>
          ))
        ) : (
          <p>No events recorded.</p>
        )}
      </CardContent>
    </Card>
  )

  const hcmContent = hcmQuery.isLoading ? (
    <div className="space-y-3">
      <Skeleton className="h-36 w-full" />
      <Skeleton className="h-48 w-full" />
      <Skeleton className="h-48 w-full" />
    </div>
  ) : hcmQuery.isError || !hcmQuery.data ? (
    <EmptyState
      title="HCM metadata unavailable"
      description={hcmQuery.error?.message ?? 'Unable to load linked HCM metadata.'}
      actionLabel="Retry"
      onAction={() => queryClient.invalidateQueries({ queryKey: ['cage-hcm', cageId] })}
    />
  ) : (
    <CageHcmTab hcm={hcmQuery.data} />
  )

  const tabs = [
    { id: 'overview', label: 'Overview', content: <CageOverview cage={cage} /> },
    {
      id: 'timeline',
      label: 'Timeline',
      content: (
        <Card>
          <CardHeader>
            <CardTitle>Cage timeline</CardTitle>
            <CardDescription>Audit and care events.</CardDescription>
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
    { id: 'events', label: 'Events', content: eventsContent },
    { id: 'subjects', label: 'Subjects', content: <CageSubjectsTable subjects={subjects} /> },
    { id: 'hcm', label: 'HCM', content: hcmContent },
    { id: 'conditions', label: 'Conditions', content: <ConditionsPanel scopeType="cage" scopeId={cage.id} /> },
    { id: 'audit', label: 'Audit', content: <ResourceAuditPanel resourceType="cage" resourceId={cage.id} /> },
    { id: 'dvc', label: 'DVC', content: dvcContent },
    ...(dvcObservatoryEnabled
      ? [
        {
          id: 'dvc-observatory',
          label: 'DVC Observatory',
          content: <DVCObservatoryTab cageId={cage.id} />,
        },
      ]
      : []),
  ]
  return (
    <DetailLayout
      title={`Cage ${cage.label}`}
      subtitle={`${cage.room} · ${cage.rack}`}
      backLink="/cages"
      backLabel="Cages"
      tabs={tabs}
    />
  )
}
