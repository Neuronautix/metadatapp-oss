import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, CheckCircle2, Copy, UserCheck } from 'lucide-react'
import { getAlarms, acknowledgeAlarm, assignAlarm, resolveAlarm } from './alarms.api.ts'
import { getConditionReadings, getConditionThresholds } from '../conditions/conditions.api.ts'
import type { Alarm, AlarmSeverity, AlarmStatus } from '../tecniplast.types.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { formatDateTime } from '@/lib/format.ts'
import { LineChart } from '../conditions/LineChart.tsx'

const PAGE_SIZE = 8

const severityOptions: AlarmSeverity[] = ['critical', 'high', 'medium', 'low']
const statusOptions: AlarmStatus[] = ['active', 'acknowledged', 'resolved']
const scopeOptions = ['facility', 'room', 'rack', 'cage'] as const

const severityVariant = (severity: AlarmSeverity) => {
  if (severity === 'critical') return 'default'
  if (severity === 'high') return 'warning'
  return 'outline'
}

export function AlarmsPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [severity, setSeverity] = useState('')
  const [status, setStatus] = useState('')
  const [scopeType, setScopeType] = useState('')
  const [sensorType, setSensorType] = useState('')
  const [sortBy, setSortBy] = useState('createdAt')
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc')
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const [activeAlarm, setActiveAlarm] = useState<Alarm | null>(null)
  const queryClient = useQueryClient()
  const { toast } = useToast()

  const filters = {
    page,
    pageSize: PAGE_SIZE,
    q: search,
    severity,
    status,
    scopeType,
    sensorType,
    sortBy,
    sortDir,
  }

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['tp-alarms', filters],
    queryFn: () => getAlarms(filters),
  })

  const totalPages = data ? Math.ceil(data.total / data.pageSize) : 1

  const ackMutation = useMutation({
    mutationFn: (id: string) => acknowledgeAlarm(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['tp-alarms'] }),
  })

  const assignMutation = useMutation({
    mutationFn: (id: string) => assignAlarm(id, 'TECH-01'),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['tp-alarms'] }),
  })

  const resolveMutation = useMutation({
    mutationFn: (id: string) => resolveAlarm(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['tp-alarms'] }),
  })

  const toggleSelect = (id: string) => {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((value) => value !== id) : [...prev, id]))
  }

  const bulkAction = async (action: 'ack' | 'resolve') => {
    const targetIds = selectedIds
    if (!targetIds.length) return
    await Promise.all(targetIds.map((id) => (action === 'ack' ? acknowledgeAlarm(id) : resolveAlarm(id))))
    toast({
      title: action === 'ack' ? 'Alarms acknowledged' : 'Alarms resolved',
      description: `${targetIds.length} alarms updated.`,
    })
    setSelectedIds([])
    queryClient.invalidateQueries({ queryKey: ['tp-alarms'] })
  }

  const handleSort = (nextSortBy: string) => {
    if (sortBy === nextSortBy) {
      setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'))
    } else {
      setSortBy(nextSortBy)
      setSortDir('desc')
    }
  }

  const alarms = data?.data ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Alarms Center"
        subtitle="Acknowledge, assign, and resolve facility alarms in real time."
        actions={
          <Button
            variant="outline"
            onClick={() => {
              setSearch('')
              setSeverity('')
              setStatus('')
              setScopeType('')
              setSensorType('')
              setSortBy('createdAt')
              setSortDir('desc')
              setPage(1)
            }}
          >
            Reset filters
          </Button>
        }
      />

      <Card className="space-y-5">
        <CardContent className="pt-6">
          <div className="flex flex-wrap items-center gap-3">
            <div className="flex w-full items-center gap-2 rounded-full border border-line bg-surface px-3 py-2 text-sm text-slate-500 md:max-w-xs">
              <Input
                value={search}
                onChange={(event) => {
                  setSearch(event.target.value)
                  setPage(1)
                }}
                placeholder="Search alarms"
                className="h-7 border-0 bg-transparent p-0 text-sm focus-visible:ring-0"
              />
            </div>
            <select
              className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
              value={severity}
              onChange={(event) => {
                setSeverity(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All severity</option>
              {severityOptions.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
            <select
              className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
              value={status}
              onChange={(event) => {
                setStatus(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All status</option>
              {statusOptions.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
            <select
              className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
              value={scopeType}
              onChange={(event) => {
                setScopeType(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All scope</option>
              {scopeOptions.map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
            <select
              className="rounded-full border border-line bg-white px-3 py-2 text-sm text-slate-600"
              value={sensorType}
              onChange={(event) => {
                setSensorType(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All sensors</option>
              {['temperature', 'humidity', 'pressure', 'co2', 'light', 'noise'].map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          </div>

          {selectedIds.length ? (
            <div className="mt-4 flex items-center gap-2 text-sm text-slate-500">
              <span>{selectedIds.length} selected</span>
              <Button size="sm" variant="outline" onClick={() => bulkAction('ack')}>
                Acknowledge
              </Button>
              <Button size="sm" variant="outline" onClick={() => bulkAction('resolve')}>
                Resolve
              </Button>
            </div>
          ) : null}
        </CardContent>

        {isLoading ? (
          <div className="space-y-3 px-6 pb-6">
            {Array.from({ length: 6 }).map((_, index) => (
              <Skeleton key={index} className="h-12 w-full" />
            ))}
          </div>
        ) : isError ? (
          <div className="px-6 pb-6">
            <EmptyState
              title="Alarm stream unavailable"
              description={(error as Error)?.message}
              actionLabel="Retry"
              onAction={() => queryClient.invalidateQueries({ queryKey: ['tp-alarms'] })}
            />
          </div>
        ) : alarms.length === 0 ? (
          <div className="px-6 pb-6">
            <EmptyState title="No alarms" description="No alarms match the current filters." />
          </div>
        ) : (
          <div className="grid gap-6 px-6 pb-6 lg:grid-cols-[3fr_2fr]">
            <div>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead></TableHead>
                    <TableHead onClick={() => handleSort('severity')} className="cursor-pointer">
                      Severity
                    </TableHead>
                    <TableHead>Scope</TableHead>
                    <TableHead onClick={() => handleSort('status')} className="cursor-pointer">
                      Status
                    </TableHead>
                    <TableHead>Sensor</TableHead>
                    <TableHead onClick={() => handleSort('createdAt')} className="cursor-pointer">
                      Created
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {alarms.map((alarm) => (
                    <TableRow
                      key={alarm.id}
                      className={activeAlarm?.id === alarm.id ? 'bg-surface-2' : ''}
                      onClick={() => setActiveAlarm(alarm)}
                    >
                      <TableCell>
                        <input
                          type="checkbox"
                          checked={selectedIds.includes(alarm.id)}
                          onChange={() => toggleSelect(alarm.id)}
                          onClick={(event) => event.stopPropagation()}
                        />
                      </TableCell>
                      <TableCell>
                        <Badge variant={severityVariant(alarm.severity)}>{alarm.severity}</Badge>
                      </TableCell>
                      <TableCell>
                        <div className="text-sm text-slate-700">{alarm.scopeLabel}</div>
                        <div className="text-xs text-slate-500">{alarm.scopeType}</div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">{alarm.status}</Badge>
                      </TableCell>
                      <TableCell className="text-xs text-slate-500">{alarm.sensorType}</TableCell>
                      <TableCell className="text-xs text-slate-500">{formatDateTime(alarm.createdAt)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              <div className="mt-4 flex items-center justify-between text-sm text-slate-500">
                <span>
                  Page {page} of {totalPages}
                </span>
                <div className="flex items-center gap-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                    disabled={page === 1}
                  >
                    Previous
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setPage((prev) => Math.min(totalPages, prev + 1))}
                    disabled={page === totalPages}
                  >
                    Next
                  </Button>
                </div>
              </div>
            </div>

            <AlarmDetailPanel
              alarm={activeAlarm ?? alarms[0]}
              onAcknowledge={(id) => ackMutation.mutate(id)}
              onAssign={(id) => assignMutation.mutate(id)}
              onResolve={(id) => resolveMutation.mutate(id)}
            />
          </div>
        )}
      </Card>
    </div>
  )
}

function AlarmDetailPanel({
  alarm,
  onAcknowledge,
  onAssign,
  onResolve,
}: {
  alarm?: Alarm
  onAcknowledge: (id: string) => void
  onAssign: (id: string) => void
  onResolve: (id: string) => void
}) {
  const { toast } = useToast()

  const thresholdsQuery = useQuery({
    queryKey: ['tp-conditions-thresholds', alarm?.scopeType, alarm?.scopeId],
    queryFn: () => getConditionThresholds(alarm?.scopeType ?? '', alarm?.scopeId ?? ''),
    enabled: Boolean(alarm?.scopeId),
  })

  const readingsQuery = useQuery({
    queryKey: ['tp-conditions-readings', alarm?.scopeType, alarm?.scopeId, alarm?.sensorType],
    queryFn: () => {
      const to = new Date()
      const from = new Date(to.getTime() - 24 * 3600000)
      return getConditionReadings({
        scopeType: alarm?.scopeType ?? 'cage',
        scopeId: alarm?.scopeId ?? '',
        metric: alarm?.sensorType ?? 'temperature',
        from: from.toISOString(),
        to: to.toISOString(),
        interval: '30m',
      })
    },
    enabled: Boolean(alarm?.scopeId),
  })

  const metricThreshold = useMemo(() => {
    return (thresholdsQuery.data ?? []).find((item) => item.metric === alarm?.sensorType)
  }, [alarm?.sensorType, thresholdsQuery.data])

  if (!alarm) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Alarm detail</CardTitle>
          <CardDescription>Select an alarm to view details.</CardDescription>
        </CardHeader>
      </Card>
    )
  }

  return (
    <Card className="h-full">
      <CardHeader>
        <CardTitle>Alarm detail</CardTitle>
        <CardDescription>{alarm.scopeLabel}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4 text-sm text-slate-600">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Message</p>
          <p className="font-semibold text-slate-700">{alarm.message}</p>
        </div>
        <div className="grid gap-3 md:grid-cols-2">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Severity</p>
            <p className="font-semibold text-slate-700">{alarm.severity}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Status</p>
            <p className="font-semibold text-slate-700">{alarm.status}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Sensor</p>
            <p className="font-semibold text-slate-700">{alarm.sensorType}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Correlation</p>
            <button
              className="text-xs text-slate-500"
              onClick={() => {
                navigator.clipboard.writeText(alarm.correlationId)
                toast({ title: 'Copied', description: alarm.correlationId })
              }}
            >
              <Copy className="mr-1 inline h-3 w-3" />
              {alarm.correlationId}
            </button>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button size="sm" variant="outline" onClick={() => onAcknowledge(alarm.id)}>
            <UserCheck className="h-4 w-4" />
            Acknowledge
          </Button>
          <Button size="sm" variant="outline" onClick={() => onAssign(alarm.id)}>
            <AlertTriangle className="h-4 w-4" />
            Assign
          </Button>
          <Button size="sm" variant="outline" onClick={() => onResolve(alarm.id)}>
            <CheckCircle2 className="h-4 w-4" />
            Resolve
          </Button>
        </div>

        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Timeline</p>
          <div className="mt-2 space-y-2 text-xs text-slate-500">
            <div className="flex items-center justify-between">
              <span>Alarm raised</span>
              <span>{formatDateTime(alarm.createdAt)}</span>
            </div>
            {alarm.acknowledgedAt ? (
              <div className="flex items-center justify-between">
                <span>Acknowledged</span>
                <span>{formatDateTime(alarm.acknowledgedAt)}</span>
              </div>
            ) : null}
            {alarm.resolvedAt ? (
              <div className="flex items-center justify-between">
                <span>Resolved</span>
                <span>{formatDateTime(alarm.resolvedAt)}</span>
              </div>
            ) : null}
          </div>
        </div>

        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Readings context</p>
          {readingsQuery.isLoading ? (
            <Skeleton className="mt-3 h-32 w-full" />
          ) : readingsQuery.isError ? (
            <EmptyState
              title="Readings unavailable"
              description={readingsQuery.error?.message ?? 'Unable to load readings.'}
            />
          ) : readingsQuery.data ? (
            <LineChart
              points={readingsQuery.data.points}
              min={metricThreshold?.min}
              max={metricThreshold?.max}
              unit={metricThreshold?.unit}
            />
          ) : null}
        </div>
      </CardContent>
    </Card>
  )
}
