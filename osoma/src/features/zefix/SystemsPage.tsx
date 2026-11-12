import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
    CartesianGrid,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { RestrictedActions } from '@/components/RestrictedActions.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import {
    createZefishEnvironmentalObservation,
    getZefishEnvironmentalObservations,
    getZefishSystems,
} from '@/domain/zefish/zefish.api.ts'
import type { ZefishSystemSnapshot } from '@/domain/zefish/zefish.types.ts'
import { buildEnvironmentalTrend, formatMetric, toObservationTimestamp, sortRecentObservations } from '@/domain/zefish/zefish.utils.ts'
import { formatDateTime } from '@/lib/format.ts'
import { EnvironmentalObservationForm, type EnvironmentalObservationFormValues } from '@/features/zefix/EnvironmentalObservationForm.tsx'
import { useToast } from '@/components/ui/toast.tsx'

const filtrationBadge = (status: 'OK' | 'MAINTENANCE' | 'FAILURE') => {
    if (status === 'OK') return 'success'
    if (status === 'MAINTENANCE') return 'warning'
    return 'critical'
}

const observationScopeLabel = (system: ZefishSystemSnapshot | undefined) => {
    if (!system) return 'Selected system'
    return `${system.systemID} · ${system.roomName}`
}

export function SystemsPage() {
    const [selectedSystemID, setSelectedSystemID] = useState('')
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const { role } = useRole()
    const allowEdit = canEdit(role)

    const systemsQuery = useQuery({
        queryKey: ['zefix', 'systems'],
        queryFn: getZefishSystems,
    })

    const systems = systemsQuery.data ?? []

    useEffect(() => {
        if (!selectedSystemID && systems.length) {
            setSelectedSystemID(systems[0].systemID)
        }
    }, [selectedSystemID, systems])

    const selectedSystem = useMemo(
        () => systems.find((system) => system.systemID === selectedSystemID) ?? systems[0],
        [selectedSystemID, systems]
    )

    const observationsQuery = useQuery({
        queryKey: ['zefix', 'systems', selectedSystem?.systemID ?? selectedSystemID, 'observations'],
        queryFn: () =>
            getZefishEnvironmentalObservations({
                scopeType: 'system',
                scopeID: selectedSystem?.systemID ?? selectedSystemID,
            }),
        enabled: Boolean(selectedSystem?.systemID ?? selectedSystemID),
    })

    const observations = observationsQuery.data ?? []
    const temperatureTrend = useMemo(
        () =>
            buildEnvironmentalTrend(observations).filter(
                (point) => typeof point.temperature === 'number' || typeof point.pH === 'number'
            ),
        [observations]
    )
    const recentObservations = useMemo(() => sortRecentObservations(observations).slice(0, 8), [observations])

    const observationMutation = useMutation({
        mutationFn: async (values: EnvironmentalObservationFormValues) => {
            const system = selectedSystem ?? systems[0]
            if (!system) {
                throw new Error('No system is available for observation entry.')
            }

            await createZefishEnvironmentalObservation({
                scopeType: 'system',
                scopeID: system.systemID,
                metric: values.metric,
                value: values.value,
                timestamp: toObservationTimestamp(values.timestamp),
                source: 'manual',
                notes: values.notes?.trim() ? values.notes.trim() : null,
            })
        },
        onSuccess: async () => {
            toast({
                title: 'Observation recorded',
                description: 'System charts and room aggregates will refresh from persisted observations.',
                variant: 'success',
            })
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['zefix', 'systems'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'systems', selectedSystem?.systemID ?? selectedSystemID, 'observations'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'rooms'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'overview'] }),
            ])
        },
        onError: (error) => {
            toast({
                title: 'Observation failed',
                description: error instanceof Error ? error.message : 'Unable to save observation.',
                variant: 'error',
            })
        },
    })

    if (systemsQuery.isLoading) {
        return (
            <div className="space-y-6">
                <Skeleton className="h-12 w-96" />
                <Skeleton className="h-64 w-full" />
                <Skeleton className="h-64 w-full" />
            </div>
        )
    }

    if (systemsQuery.isError) {
        return (
            <EmptyState
                title="Systems unavailable"
                description={systemsQuery.error?.message ?? 'Unable to load system telemetry.'}
            />
        )
    }

    if (!systems.length) {
        return <EmptyState title="No systems" description="No system records are available." />
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Systems"
                subtitle="Track water chemistry and device health with persisted observation history and manual entries."
            />

            <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>System snapshot</CardTitle>
                        <CardDescription>Backend-backed recirculation systems with live indicators and observation history.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {systems.map((system) => (
                            <button
                                key={system.systemID}
                                type="button"
                                onClick={() => setSelectedSystemID(system.systemID)}
                                className={`w-full rounded-xl border p-3 text-left transition ${
                                    (selectedSystem?.systemID ?? systems[0]?.systemID) === system.systemID
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-line bg-surface-2 hover:bg-surface-3'
                                }`}
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="font-semibold">
                                        {system.systemID} · {system.model}
                                    </p>
                                    <Badge variant={filtrationBadge(system.filtrationStatus)}>{system.filtrationStatus}</Badge>
                                </div>
                                <p className="text-xs opacity-80">
                                    {system.roomName} · {system.manufacturer}
                                </p>
                                <div className="mt-2 grid gap-2 text-xs md:grid-cols-3">
                                    <span>Temp {system.waterTemperature.toFixed(1)}°C</span>
                                    <span>pH {system.pH.toFixed(2)}</span>
                                    <span>Flow {system.waterFlow.toFixed(2)} L/min</span>
                                </div>
                                <div className="mt-1 grid gap-2 text-xs md:grid-cols-3">
                                    <span>Conductivity {system.conductivity.toFixed(0)} µS</span>
                                    <span>O₂ {system.oxygen.toFixed(2)} mg/L</span>
                                    <span>
                                        {system.fishCount} fish · {system.activeAlerts} alerts
                                    </span>
                                </div>
                            </button>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Selected system</CardTitle>
                        <CardDescription>Current values now come from persisted observation history.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-slate-600">
                        {selectedSystem ? (
                            <>
                                <p>
                                    <span className="font-semibold text-slate-800">ID:</span> {selectedSystem.systemID}
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Room:</span> {selectedSystem.roomName}
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Model:</span> {selectedSystem.manufacturer}{' '}
                                    {selectedSystem.model}
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Temperature:</span>{' '}
                                    {selectedSystem.waterTemperature.toFixed(1)} °C
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">pH:</span> {selectedSystem.pH.toFixed(2)}
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Conductivity:</span>{' '}
                                    {selectedSystem.conductivity.toFixed(0)} µS
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Oxygen:</span> {selectedSystem.oxygen.toFixed(2)} mg/L
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Water flow:</span>{' '}
                                    {selectedSystem.waterFlow.toFixed(2)} L/min
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Filtration:</span> {selectedSystem.filtrationStatus}
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Last observation:</span>{' '}
                                    {selectedSystem.latestObservationAt ? formatDateTime(selectedSystem.latestObservationAt) : 'No observation yet'}
                                </p>
                            </>
                        ) : (
                            <p>Select a system from the list.</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {allowEdit ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Record observation</CardTitle>
                        <CardDescription>Persist a manual temperature or pH reading for the selected system.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <EnvironmentalObservationForm
                            scopeType="system"
                            scopeID={selectedSystem?.systemID ?? selectedSystemID}
                            scopeLabel={observationScopeLabel(selectedSystem)}
                            defaultMetric="temperature"
                            isPending={observationMutation.isPending}
                            onSubmit={(values) => observationMutation.mutateAsync(values)}
                        />
                    </CardContent>
                </Card>
            ) : (
                <RestrictedActions
                    title="Read-only access"
                    description="Recording environmental observations requires editor access."
                    actionLabel="Editor access required"
                    disabled
                    onAction={() => {}}
                />
            )}

            <div className="grid gap-4 xl:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Temperature trend</CardTitle>
                        <CardDescription>{selectedSystem?.systemID ?? 'System'} history from persisted observations.</CardDescription>
                    </CardHeader>
                    <CardContent className="h-72">
                        {observationsQuery.isLoading ? (
                            <Skeleton className="h-full w-full" />
                        ) : observationsQuery.isError ? (
                            <EmptyState
                                title="History unavailable"
                                description={observationsQuery.error?.message ?? 'Unable to load history.'}
                            />
                        ) : (
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={temperatureTrend}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        dataKey="timestamp"
                                        tickFormatter={(value) =>
                                            new Intl.DateTimeFormat('en-GB', {
                                                day: '2-digit',
                                                month: 'short',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            }).format(new Date(value))
                                        }
                                    />
                                    <YAxis domain={['auto', 'auto']} />
                                    <Tooltip labelFormatter={(value) => formatDateTime(String(value))} />
                                    <Line dataKey="temperature" type="monotone" stroke="#f59e0b" strokeWidth={2.5} dot={false} />
                                </LineChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>pH trend</CardTitle>
                        <CardDescription>Manual and connected readings plotted over time.</CardDescription>
                    </CardHeader>
                    <CardContent className="h-72">
                        {observationsQuery.isLoading ? (
                            <Skeleton className="h-full w-full" />
                        ) : observationsQuery.isError ? (
                            <EmptyState
                                title="History unavailable"
                                description={observationsQuery.error?.message ?? 'Unable to load history.'}
                            />
                        ) : (
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={temperatureTrend}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis
                                        dataKey="timestamp"
                                        tickFormatter={(value) =>
                                            new Intl.DateTimeFormat('en-GB', {
                                                day: '2-digit',
                                                month: 'short',
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            }).format(new Date(value))
                                        }
                                    />
                                    <YAxis domain={['auto', 'auto']} />
                                    <Tooltip labelFormatter={(value) => formatDateTime(String(value))} />
                                    <Line dataKey="pH" type="monotone" stroke="#2563eb" strokeWidth={2.5} dot={false} />
                                </LineChart>
                            </ResponsiveContainer>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Recent observations</CardTitle>
                    <CardDescription>Most recent persisted readings for the selected system.</CardDescription>
                </CardHeader>
                <CardContent>
                    {recentObservations.length ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Timestamp</TableHead>
                                    <TableHead>Metric</TableHead>
                                    <TableHead>Value</TableHead>
                                    <TableHead>Source</TableHead>
                                    <TableHead>Notes</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recentObservations.map((observation) => (
                                    <TableRow key={observation.observationID ?? `${observation.metric}-${observation.timestamp}`}>
                                        <TableCell>{formatDateTime(observation.timestamp)}</TableCell>
                                        <TableCell>{formatMetric(observation.metric ?? observation.metricType ?? 'temperature')}</TableCell>
                                        <TableCell>{observation.value?.toFixed(2) ?? '--'}</TableCell>
                                        <TableCell>
                                            <Badge variant={observation.source === 'connected' ? 'outline' : 'default'}>
                                                {observation.source ?? 'manual'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="max-w-lg truncate text-slate-500">
                                            {observation.notes ?? observation.recorder ?? 'No notes'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <EmptyState title="No observations yet" description="Persist a new reading to populate the trend charts." />
                    )}
                </CardContent>
            </Card>
        </div>
    )
}
