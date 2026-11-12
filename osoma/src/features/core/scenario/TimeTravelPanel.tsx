import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeftRight, History, PlayCircle, Zap } from 'lucide-react'
import { getConditionSnapshot } from '@/features/integrations/tecniplast/conditions/conditions.api.ts'
import { replayScenarioApi } from './scenario.api.ts'
import { ScenarioBuilder } from './ScenarioBuilder.tsx'
import { TimelineDiffView } from './TimelineDiffView.tsx'
import type { ReplayResult, Scenario } from '@/domain/scenario/scenario.types.ts'
import type { ScopeType } from '@/domain/scope.ts'
import type { ConditionSnapshot } from '@/features/integrations/tecniplast/tecniplast.types.ts'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDateTime } from '@/lib/format.ts'
import { cn } from '@/lib/utils.ts'

const toInputValue = (iso: string) => iso.slice(0, 16)

export function TimeTravelPanel({
  scopeType,
  scopeId,
  scopeLabel,
}: {
  scopeType: ScopeType
  scopeId: string
  scopeLabel?: string
}) {
  const [baseline] = useState(() => Date.now())
  const [selectedTime, setSelectedTime] = useState(() => new Date(baseline - 90 * 60000).toISOString())
  const [mode, setMode] = useState<'reality' | 'scenario'>('reality')
  const [scenario, setScenario] = useState<Scenario | null>(null)

  const hoursBack = useMemo(
    () => Math.max(0, Math.round((baseline - new Date(selectedTime).getTime()) / 3600000)),
    [selectedTime, baseline]
  )

  const realitySnapshot = useQuery<ConditionSnapshot>({
    queryKey: ['tt-snapshot', scopeType, scopeId, selectedTime],
    queryFn: () => getConditionSnapshot(scopeType, scopeId, { at: selectedTime }),
    staleTime: 0,
  })

  const scenarioSnapshot = useQuery<ConditionSnapshot | null>({
    queryKey: ['tt-snapshot-scenario', scopeType, scopeId, scenario?.id, selectedTime],
    queryFn: () =>
      scenario
        ? getConditionSnapshot(scopeType, scopeId, { at: selectedTime, scenarioId: scenario.id })
        : Promise.resolve<ConditionSnapshot | null>(null),
    enabled: Boolean(scenario),
    staleTime: 0,
  })

  const replay = useQuery<ReplayResult | null>({
    queryKey: ['tt-replay', scenario?.id, selectedTime],
    queryFn: () =>
      scenario ? replayScenarioApi(scenario.id, selectedTime) : Promise.resolve<ReplayResult | null>(null),
    enabled: Boolean(scenario),
    staleTime: 0,
  })

  const activeSnapshot: ConditionSnapshot | null | undefined =
    mode === 'scenario' ? scenarioSnapshot.data : realitySnapshot.data

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
          <div>
            <CardTitle>Time-Travel Lab</CardTitle>
            <CardDescription>Rewind the room, branch a scenario, replay outcomes.</CardDescription>
          </div>
          <div className="flex items-center gap-2">
            <Button
              size="sm"
              variant={mode === 'reality' ? 'default' : 'outline'}
              onClick={() => setMode('reality')}
            >
              <History className="mr-1 h-4 w-4" />
              Reality
            </Button>
            <Button
              size="sm"
              variant={mode === 'scenario' ? 'default' : 'outline'}
              onClick={() => setMode('scenario')}
              disabled={!scenario}
            >
              <ArrowLeftRight className="mr-1 h-4 w-4" />
              Scenario
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid gap-3 md:grid-cols-[1fr_280px]">
            <div className="space-y-2">
              <label className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                Timeline scrubber ({hoursBack}h ago)
              </label>
              <input
                type="range"
                min={0}
                max={48}
                value={hoursBack}
                onChange={(event) => {
                  const next = Number(event.target.value)
                  setSelectedTime(new Date(baseline - next * 3600000).toISOString())
                }}
                className="w-full accent-slate-800"
              />
            </div>
            <div className="space-y-2">
              <label className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Exact timestamp</label>
              <input
                type="datetime-local"
                value={toInputValue(selectedTime)}
                onChange={(event) => setSelectedTime(new Date(event.target.value).toISOString())}
                className="w-full rounded-lg border border-line px-3 py-2 text-sm text-slate-700"
              />
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <Badge variant="outline">Scope: {scopeLabel ?? scopeId}</Badge>
            <Badge variant="default">Viewing: {formatDateTime(selectedTime)}</Badge>
            {scenario ? <Badge variant="default">Scenario: {scenario.label}</Badge> : null}
          </div>
        </CardContent>
      </Card>

      <SnapshotView
        title={mode === 'scenario' ? 'Scenario view' : 'Reality view'}
        snapshot={activeSnapshot}
        isLoading={mode === 'scenario' ? scenarioSnapshot.isLoading : realitySnapshot.isLoading}
        error={mode === 'scenario' ? scenarioSnapshot.error : realitySnapshot.error}
      />

      {scenario ? (
        <TimelineDiffView result={replay.data} isLoading={replay.isLoading} />
      ) : (
        <ScenarioBuilder
          scopeType={scopeType}
          scopeId={scopeId}
          scopeLabel={scopeLabel}
          baseTimestamp={selectedTime}
          onScenarioCreated={(created) => {
            setScenario(created)
            setMode('scenario')
          }}
        />
      )}
    </div>
  )
}

function SnapshotView({
  title,
  snapshot,
  isLoading,
  error,
}: {
  title: string
  snapshot?: ConditionSnapshot | null
  isLoading?: boolean
  error?: unknown
}) {
  if (isLoading) {
    return <Skeleton className="h-40 w-full" />
  }

  if (error) {
    return (
      <EmptyState
        title="Snapshot unavailable"
        description={(error as Error)?.message ?? 'Could not load snapshot.'}
      />
    )
  }

  if (!snapshot) {
    return <EmptyState title="Pick a timestamp" description="Move the scrubber to load a snapshot." />
  }

  const activeAlarms = (snapshot.alarms ?? []).filter((alarm) => alarm.status === 'active')
  const timestampLabel = snapshot.at ?? snapshot.collectedAt

  return (
    <Card>
      <CardHeader className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
        <div>
          <CardTitle>{title}</CardTitle>
          <CardDescription>{formatDateTime(timestampLabel)}</CardDescription>
        </div>
        <Badge variant="outline" className="gap-1">
          <Zap className="h-4 w-4" /> {snapshot.metrics.length} signals
        </Badge>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-3 md:grid-cols-3">
          {snapshot.metrics.map((metric) => (
            <div
              key={metric.metric}
              className={cn(
                'rounded-xl border bg-white p-3',
                metric.status === 'out-of-range' ? 'border-amber-200 bg-amber-50' : 'border-line'
              )}
            >
              <div className="flex items-center justify-between text-xs uppercase tracking-[0.2em] text-slate-500">
                <span>{metric.metric}</span>
                <Badge variant={metric.status === 'out-of-range' ? 'critical' : 'success'} className="text-[10px]">
                  {metric.status === 'out-of-range' ? 'Alert' : 'OK'}
                </Badge>
              </div>
              <div className="mt-2 text-xl font-semibold text-slate-900">
                {metric.value} {metric.unit}
              </div>
              <p className="text-xs text-slate-500">
                Range {metric.min}-{metric.max} {metric.unit}
              </p>
            </div>
          ))}
        </div>

        <div className="space-y-2">
          <div className="flex items-center gap-2 text-sm font-semibold text-slate-700">
            <PlayCircle className="h-4 w-4" /> Alarms ({activeAlarms.length})
          </div>
          {activeAlarms.length === 0 ? (
            <p className="text-sm text-slate-500">No active alarms at this moment.</p>
          ) : (
            <div className="space-y-2">
              {activeAlarms.map((alarm) => (
                <div
                  key={alarm.id}
                  className="rounded-xl border border-line bg-surface-2 p-3 text-sm text-slate-700"
                >
                  <div className="flex items-center justify-between">
                    <div className="font-semibold">{alarm.sensorType}</div>
                    <Badge variant="outline">{alarm.severity}</Badge>
                  </div>
                  <p className="text-xs text-slate-500">{alarm.message}</p>
                </div>
              ))}
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  )
}
