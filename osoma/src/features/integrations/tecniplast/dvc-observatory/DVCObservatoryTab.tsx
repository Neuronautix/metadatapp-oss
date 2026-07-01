import { useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Pin, PinOff } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { formatDateTime } from '@/lib/format.ts'
import type { CageContextSnapshot, QualitySignals } from '@/domain/dvc/dvc.types.ts'
import { computeAnomalies } from '@/domain/dvc/dvc.anomalies.ts'
import { buildCircadianTrace, meanTrace, smoothTrace, stdTrace } from '@/domain/dvc/dvc.circadian.ts'
import { ControlTower, type DvcOverlayToggles, type DvcThresholds } from './ControlTower.tsx'
import { DVCObservatoryLayout } from './DVCObservatoryLayout.tsx'
import { ActivityTimelineChart, type TimelineSelection } from './ActivityTimelineChart.tsx'
import { DetailsDrawer } from './DetailsDrawer.tsx'
import { ContextSnapshotPanel } from './ContextSnapshotPanel.tsx'
import { WelfarePanel } from './WelfarePanel.tsx'
import { BehavioralOutputsPanel } from './BehavioralOutputsPanel.tsx'
import { Circadian24hChart } from './Circadian24hChart.tsx'
import {
  getDvcActivity,
  getDvcAiOutput,
  getDvcCompare,
  getDvcContextSnapshot,
  getDvcEnvironment,
  getDvcEvents,
  getDvcWelfareAlarms,
} from '@/features/integrations/tecniplast/dvc/dvc.api.ts'

const defaultThresholds: DvcThresholds = {
  tempRange: { min: 20, max: 26 },
  humidityRange: { min: 40, max: 60 },
  activityDropPct: 18,
  missingDataPct: 12,
}

const defaultOverlays: DvcOverlayToggles = {
  environment: true,
  events: true,
  welfare: true,
  treatments: true,
  ai: true,
}

const toIso = (value: string) => new Date(value).toISOString()

const calcRange = (rangeHours: number, customRange: { from: string; to: string }) => {
  if (rangeHours === 0 && customRange.from && customRange.to) {
    return { from: toIso(customRange.from), to: toIso(customRange.to) }
  }
  const to = new Date()
  const from = new Date(to.getTime() - rangeHours * 60 * 60 * 1000)
  return { from: from.toISOString(), to: to.toISOString() }
}

const computeMissingPct = (ts: string[], intervalMinutes: number) => {
  if (ts.length < 2) return 0
  const expected = intervalMinutes * 60 * 1000
  let missing = 0
  for (let i = 1; i < ts.length; i += 1) {
    const gap = new Date(ts[i]).getTime() - new Date(ts[i - 1]).getTime()
    if (gap > expected * 1.5) {
      missing += Math.max(1, Math.round(gap / expected))
    }
  }
  const total = Math.max(1, Math.round((new Date(ts[ts.length - 1]).getTime() - new Date(ts[0]).getTime()) / expected))
  return Math.min(100, Math.round((missing / total) * 100))
}

export function DVCObservatoryTab({ cageId: propCageId }: { cageId?: string }) {
  const params = useParams()
  const cageId = propCageId ?? params.cageId ?? ''
  const queryClient = useQueryClient()
  const welfareEnabled = useFeatureFlag('dvc.studyal.welfare.enabled')
  const aiEnabled = useFeatureFlag('dvc.aiInsights.enabled')
  const circadianEnabled = useFeatureFlag('dvc.circadian.enabled')

  const [rangeHours, setRangeHours] = useState(168)
  const [resolutionMinutes, setResolutionMinutes] = useState(15)
  const [overlays, setOverlays] = useState<DvcOverlayToggles>(defaultOverlays)
  const [thresholds, setThresholds] = useState<DvcThresholds>(defaultThresholds)
  const [compareMode, setCompareMode] = useState<{ mode: 'none' | 'cage' | 'vcg'; cageId: string }>({
    mode: 'none',
    cageId: '',
  })
  const [customRange, setCustomRange] = useState({
    from: new Date(Date.now() - 48 * 60 * 60 * 1000).toISOString().slice(0, 16),
    to: new Date().toISOString().slice(0, 16),
  })
  const [zoomWindow, setZoomWindow] = useState(100)
  const [zoomStart, setZoomStart] = useState(0)
  const [smoothing, setSmoothing] = useState<'none' | 'light'>('none')
  const [hover, setHover] = useState<{ ts: string; index: number } | null>(null)
  const [pinnedTime, setPinnedTime] = useState<string | null>(null)
  const [selection, setSelection] = useState<TimelineSelection | null>(null)

  const { from, to } = calcRange(rangeHours, customRange)

  const activityQuery = useQuery({
    queryKey: ['dvc-activity', cageId, from, to, resolutionMinutes],
    queryFn: () => getDvcActivity(cageId, { from, to, interval: resolutionMinutes }),
    enabled: Boolean(cageId),
  })

  const environmentQuery = useQuery({
    queryKey: ['dvc-environment', cageId, from, to, resolutionMinutes],
    queryFn: () => getDvcEnvironment(cageId, { from, to, interval: resolutionMinutes }),
    enabled: Boolean(cageId),
  })

  const eventsQuery = useQuery({
    queryKey: ['dvc-events', cageId, from, to],
    queryFn: () => getDvcEvents(cageId, { from, to }),
    enabled: Boolean(cageId) && overlays.events,
  })

  const welfareQuery = useQuery({
    queryKey: ['dvc-welfare', cageId, from, to],
    queryFn: () => getDvcWelfareAlarms(cageId, { from, to }),
    enabled: Boolean(cageId) && welfareEnabled,
  })

  const aiQuery = useQuery({
    queryKey: ['dvc-ai', cageId, from, to, resolutionMinutes],
    queryFn: () => getDvcAiOutput(cageId, { from, to, interval: resolutionMinutes }),
    enabled: Boolean(cageId) && aiEnabled,
  })

  const compareQuery = useQuery({
    queryKey: ['dvc-compare', cageId, compareMode, from, to, resolutionMinutes],
    queryFn: () =>
      getDvcCompare({
        cageIds:
          compareMode.mode === 'vcg'
            ? [cageId, 'CG-RK-101-A-01', 'CG-RK-101-B-03', 'CG-RK-302-A-04']
            : [cageId, compareMode.cageId || 'CG-RK-101-A-01'],
        from,
        to,
        interval: resolutionMinutes,
      }),
    enabled: Boolean(cageId) && compareMode.mode !== 'none',
  })

  const activity = activityQuery.data
  const selectedTime = pinnedTime ?? hover?.ts ?? activity?.ts[activity.ts.length - 1] ?? null

  const contextQuery = useQuery({
    queryKey: ['dvc-context', cageId, selectedTime],
    queryFn: () => getDvcContextSnapshot(cageId, selectedTime ?? undefined),
    enabled: Boolean(cageId) && Boolean(selectedTime),
  })

  const environment = environmentQuery.data
  const events = (eventsQuery.data?.data ?? []).filter((event) => {
    if (!overlays.treatments && event.type === 'feed_change') return false
    return true
  })
  const alarms = welfareQuery.data?.data ?? []

  const windowedActivity = useMemo(() => {
    if (!activity) return null
    const total = activity.ts.length
    if (!total) return activity
    const windowSize = Math.max(2, Math.floor((zoomWindow / 100) * total))
    const startIndex = Math.min(total - windowSize, Math.floor((zoomStart / 100) * total))
    return {
      ...activity,
      ts: activity.ts.slice(startIndex, startIndex + windowSize),
      activity: activity.activity.slice(startIndex, startIndex + windowSize),
    }
  }, [activity, zoomWindow, zoomStart])

  const windowedEnvironment = useMemo(() => {
    if (!environment || !windowedActivity) return environment
    const startTs = windowedActivity.ts[0]
    const endTs = windowedActivity.ts[windowedActivity.ts.length - 1]
    const startIndex = environment.ts.findIndex((ts) => ts >= startTs)
    const endIndex = environment.ts.findIndex((ts) => ts >= endTs)
    if (startIndex === -1 || endIndex === -1) return environment
    return {
      ...environment,
      ts: environment.ts.slice(startIndex, endIndex + 1),
      temp: environment.temp.slice(startIndex, endIndex + 1),
      humidity: environment.humidity.slice(startIndex, endIndex + 1),
      light: environment.light.slice(startIndex, endIndex + 1),
      noise: environment.noise.slice(startIndex, endIndex + 1),
      co2: environment.co2.slice(startIndex, endIndex + 1),
      ammonia: environment.ammonia.slice(startIndex, endIndex + 1),
    }
  }, [environment, windowedActivity])

  const windowBounds = useMemo(() => {
    if (!windowedActivity || !windowedActivity.ts.length) return null
    return {
      start: windowedActivity.ts[0],
      end: windowedActivity.ts[windowedActivity.ts.length - 1],
    }
  }, [windowedActivity])

  const filteredEvents = useMemo(() => {
    if (!windowBounds) return events
    const start = new Date(windowBounds.start).getTime()
    const end = new Date(windowBounds.end).getTime()
    return events.filter((event) => {
      const ts = new Date(event.ts).getTime()
      return ts >= start && ts <= end
    })
  }, [events, windowBounds])

  const filteredAlarms = useMemo(() => {
    if (!windowBounds) return alarms
    const start = new Date(windowBounds.start).getTime()
    const end = new Date(windowBounds.end).getTime()
    return alarms.filter((alarm) => {
      const alarmStart = new Date(alarm.startTs).getTime()
      const alarmEnd = new Date(alarm.endTs ?? alarm.startTs).getTime()
      return alarmEnd >= start && alarmStart <= end
    })
  }, [alarms, windowBounds])

  const qualitySignals = useMemo<QualitySignals | null>(() => {
    if (!activity) return null
    const missingDataPct = computeMissingPct(activity.ts, resolutionMinutes)
    return {
      cageId,
      pid: `urn:os:dvc:quality:${cageId}`,
      missingDataPct,
      lastSeen: activity.ts[activity.ts.length - 1] ?? new Date().toISOString(),
      sensorHealth: missingDataPct > thresholds.missingDataPct ? 'warn' : 'good',
      pipelineVersion: (contextQuery.data as CageContextSnapshot | undefined)?.pipelineVersion ?? 'v3.2.1',
    }
  }, [activity, cageId, resolutionMinutes, thresholds.missingDataPct, contextQuery.data])

  const anomalyReport = useMemo(() => {
    if (!windowedActivity || !windowedEnvironment || !qualitySignals) return null
    return computeAnomalies(windowedActivity, windowedEnvironment, qualitySignals, thresholds)
  }, [windowedActivity, windowedEnvironment, qualitySignals, thresholds])

  if (activityQuery.isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (activityQuery.isError || !activity || !windowedActivity) {
    return (
      <EmptyState
        title="DVC Observatory unavailable"
        description={(activityQuery.error as Error)?.message ?? 'Activity feed missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['dvc-activity', cageId] })}
      />
    )
  }

  const compareSeriesList = useMemo(() => {
    const entries = compareQuery.data?.data?.filter((entry) => entry.cageId !== cageId) ?? []
    if (!windowedActivity) return entries
    const startTs = windowedActivity.ts[0]
    const endTs = windowedActivity.ts[windowedActivity.ts.length - 1]
    return entries.map((series) => {
      const startIndex = series.ts.findIndex((ts) => ts >= startTs)
      const endIndex = series.ts.findIndex((ts) => ts >= endTs)
      if (startIndex === -1 || endIndex === -1) return series
      return {
        ...series,
        ts: series.ts.slice(startIndex, endIndex + 1),
        activity: series.activity.slice(startIndex, endIndex + 1),
      }
    })
  }, [compareQuery.data?.data, cageId, windowedActivity])

  const compareSeries = compareSeriesList[0] ?? null

  const circadianTarget = useMemo(() => {
    if (!activity) return null
    const today = buildCircadianTrace(activity, 24)
    return smoothing === 'light' ? smoothTrace(today, 3) : today
  }, [activity, smoothing])

  const circadianBaseline = useMemo(() => {
    if (!activity) return null
    const baseline = buildCircadianTrace(activity)
    return smoothing === 'light' ? smoothTrace(baseline, 3) : baseline
  }, [activity, smoothing])

  const circadianCompare = useMemo(() => {
    if (compareMode.mode === 'none' || !compareSeriesList.length) return null
    const traces = compareSeriesList.map((series) => buildCircadianTrace(series))
    const mean = meanTrace(traces)
    return smoothing === 'light' ? smoothTrace(mean, 3) : mean
  }, [compareMode.mode, compareSeriesList, smoothing])

  const circadianStd = useMemo(() => {
    if (compareMode.mode !== 'vcg' || !compareSeriesList.length) return null
    const traces = compareSeriesList.map((series) => buildCircadianTrace(series))
    return stdTrace(traces)
  }, [compareMode.mode, compareSeriesList])
  const compareContext = compareQuery.data?.context?.find((context) => context.cageId !== cageId) ?? null

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-slate-400">DVC Observatory</p>
          <h2 className="font-display text-2xl">Cage {cageId}</h2>
          <p className="text-xs text-slate-500">
            Window {formatDateTime(from)} - {formatDateTime(to)}
          </p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => setPinnedTime((prev) => (prev ? null : hover?.ts ?? activity.ts[activity.ts.length - 1]))}
        >
          {pinnedTime ? <PinOff className="h-4 w-4" /> : <Pin className="h-4 w-4" />}
          {pinnedTime ? 'Unpin timestamp' : 'Pin timestamp'}
        </Button>
      </div>

      <DVCObservatoryLayout
        control={
          <ControlTower
            rangeHours={rangeHours}
            onRangeHours={setRangeHours}
            resolutionMinutes={resolutionMinutes}
            onResolutionMinutes={setResolutionMinutes}
            overlays={{
              ...overlays,
              welfare: welfareEnabled ? overlays.welfare : false,
              ai: aiEnabled ? overlays.ai : false,
            }}
            onOverlays={setOverlays}
            thresholds={thresholds}
            onThresholds={setThresholds}
            compareMode={compareMode}
            onCompareMode={setCompareMode}
            customRange={customRange}
            onCustomRange={setCustomRange}
          />
        }
        timeline={
          <Card>
            <CardHeader>
              <CardTitle>Activity timeline</CardTitle>
            </CardHeader>
            <CardContent>
              <ActivityTimelineChart
                activity={windowedActivity}
                compare={compareMode.mode === 'none' ? null : compareSeries ?? null}
                environment={windowedEnvironment}
                events={filteredEvents}
                alarms={filteredAlarms}
                aiOutput={aiEnabled && overlays.ai ? aiQuery.data ?? null : null}
                overlays={{
                  environment: overlays.environment,
                  events: overlays.events,
                  welfare: welfareEnabled && overlays.welfare,
                  ai: aiEnabled && overlays.ai,
                }}
                thresholds={thresholds}
                quality={qualitySignals}
                onHover={(value) => {
                  if (!pinnedTime) {
                    setHover(value)
                  }
                }}
                onSelect={(value) => {
                  setSelection(value)
                  if (value.type === 'event') {
                    setPinnedTime(value.event.ts)
                  }
                  if (value.type === 'alarm') {
                    setPinnedTime(value.alarm.startTs)
                  }
                  if (value.type === 'ai') {
                    setPinnedTime(value.ai.windowStart)
                  }
                }}
                pinnedTime={pinnedTime}
              />
              <div className="mt-4 grid gap-3 text-xs text-slate-500 md:grid-cols-2">
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Zoom window</p>
                  <input
                    type="range"
                    min={30}
                    max={100}
                    value={zoomWindow}
                    onChange={(event) => setZoomWindow(Number(event.target.value))}
                    className="mt-2 w-full"
                  />
                </div>
                <div>
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Window start</p>
                  <input
                    type="range"
                    min={0}
                    max={Math.max(0, 100 - zoomWindow)}
                    value={zoomStart}
                    onChange={(event) => setZoomStart(Number(event.target.value))}
                    className="mt-2 w-full"
                  />
                </div>
              </div>
              {circadianEnabled && circadianTarget ? (
                <div className="mt-6 space-y-3">
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
                  <Circadian24hChart
                    title="Circadian 24h trace"
                    target={circadianTarget}
                    baseline={circadianBaseline}
                    mean={circadianCompare}
                    stdBand={circadianStd}
                  />
                </div>
              ) : null}
            </CardContent>
          </Card>
        }
        insights={
          <div className="space-y-4">
            <ContextSnapshotPanel snapshot={contextQuery.data ?? null} quality={qualitySignals} />
            {welfareEnabled ? <WelfarePanel report={anomalyReport} /> : null}
            {aiEnabled ? <BehavioralOutputsPanel output={aiQuery.data ?? null} /> : null}
            {compareMode.mode !== 'none' && compareContext && contextQuery.data ? (
              <Card>
                <CardHeader>
                  <CardTitle>Compare context</CardTitle>
                </CardHeader>
                <CardContent className="space-y-2 text-sm text-slate-600">
                  <p>Overlay: {compareMode.mode.toUpperCase()}</p>
                  <p>Bedding: {compareContext.beddingMaterial}</p>
                  <p>Sanitary: {compareContext.sanitaryStatus}</p>
                  <p>Pipeline: {compareContext.pipelineVersion}</p>
                  <p className="text-xs text-slate-500">
                    {(() => {
                      const reasons: string[] = []
                      if (compareContext.beddingMaterial !== contextQuery.data.beddingMaterial) {
                        reasons.push('Bedding mismatch')
                      }
                      if (compareContext.sanitaryStatus !== contextQuery.data.sanitaryStatus) {
                        reasons.push('Sanitary mismatch')
                      }
                      if (compareContext.pipelineVersion !== contextQuery.data.pipelineVersion) {
                        reasons.push('Pipeline drift')
                      }
                      return reasons.length ? `Mismatch: ${reasons.join(', ')}.` : 'Metadata aligned with target cage.'
                    })()}
                  </p>
                </CardContent>
              </Card>
            ) : null}
          </div>
        }
      />

      <DetailsDrawer selection={selection} onClose={() => setSelection(null)} />
    </div>
  )
}
