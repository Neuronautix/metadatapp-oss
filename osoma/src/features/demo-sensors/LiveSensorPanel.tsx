import { useDeferredValue, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, Clock3, Droplets, Gauge, Thermometer, Wifi, WifiOff } from 'lucide-react'
import {
  CartesianGrid,
  Legend,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import { Card } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDateTime } from '@/lib/format.ts'
import { getLiveSensorHistory, getLiveSensorSnapshot } from './demo-sensors.api.ts'
import type {
  LiveSensorConnectionState,
  LiveSensorHistoryPoint,
  LiveSensorSnapshot,
  LiveSensorThresholdMetric,
} from './demo-sensors.types.ts'

const metricLabels: Record<LiveSensorThresholdMetric, { label: string; unitFallback: string; icon: typeof Thermometer }> = {
  temperature: { label: 'Temperature', unitFallback: '°C', icon: Thermometer },
  humidity: { label: 'Humidity', unitFallback: '%', icon: Droplets },
  pressure: { label: 'Pressure', unitFallback: 'hPa', icon: Gauge },
}

const connectionBadgeVariant = (state: LiveSensorConnectionState) => {
  if (state === 'connected') return 'success' as const
  if (state === 'stale' || state === 'waiting') return 'warning' as const
  if (state === 'unreachable') return 'critical' as const
  return 'outline' as const
}

const thresholdBadgeVariant = (status: LiveSensorSnapshot['threshold']['overallStatus']) => {
  if (status === 'critical') return 'critical' as const
  if (status === 'warning') return 'warning' as const
  if (status === 'normal') return 'success' as const
  return 'outline' as const
}

const connectionLabel = (state: LiveSensorConnectionState) => {
  switch (state) {
    case 'connected':
      return 'Connected'
    case 'stale':
      return 'Stale'
    case 'waiting':
      return 'Waiting for data'
    case 'unreachable':
      return 'API unreachable'
    default:
      return 'Disabled'
  }
}

const renderConnectionMessage = (data: LiveSensorSnapshot) => {
  if (data.connectionState === 'unreachable') {
    return {
      title: 'Sensor API unreachable',
      description: data.health.message ?? 'Metadatapp could not reach the local Sovereign Sensor Agent API.',
      tone: 'critical' as const,
    }
  }

  if (data.connectionState === 'waiting') {
    return {
      title: 'Waiting for first observation',
      description: data.health.message ?? 'The Raspberry Pi sensor agent is online but has not produced an observation yet.',
      tone: 'warning' as const,
    }
  }

  if (data.connectionState === 'stale') {
    return {
      title: 'Sensor data is stale',
      description: data.health.message ?? 'The latest observation is older than expected.',
      tone: 'warning' as const,
    }
  }

  if (data.connectionState === 'disabled') {
    return {
      title: 'Sensor integration disabled',
      description: data.health.message ?? 'Configure the Sovereign Sensor Agent base URL to enable live readings.',
      tone: 'outline' as const,
    }
  }

  return null
}

type LiveSensorPanelProps = {
  title?: string
  subtitle?: string
  refetchIntervalMs?: number
}

const historyWindows = [
  { label: '30m', rangeMinutes: 30, maxPoints: 60 },
  { label: '3h', rangeMinutes: 180, maxPoints: 180 },
  { label: '12h', rangeMinutes: 720, maxPoints: 240 },
] as const

const chartTick = (value: string) =>
  new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))

export function LiveSensorPanel({
  title = 'Live sensor demo',
  subtitle = 'Read-only Raspberry Pi observations proxied by Metadatapp.',
  refetchIntervalMs = 5000,
}: LiveSensorPanelProps) {
  const [selectedWindow, setSelectedWindow] = useState<(typeof historyWindows)[number]>(historyWindows[1])
  const deferredWindow = useDeferredValue(selectedWindow)

  const { data, isLoading, isError, error, dataUpdatedAt } = useQuery({
    queryKey: ['demo-sensors', 'snapshot'],
    queryFn: getLiveSensorSnapshot,
    refetchInterval: refetchIntervalMs,
  })

  const historyQuery = useQuery({
    queryKey: ['demo-sensors', 'history', deferredWindow.rangeMinutes, deferredWindow.maxPoints],
    queryFn: () => getLiveSensorHistory(deferredWindow),
    refetchInterval: refetchIntervalMs,
  })

  const history = historyQuery.data?.points ?? []
  const chartData = history.map((point: LiveSensorHistoryPoint) => ({
    timestamp: point.observedAt,
    temperatureC: point.temperatureC,
    humidityPct: point.humidityPct,
  }))

  if (isLoading) {
    return (
      <Card className="space-y-4">
        <div className="space-y-2">
          <Skeleton className="h-6 w-48" />
          <Skeleton className="h-4 w-72" />
        </div>
        <div className="grid gap-4 md:grid-cols-3">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-24 w-full" />
          ))}
        </div>
      </Card>
    )
  }

  if (isError) {
    return (
      <Card>
        <EmptyState title="Sovereign Sensor Agent unavailable" description={(error as Error).message} />
      </Card>
    )
  }

  if (!data) {
    return null
  }

  const updatedAt = dataUpdatedAt ? formatDateTime(new Date(dataUpdatedAt).toJSON()) : null
  const connectionMessage = renderConnectionMessage(data)
  const observationTimestamp = data.latest.observedAt ?? data.health.lastObservationAt
  const metrics: LiveSensorThresholdMetric[] = data.latest.pressureHpa === null && !data.threshold.thresholdStatus.pressure.available
    ? ['temperature', 'humidity']
    : ['temperature', 'humidity', 'pressure']
  const temperatureValues = chartData
    .map((point) => point.temperatureC)
    .filter((value): value is number => typeof value === 'number')
  const humidityValues = chartData
    .map((point) => point.humidityPct)
    .filter((value): value is number => typeof value === 'number')
  const temperatureDomain = temperatureValues.length
    ? [Math.min(...temperatureValues) - 1, Math.max(...temperatureValues) + 1]
    : ['auto', 'auto']
  const humidityDomain = humidityValues.length
    ? [Math.min(...humidityValues) - 5, Math.max(...humidityValues) + 5]
    : ['auto', 'auto']

  return (
    <Card className="space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="font-display text-lg font-semibold text-slate-900">{title}</h3>
          <p className="mt-1 text-sm text-slate-500">{subtitle}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={connectionBadgeVariant(data.connectionState)}>
            {connectionLabel(data.connectionState)}
          </Badge>
          <Badge variant={thresholdBadgeVariant(data.threshold.overallStatus)} className="capitalize">
            Thresholds: {data.threshold.overallStatus}
          </Badge>
        </div>
      </div>

      {connectionMessage ? (
        <div
          className={
            connectionMessage.tone === 'critical'
              ? 'flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800'
              : connectionMessage.tone === 'warning'
                ? 'flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800'
                : 'flex items-start gap-3 rounded-2xl border border-line bg-surface-2 px-4 py-3 text-sm text-slate-700'
          }
        >
          {connectionMessage.tone === 'critical' ? (
            <WifiOff className="mt-0.5 h-4 w-4 shrink-0" />
          ) : connectionMessage.tone === 'warning' ? (
            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
          ) : (
            <Wifi className="mt-0.5 h-4 w-4 shrink-0" />
          )}
          <div>
            <p className="font-medium">{connectionMessage.title}</p>
            <p>{connectionMessage.description}</p>
          </div>
        </div>
      ) : null}

      {data.threshold.activeAlarms.length > 0 ? (
        <div className="space-y-2">
          {data.threshold.activeAlarms.map((alarm) => (
            <div
              key={alarm.code}
              className="flex items-start gap-3 rounded-2xl border border-line bg-surface-2 px-4 py-3 text-sm text-slate-700"
            >
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
              <div>
                <p className="font-medium capitalize">{alarm.code.replaceAll('_', ' ')}</p>
                <p className="text-slate-500">{alarm.message}</p>
              </div>
            </div>
          ))}
        </div>
      ) : null}

      <div className={`grid gap-4 ${metrics.length === 3 ? 'md:grid-cols-3' : 'md:grid-cols-2'}`}>
        {metrics.map((metric) => {
          const config = metricLabels[metric]
          const Icon = config.icon
          const threshold = data.threshold.thresholdStatus[metric]
          const value =
            metric === 'temperature' ? data.latest.temperatureC
              : metric === 'humidity' ? data.latest.humidityPct
                : data.latest.pressureHpa

          return (
            <div key={metric} className="rounded-2xl border border-line bg-surface-2 p-4">
              <div className="flex items-center justify-between">
                <div className="rounded-xl bg-white p-2 text-slate-600 shadow-sm">
                  <Icon className="h-5 w-5" />
                </div>
                <Badge variant={threshold.alarm ? 'warning' : threshold.status === 'unavailable' ? 'outline' : 'success'}>
                  {threshold.status}
                </Badge>
              </div>
              <p className="mt-4 text-sm text-slate-500">{config.label}</p>
              <p className="mt-1 text-2xl font-display font-semibold text-slate-900">
                {typeof value === 'number' ? value.toFixed(1) : '—'}{' '}
                <span className="text-sm font-medium text-slate-400">{threshold.unit || config.unitFallback}</span>
              </p>
            </div>
          )
        })}
      </div>

      <div className="space-y-4 rounded-2xl border border-line bg-white/80 p-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h4 className="text-sm font-medium text-slate-900">Live environment timeline</h4>
            <p className="mt-1 text-sm text-slate-500">Temperature and humidity update automatically and remain explorable with hover details.</p>
          </div>
          <div className="flex items-center gap-2 rounded-full border border-line bg-surface px-1 py-1">
            {historyWindows.map((window) => (
              <Button
                key={window.label}
                size="sm"
                variant={selectedWindow.label === window.label ? 'default' : 'ghost'}
                onClick={() => setSelectedWindow(window)}
              >
                {window.label}
              </Button>
            ))}
          </div>
        </div>

        {historyQuery.isLoading ? (
          <Skeleton className="h-72 w-full" />
        ) : historyQuery.isError ? (
          <EmptyState title="Sensor timeline unavailable" description={(historyQuery.error as Error).message} />
        ) : chartData.length === 0 ? (
          <EmptyState title="No timeline data yet" description="The live sensor feed has not produced a history series yet." />
        ) : (
          <div className="space-y-3">
            <div className="h-72 w-full">
              <ResponsiveContainer width="100%" height="100%" minWidth={320} minHeight={288}>
                <LineChart data={chartData} margin={{ top: 8, right: 18, left: 6, bottom: 8 }}>
                  <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" />
                  <XAxis dataKey="timestamp" tickFormatter={chartTick} minTickGap={24} stroke="#64748b" />
                  <YAxis yAxisId="temperature" orientation="left" domain={temperatureDomain as [number, number]} stroke="#ea580c" width={56} />
                  <YAxis yAxisId="humidity" orientation="right" domain={humidityDomain as [number, number]} stroke="#0284c7" width={56} />
                  <Tooltip
                    labelFormatter={(value) => formatDateTime(String(value))}
                    formatter={(value, name) => [
                      typeof value === 'number' ? value.toFixed(1) : '—',
                      name === 'temperatureC' ? 'Temperature (°C)' : 'Humidity (%)',
                    ]}
                  />
                  <Legend formatter={(value) => value === 'temperatureC' ? 'Temperature' : 'Humidity'} />
                  <Line yAxisId="temperature" type="monotone" dataKey="temperatureC" stroke="#ea580c" strokeWidth={2.5} dot={false} activeDot={{ r: 4 }} />
                  <Line yAxisId="humidity" type="monotone" dataKey="humidityPct" stroke="#0284c7" strokeWidth={2.5} dot={false} activeDot={{ r: 4 }} />
                </LineChart>
              </ResponsiveContainer>
            </div>

            <div className="grid gap-3 text-sm text-slate-500 md:grid-cols-3">
              <div className="rounded-2xl border border-line bg-surface-2 px-4 py-3">
                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Window</div>
                <div className="mt-1 text-slate-700">Last {selectedWindow.label}</div>
              </div>
              <div className="rounded-2xl border border-line bg-surface-2 px-4 py-3">
                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Samples</div>
                <div className="mt-1 text-slate-700">{chartData.length} points</div>
              </div>
              <div className="rounded-2xl border border-line bg-surface-2 px-4 py-3">
                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Latest point</div>
                <div className="mt-1 text-slate-700">{historyQuery.data?.observedAt ? formatDateTime(historyQuery.data.observedAt) : 'Waiting for data'}</div>
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-2xl border border-line p-4">
          <div className="flex items-center gap-2 text-sm font-medium text-slate-900">
            <Clock3 className="h-4 w-4 text-slate-400" />
            Last observation
          </div>
          <p className="mt-2 text-sm text-slate-500">
            {observationTimestamp ? formatDateTime(observationTimestamp) : 'No live observation available yet'}
          </p>
        </div>
        <div className="rounded-2xl border border-line p-4">
          <div className="flex items-center gap-2 text-sm font-medium text-slate-900">
            <Clock3 className="h-4 w-4 text-slate-400" />
            Freshness
          </div>
          <p className="mt-2 text-sm text-slate-500">
            {data.health.freshnessAgeSeconds === null ? 'Waiting for sensor data' : `${data.health.freshnessAgeSeconds}s old`}
          </p>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <div className="rounded-2xl border border-line p-4">
          <div className="text-sm font-medium text-slate-900">Sensor source</div>
          <p className="mt-2 text-sm text-slate-500">
            {data.latest.sensorId ?? data.threshold.sensorId ?? 'Unknown sensor'}
            {(data.latest.sourcePort ?? data.threshold.sourcePort) ? ` · ${data.latest.sourcePort ?? data.threshold.sourcePort}` : ''}
          </p>
        </div>
        <div className="rounded-2xl border border-line p-4">
          <div className="text-sm font-medium text-slate-900">Last refresh</div>
          <p className="mt-2 text-sm text-slate-500">{updatedAt ?? 'Waiting for refresh'}</p>
        </div>
      </div>
    </Card>
  )
}
