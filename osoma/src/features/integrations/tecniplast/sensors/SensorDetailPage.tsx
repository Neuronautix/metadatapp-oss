import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft } from 'lucide-react'
import { getSensor } from './sensors.api.ts'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { formatDateTime } from '@/lib/format.ts'

export function SensorDetailPage() {
  const { sensorId } = useParams()
  const queryClient = useQueryClient()

  const sensorQuery = useQuery({
    queryKey: ['tp-sensor', sensorId],
    queryFn: () => getSensor(sensorId ?? ''),
    enabled: Boolean(sensorId),
  })

  if (sensorQuery.isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (sensorQuery.isError || !sensorQuery.data) {
    return (
      <EmptyState
        title="Sensor unavailable"
        description={sensorQuery.error?.message ?? 'Sensor record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['tp-sensor', sensorId] })}
      />
    )
  }

  const sensor = sensorQuery.data

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Button variant="ghost" size="sm" asChild>
          <Link to="/tp/sensors">
            <ArrowLeft className="h-4 w-4" />
            Back
          </Link>
        </Button>
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Sensor</p>
          <h2 className="font-display text-2xl">{sensor.id}</h2>
          <p className="text-sm text-slate-500">{sensor.scopeLabel}</p>
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Sensor health</CardTitle>
            <CardDescription>Status and calibration.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <p>Status: {sensor.status}</p>
            <p>Metric: {sensor.metric}</p>
            <p>Battery: {sensor.battery}%</p>
            <p>Calibration due: {formatDateTime(sensor.calibrationDueAt)}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Activity</CardTitle>
            <CardDescription>Last seen telemetry.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <p>Last seen: {formatDateTime(sensor.lastSeen)}</p>
            <p>Scope type: {sensor.scopeType}</p>
            <p>Scope ID: {sensor.scopeId}</p>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
