import { useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Download } from 'lucide-react'
import { getConditionReadings, getConditionSnapshot, getConditionThresholds, updateConditionThresholds } from './conditions.api.ts'
import type { ConditionMetric, ConditionSnapshotMetric, ConditionThreshold } from '../tecniplast.types.ts'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { exportCsv } from '@/lib/export.ts'
import { LineChart } from './LineChart.tsx'
import { useToast } from '@/components/ui/toast.tsx'

const metricLabels: Record<ConditionMetric, string> = {
  temperature: 'Temperature',
  humidity: 'Humidity',
  pressure: 'Pressure',
  co2: 'CO2',
  light: 'Light',
  noise: 'Noise',
}

const ranges = [
  { label: '24h', value: '24h', minutes: 60 * 24, interval: '30m' },
  { label: '7d', value: '7d', minutes: 60 * 24 * 7, interval: '2h' },
  { label: '30d', value: '30d', minutes: 60 * 24 * 30, interval: '6h' },
] as const

export function ConditionsPanel({ scopeType, scopeId }: { scopeType: string; scopeId: string }) {
  const [metric, setMetric] = useState<ConditionMetric>('temperature')
  const [range, setRange] = useState<(typeof ranges)[number]['value']>('24h')
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const snapshot = useQuery({
    queryKey: ['tp-conditions-snapshot', scopeType, scopeId],
    queryFn: () => getConditionSnapshot(scopeType, scopeId),
  })

  const thresholdsQuery = useQuery({
    queryKey: ['tp-conditions-thresholds', scopeType, scopeId],
    queryFn: () => getConditionThresholds(scopeType, scopeId),
  })
  const [draftThresholds, setDraftThresholds] = useState<ConditionThreshold[]>([])

  useEffect(() => {
    if (thresholdsQuery.data) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setDraftThresholds(thresholdsQuery.data)
    }
  }, [thresholdsQuery.data])

  const activeRange = ranges.find((item) => item.value === range) ?? ranges[0]
  const to = new Date()
  const from = new Date(to.getTime() - activeRange.minutes * 60000)

  const readings = useQuery({
    queryKey: ['tp-conditions-readings', scopeType, scopeId, metric, range],
    queryFn: () =>
      getConditionReadings({
        scopeType,
        scopeId,
        metric,
        from: from.toISOString(),
        to: to.toISOString(),
        interval: activeRange.interval,
      }),
    enabled: Boolean(scopeId),
  })

  const outOfRangeMetrics = useMemo(() => {
    return (snapshot.data?.metrics ?? []).filter((item) => item.status === 'out-of-range')
  }, [snapshot.data?.metrics])

  const metricThreshold = draftThresholds.find((item) => item.metric === metric)

  const handleThresholdChange = (metric: ConditionMetric, key: 'min' | 'max', value: number) => {
    setDraftThresholds((prev) =>
      prev.map((threshold) => (threshold.metric === metric ? { ...threshold, [key]: value } : threshold))
    )
  }

  const handleThresholdSave = () => {
    updateConditionThresholds(scopeType, scopeId, draftThresholds)
      .then(() => {
        queryClient.invalidateQueries({ queryKey: ['tp-conditions-thresholds', scopeType, scopeId] })
        queryClient.invalidateQueries({ queryKey: ['tp-conditions-snapshot', scopeType, scopeId] })
        toast({ title: 'Thresholds updated', description: 'Alert ranges recalibrated.' })
      })
      .catch(() => {
        toast({ title: 'Update failed', description: 'Unable to save thresholds.' })
      })
  }

  const exportReadings = () => {
    if (!readings.data?.points.length) return
    const rows = readings.data.points.map((point) => ({
      timestamp: point.at,
      value: point.value,
    }))
    exportCsv(rows, `${scopeType}-${scopeId}-${metric}-readings`)
  }

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Latest conditions</CardTitle>
          <CardDescription>Live snapshot from environmental sensors.</CardDescription>
        </CardHeader>
        <CardContent>
          {snapshot.isLoading ? (
            <div className="grid gap-3 md:grid-cols-2">
              {Array.from({ length: 4 }).map((_, index) => (
                <Skeleton key={index} className="h-12 w-full" />
              ))}
            </div>
          ) : snapshot.isError ? (
            <EmptyState
              title="Snapshot unavailable"
              description={snapshot.error?.message ?? 'Unable to load conditions.'}
              actionLabel="Retry"
              onAction={() => snapshot.refetch()}
            />
          ) : (
            <div className="grid gap-3 md:grid-cols-2">
              {(snapshot.data?.metrics ?? []).map((item) => (
                <MetricCard key={item.metric} metric={item} />
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Trends</CardTitle>
          <CardDescription>Historical readings with thresholds.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-2">
            <select
              className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
              value={metric}
              onChange={(event) => setMetric(event.target.value as ConditionMetric)}
            >
              {Object.entries(metricLabels).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
            {ranges.map((item) => (
              <Button
                key={item.value}
                size="sm"
                variant={range === item.value ? 'default' : 'outline'}
                onClick={() => setRange(item.value)}
              >
                {item.label}
              </Button>
            ))}
            <Button size="sm" variant="ghost" onClick={exportReadings} disabled={!readings.data?.points.length}>
              <Download className="h-4 w-4" />
              Export CSV
            </Button>
          </div>

          {readings.isLoading ? (
            <Skeleton className="h-40 w-full" />
          ) : readings.isError ? (
            <EmptyState
              title="Trends unavailable"
              description={readings.error?.message ?? 'Unable to load readings.'}
              actionLabel="Retry"
              onAction={() => readings.refetch()}
            />
          ) : readings.data ? (
            <LineChart
              points={readings.data.points}
              min={metricThreshold?.min}
              max={metricThreshold?.max}
              unit={metricThreshold?.unit}
            />
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Thresholds</CardTitle>
          <CardDescription>Out-of-range detection settings.</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {thresholdsQuery.isLoading ? (
            <Skeleton className="h-20 w-full" />
          ) : thresholdsQuery.isError ? (
            <EmptyState
              title="Thresholds unavailable"
              description={thresholdsQuery.error?.message ?? 'Unable to load thresholds.'}
              actionLabel="Retry"
              onAction={() => thresholdsQuery.refetch()}
            />
          ) : (
            <div className="space-y-3">
              {draftThresholds.map((threshold) => (
                <div key={threshold.metric} className="grid gap-2 md:grid-cols-[140px_1fr_1fr_60px]">
                  <div className="text-sm font-medium text-slate-700">{metricLabels[threshold.metric]}</div>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-slate-500">Min</span>
                    <Input
                      value={threshold.min}
                      onChange={(event) =>
                        handleThresholdChange(threshold.metric, 'min', Number(event.target.value))
                      }
                      className="h-8"
                      type="number"
                    />
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-slate-500">Max</span>
                    <Input
                      value={threshold.max}
                      onChange={(event) =>
                        handleThresholdChange(threshold.metric, 'max', Number(event.target.value))
                      }
                      className="h-8"
                      type="number"
                    />
                  </div>
                  <div className="text-xs text-slate-500">{threshold.unit}</div>
                </div>
              ))}
              <div>
                <Button size="sm" onClick={handleThresholdSave} disabled={!draftThresholds.length}>
                  Save thresholds
                </Button>
              </div>
            </div>
          )}

          {outOfRangeMetrics.length ? (
            <div className="rounded-2xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
              <div className="flex items-center gap-2 font-medium">
                <AlertTriangle className="h-4 w-4" />
                Out-of-range signals detected
              </div>
              <ul className="mt-2 text-xs text-amber-700">
                {outOfRangeMetrics.map((item) => (
                  <li key={item.metric}>
                    {metricLabels[item.metric]} at {item.value} {item.unit} (range {item.min}-{item.max})
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  )
}

function MetricCard({ metric }: { metric: ConditionSnapshotMetric }) {
  return (
    <div className="rounded-2xl border border-line bg-white p-4 text-sm">
      <div className="flex items-center justify-between">
        <div className="text-xs uppercase tracking-[0.2em] text-slate-400">{metric.metric}</div>
        <span className={metric.status === 'out-of-range' ? 'text-amber-600' : 'text-emerald-600'}>
          {metric.status === 'out-of-range' ? 'Alert' : 'OK'}
        </span>
      </div>
      <div className="mt-2 text-2xl font-semibold text-slate-900">
        {metric.value} {metric.unit}
      </div>
      <div className="mt-2 text-xs text-slate-500">
        Range {metric.min}-{metric.max} {metric.unit}
      </div>
    </div>
  )
}
