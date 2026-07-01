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
import { RestrictedActions } from '@/components/RestrictedActions.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import {
    createZefishEnvironmentalObservation,
    getZefishEnvironmentalObservations,
    getZefishRooms,
} from '@/domain/zefish/zefish.api.ts'
import type { ZefishRoom } from '@/domain/zefish/zefish.types.ts'
import { buildEnvironmentalTrend, formatMetric, toObservationTimestamp, sortRecentObservations } from '@/domain/zefish/zefish.utils.ts'
import { formatDateTime } from '@/lib/format.ts'
import { EnvironmentalObservationForm } from '@/features/zefix/EnvironmentalObservationForm.tsx'
import type { EnvironmentalObservationFormValues } from '@/features/zefix/EnvironmentalObservationForm.tsx'
import { useToast } from '@/components/ui/toast.tsx'

const roomLabel = (room: ZefishRoom | undefined) => {
    if (!room) return 'Selected room'
    return `${room.roomName} · ${room.facility}`
}

export function RoomsPage() {
    const [selectedRoomID, setSelectedRoomID] = useState('')
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const { role } = useRole()
    const allowEdit = canEdit(role)

    const roomsQuery = useQuery({
        queryKey: ['zefix', 'rooms'],
        queryFn: getZefishRooms,
    })

    const rooms = roomsQuery.data ?? []

    useEffect(() => {
        if (!selectedRoomID && rooms.length) {
            setSelectedRoomID(rooms[0].room.roomID)
        }
    }, [selectedRoomID, rooms])

    const selectedRoom = useMemo(
        () => rooms.find((room) => room.room.roomID === selectedRoomID) ?? rooms[0],
        [rooms, selectedRoomID]
    )

    const observationsQuery = useQuery({
        queryKey: ['zefix', 'rooms', selectedRoom?.room.roomID ?? selectedRoomID, 'observations'],
        queryFn: () =>
            getZefishEnvironmentalObservations({
                scopeType: 'room',
                scopeID: selectedRoom?.room.roomID ?? selectedRoomID,
            }),
        enabled: Boolean(selectedRoom?.room.roomID ?? selectedRoomID),
    })

    const observations = observationsQuery.data ?? []
    const trend = useMemo(() => buildEnvironmentalTrend(observations), [observations])
    const recentObservations = useMemo(() => sortRecentObservations(observations).slice(0, 8), [observations])

    const observationMutation = useMutation({
        mutationFn: async (values: EnvironmentalObservationFormValues) => {
            const room = selectedRoom?.room ?? rooms[0]?.room
            if (!room) {
                throw new Error('No room is available for observation entry.')
            }

            await createZefishEnvironmentalObservation({
                scopeType: 'room',
                scopeID: room.roomID,
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
                description: 'Room trend charts will refresh from persisted observations.',
                variant: 'success',
            })
            await Promise.all([
                queryClient.invalidateQueries({ queryKey: ['zefix', 'rooms'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'rooms', selectedRoom?.room.roomID ?? selectedRoomID, 'observations'] }),
                queryClient.invalidateQueries({ queryKey: ['zefix', 'systems'] }),
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

    if (roomsQuery.isLoading) {
        return (
            <div className="space-y-6">
                <Skeleton className="h-12 w-96" />
                <Skeleton className="h-64 w-full" />
                <Skeleton className="h-64 w-full" />
            </div>
        )
    }

    if (roomsQuery.isError) {
        return (
            <EmptyState
                title="Rooms unavailable"
                description={roomsQuery.error?.message ?? 'Unable to load room metrics.'}
            />
        )
    }

    if (!rooms.length) {
        return <EmptyState title="No rooms" description="No room records are available." />
    }

    return (
        <div className="space-y-6">
            <PageHeader
                title="Rooms"
                subtitle="Persisted room conditions, real observation history, and manual environmental entry."
            />

            <div className="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Room snapshot</CardTitle>
                        <CardDescription>Persisted room conditions with live aggregates from stored observations.</CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {rooms.map((snapshot) => (
                            <button
                                key={snapshot.room.roomID}
                                type="button"
                                onClick={() => setSelectedRoomID(snapshot.room.roomID)}
                                className={`rounded-2xl border p-4 text-left transition ${
                                    (selectedRoom?.room.roomID ?? rooms[0]?.room.roomID) === snapshot.room.roomID
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-line bg-surface-2 hover:bg-surface-3'
                                }`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold">{snapshot.room.roomName}</p>
                                        <p className="text-xs opacity-80">
                                            {snapshot.room.facility} · {snapshot.room.roomID}
                                        </p>
                                    </div>
                                    <Badge variant="outline">{snapshot.lightCycle}</Badge>
                                </div>
                                <div className="mt-3 space-y-1 text-sm">
                                    <p>Temp {snapshot.roomTemperature.toFixed(1)} °C</p>
                                    <p>Humidity {snapshot.humidity.toFixed(1)} %</p>
                                    <p>Noise {snapshot.noiseLevel.toFixed(1)} dB</p>
                                    <p>30d mortalities {snapshot.mortality30d}</p>
                                </div>
                            </button>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Selected room</CardTitle>
                        <CardDescription>Aggregated conditions for the chosen room.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-slate-600">
                        {selectedRoom ? (
                            <>
                                <p>
                                    <span className="font-semibold text-slate-800">Room:</span> {selectedRoom.room.roomName}
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Facility:</span> {selectedRoom.room.facility}
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Temperature:</span>{' '}
                                    {selectedRoom.roomTemperature.toFixed(1)} °C
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Humidity:</span> {selectedRoom.humidity.toFixed(1)} %
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Noise:</span> {selectedRoom.noiseLevel.toFixed(1)} dB
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Light cycle:</span> {selectedRoom.lightCycle}
                                </p>
                                <p>
                                    <span className="font-semibold text-slate-800">Last observation:</span>{' '}
                                    {selectedRoom.latestObservationAt ? formatDateTime(selectedRoom.latestObservationAt) : 'No observation yet'}
                                </p>
                                <div className="flex flex-wrap gap-2 pt-1">
                                    <Badge variant="outline">30d mortalities {selectedRoom.mortality30d}</Badge>
                                    <Badge variant="outline">30d breeding {selectedRoom.breedingEvents30d}</Badge>
                                </div>
                            </>
                        ) : (
                            <p>Select a room from the list.</p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {allowEdit ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Record observation</CardTitle>
                        <CardDescription>Persist a manual temperature or pH reading for the selected room.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <EnvironmentalObservationForm
                            scopeType="room"
                            scopeID={selectedRoom?.room.roomID ?? selectedRoomID}
                            scopeLabel={roomLabel(selectedRoom?.room)}
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
                        <CardDescription>Persisted room observations plotted chronologically.</CardDescription>
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
                                <LineChart data={trend}>
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
                        <CardDescription>Room-level pH observations, including manual entries.</CardDescription>
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
                                <LineChart data={trend}>
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
                    <CardDescription>Most recent persisted readings for the selected room.</CardDescription>
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
