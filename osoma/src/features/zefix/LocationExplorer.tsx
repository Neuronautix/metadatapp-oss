import { useMemo, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit } from '@/lib/rbac.ts'
import {
    downloadZefishPoolOccupancyCsv,
    getLocationExplorerData,
    getZefishAlerts,
} from '@/domain/zefish/zefish.api.ts'
import {
    asAlertSeverityVariant,
    countActiveAlertsForEntityIds,
    formatAgeLabel,
    getAgeDays,
} from '@/domain/zefish/zefish.utils.ts'
import { CreateBatchDialog } from '@/features/zefix/CreateBatchDialog.tsx'

const compactStrings = (values: Array<string | null | undefined>) => values.filter((value): value is string => Boolean(value))

export function LocationExplorer() {
    const explorerQuery = useQuery({
        queryKey: ['zefix', 'location-explorer'],
        queryFn: getLocationExplorerData,
    })

    const alertsQuery = useQuery({
        queryKey: ['zefix', 'alerts', 'active'],
        queryFn: () => getZefishAlerts('active'),
    })

    const [selectedPlateauID, setSelectedPlateauID] = useState('')
    const [selectedRoomID, setSelectedRoomID] = useState('')
    const [selectedRackID, setSelectedRackID] = useState('')
    const [selectedPoolID, setSelectedPoolID] = useState('')
    const { role } = useRole()
    const allowEdit = canEdit(role)

    const data = explorerQuery.data
    const activeAlerts = alertsQuery.data ?? []
    const countAlertsForIds = (ids: Array<string | null | undefined>) =>
        countActiveAlertsForEntityIds(activeAlerts, compactStrings(ids))

    const plateau = useMemo(
        () => data?.plateaux.find((entry) => entry.plateauId === selectedPlateauID) ?? data?.plateaux[0],
        [data?.plateaux, selectedPlateauID]
    )
    const room = useMemo(
        () => plateau?.rooms.find((entry) => entry.roomId === selectedRoomID) ?? plateau?.rooms[0],
        [plateau?.rooms, selectedRoomID]
    )
    const rack = useMemo(
        () => room?.racks.find((entry) => entry.rackId === selectedRackID) ?? room?.racks[0],
        [room?.racks, selectedRackID]
    )
    const pool = useMemo(
        () => rack?.pools.find((entry) => entry.poolId === selectedPoolID) ?? rack?.pools[0],
        [rack?.pools, selectedPoolID]
    )
    const exportMutation = useMutation({
        mutationFn: () =>
            downloadZefishPoolOccupancyCsv({
                plateauID: plateau?.plateauId,
                roomID: room?.roomId,
                rackID: rack?.rackId,
                poolID: pool?.poolId,
            }),
    })
    const plateauAlertCount = useMemo(() => {
        if (!plateau) return 0

        const entityIds = compactStrings([
            plateau.plateauId,
            ...plateau.rooms.flatMap((roomEntry) => [
                roomEntry.roomId,
                ...roomEntry.racks.flatMap((rackEntry) => [
                    rackEntry.rackId,
                    rackEntry.code,
                    rackEntry.systemId,
                    ...rackEntry.pools.flatMap((poolEntry) => [
                        poolEntry.poolId,
                        poolEntry.batch?.batchId ?? '',
                    ]),
                ]),
            ]),
        ])

        return countAlertsForIds(entityIds)
    }, [activeAlerts, plateau])

    const roomAlertCount = useMemo(() => {
        if (!room) return 0

        const entityIds = compactStrings([
            room.roomId,
            ...room.racks.flatMap((rackEntry) => [
                rackEntry.rackId,
                rackEntry.code,
                rackEntry.systemId,
                ...rackEntry.pools.flatMap((poolEntry) => [
                    poolEntry.poolId,
                    poolEntry.batch?.batchId ?? '',
                ]),
            ]),
        ])

        return countAlertsForIds(entityIds)
    }, [activeAlerts, room])

    const rackAlertCount = useMemo(() => {
        if (!rack) return 0

        const entityIds = compactStrings([
            rack.rackId,
            rack.code,
            rack.systemId,
            ...rack.pools.flatMap((poolEntry) => [
                poolEntry.poolId,
                poolEntry.batch?.batchId ?? '',
            ]),
        ])

        return countAlertsForIds(entityIds)
    }, [activeAlerts, rack])

    const poolAlertCount = useMemo(() => {
        if (!pool || !rack) return 0

        return countAlertsForIds([
            pool.poolId,
            pool.batch?.batchId ?? '',
            rack.systemId,
            rack.code,
            rack.rackId,
        ])
    }, [activeAlerts, pool, rack])

    const selectedPoolAlerts = useMemo(() => {
        if (!pool || !rack) return []

        return activeAlerts
            .filter((alert) =>
                [pool.poolId, pool.batch?.batchId ?? '', rack.systemId ?? '', rack.code, rack.rackId].includes(alert.affectedEntityId)
            )
            .slice()
            .sort((left, right) => new Date(right.timestamp).getTime() - new Date(left.timestamp).getTime())
    }, [activeAlerts, pool, rack])

    const canCreateBatch = Boolean(pool && !pool.occupied && pool.status === 'available')

    return (
        <div className="space-y-6">
            <PageHeader
                title="Location Explorer"
                subtitle="Facility -> Plateau -> Room -> Rack -> Pool hierarchy with real backend occupancy."
                actions={
                    allowEdit ? (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => exportMutation.mutate()}
                            disabled={exportMutation.isPending || !plateau}
                        >
                            {exportMutation.isPending ? 'Exporting…' : 'Export occupancy CSV'}
                        </Button>
                    ) : undefined
                }
            />

            {explorerQuery.isLoading ? (
                <div className="grid gap-4 lg:grid-cols-5">
                    {Array.from({ length: 5 }).map((_, index) => (
                        <Skeleton key={index} className="h-80" />
                    ))}
                </div>
            ) : explorerQuery.isError || !data ? (
                <EmptyState
                    title="Location explorer unavailable"
                    description={explorerQuery.error?.message ?? 'Unable to load location structure.'}
                />
            ) : (
                <>
                    <Card>
                        <CardHeader>
                            <CardTitle>{data.facility}</CardTitle>
                            <CardDescription>Interactive pool hierarchy from real Zefix backend resources.</CardDescription>
                        </CardHeader>
                    </Card>

                    <div className="grid gap-4 lg:grid-cols-5">
                        <SelectorColumn
                            title="Plateaux"
                            items={data.plateaux.map((entry) => ({
                                id: entry.plateauId,
                                label: entry.name,
                                meta: `${entry.rooms.length} rooms · ${countAlertsForIds([
                                    entry.plateauId,
                                    ...entry.rooms.flatMap((roomEntry) => [
                                        roomEntry.roomId,
                                        ...roomEntry.racks.flatMap((rackEntry) => [
                                            rackEntry.rackId,
                                            rackEntry.code,
                                            rackEntry.systemId,
                                            ...rackEntry.pools.flatMap((poolEntry) => [
                                                poolEntry.poolId,
                                                poolEntry.batch?.batchId ?? '',
                                            ]),
                                        ]),
                                    ]),
                                ])} alerts`,
                            }))}
                            selectedId={plateau?.plateauId ?? ''}
                            onSelect={(id) => {
                                setSelectedPlateauID(id)
                                setSelectedRoomID('')
                                setSelectedRackID('')
                                setSelectedPoolID('')
                            }}
                        />

                        <SelectorColumn
                            title="Rooms"
                            items={(plateau?.rooms ?? []).map((entry) => ({
                                id: entry.roomId,
                                label: entry.name,
                                meta: `${entry.racks.length} racks · ${countAlertsForIds([
                                    entry.roomId,
                                    ...entry.racks.flatMap((rackEntry) => [
                                        rackEntry.rackId,
                                        rackEntry.code,
                                        rackEntry.systemId,
                                        ...rackEntry.pools.flatMap((poolEntry) => [
                                            poolEntry.poolId,
                                            poolEntry.batch?.batchId ?? '',
                                        ]),
                                    ]),
                                ])} alerts`,
                            }))}
                            selectedId={room?.roomId ?? ''}
                            onSelect={(id) => {
                                setSelectedRoomID(id)
                                setSelectedRackID('')
                                setSelectedPoolID('')
                            }}
                        />

                        <SelectorColumn
                            title="Racks"
                            items={(room?.racks ?? []).map((entry) => ({
                                id: entry.rackId,
                                label: entry.code,
                                meta: `${entry.pools.length} pools · ${countAlertsForIds([
                                    entry.rackId,
                                    entry.code,
                                    entry.systemId,
                                    ...entry.pools.flatMap((poolEntry) => [
                                        poolEntry.poolId,
                                        poolEntry.batch?.batchId ?? '',
                                    ]),
                                ])} alerts`,
                            }))}
                            selectedId={rack?.rackId ?? ''}
                            onSelect={(id) => {
                                setSelectedRackID(id)
                                setSelectedPoolID('')
                            }}
                        />

                        <SelectorColumn
                            title="Pools"
                            items={(rack?.pools ?? []).map((entry) => ({
                                id: entry.poolId,
                                label: entry.position,
                                meta: `${entry.batch ? `${entry.batch.totalPopulation} fish` : 'Empty'} · ${countAlertsForIds([
                                    entry.poolId,
                                    entry.batch?.batchId ?? '',
                                    rack?.systemId,
                                    rack?.code ?? '',
                                    rack?.rackId ?? '',
                                ])} alerts`,
                            }))}
                            selectedId={pool?.poolId ?? ''}
                            onSelect={setSelectedPoolID}
                        />

                        <Card>
                            <CardHeader>
                                <CardTitle>Pool detail</CardTitle>
                                <CardDescription>Capacity, pool status, and active batch occupancy.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2 text-sm text-slate-600">
                                {pool ? (
                                    <>
                                        <p><span className="font-semibold text-slate-800">Pool:</span> {pool.position}</p>
                                        <p><span className="font-semibold text-slate-800">Path:</span> {plateau?.code} / {room?.name} / {rack?.code}</p>
                                        <p><span className="font-semibold text-slate-800">Capacity:</span> {pool.capacity}</p>
                                        <div className="flex flex-wrap gap-2 pt-1">
                                            <Badge variant={pool.occupied ? 'default' : 'outline'}>
                                                {pool.occupied ? 'Occupied' : 'Empty'}
                                            </Badge>
                                            <Badge variant="outline">{pool.status}</Badge>
                                            <Badge variant={poolAlertCount ? 'critical' : 'outline'}>
                                                {poolAlertCount ? `${poolAlertCount} alerts` : 'No alerts'}
                                            </Badge>
                                        </div>
                                        {pool.batch ? (
                                            <>
                                                <p><span className="font-semibold text-slate-800">Batch:</span> {pool.batch.batchId}</p>
                                                <p><span className="font-semibold text-slate-800">Line:</span> {pool.batch.lineName}</p>
                                                <p><span className="font-semibold text-slate-800">Fish count:</span> {pool.batch.totalPopulation}</p>
                                                <p><span className="font-semibold text-slate-800">Birth date:</span> {pool.batch.birthDate}</p>
                                                <p><span className="font-semibold text-slate-800">Age:</span> {formatAgeLabel(getAgeDays(pool.batch.birthDate))}</p>
                                                <p><span className="font-semibold text-slate-800">Lifecycle:</span> {pool.batch.lifecycleStatus}</p>
                                            </>
                                        ) : (
                                            <div className="space-y-3 pt-2">
                                                <p>No active batch present in this pool.</p>
                                                {allowEdit && canCreateBatch && plateau && room && rack ? (
                                                    <CreateBatchDialog
                                                        pool={pool}
                                                        plateauCode={plateau.code}
                                                        roomName={room.name}
                                                        rackCode={rack.code}
                                                    />
                                                ) : (
                                                    <p className="text-xs text-slate-500">
                                                        Only an empty, available pool can accept a new batch. Batch creation requires editor access.
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                        <div className="pt-2 text-xs text-slate-500">
                                            <p>
                                                <span className="font-semibold text-slate-800">Room alerts:</span> {roomAlertCount}
                                            </p>
                                            <p>
                                                <span className="font-semibold text-slate-800">Rack alerts:</span> {rackAlertCount}
                                            </p>
                                            <p>
                                                <span className="font-semibold text-slate-800">Plateau alerts:</span> {plateauAlertCount}
                                            </p>
                                        </div>
                                        {selectedPoolAlerts.length ? (
                                            <div className="space-y-2 pt-3">
                                                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                                    Active alerts
                                                </p>
                                                {selectedPoolAlerts.slice(0, 3).map((alert) => (
                                                    <div key={alert.alertID} className="rounded-lg border border-line bg-surface p-2 text-xs">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="font-medium text-slate-800">{alert.type}</span>
                                                            <Badge variant={asAlertSeverityVariant(alert.severity)}>{alert.severity}</Badge>
                                                        </div>
                                                        <p className="mt-1 text-slate-500">{alert.affectedLabel}</p>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : null}
                                    </>
                                ) : (
                                    <p>Select a pool to inspect current occupancy.</p>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </>
            )}
        </div>
    )
}

function SelectorColumn(
    {
        title,
        items,
        selectedId,
        onSelect,
    }: {
        title: string
        items: Array<{ id: string; label: string; meta: string }>
        selectedId: string
        onSelect: (id: string) => void
    }
) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{items.length} items</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
                {items.length ? (
                    items.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() => onSelect(item.id)}
                            className={`w-full rounded-lg border px-3 py-2 text-left text-sm transition ${item.id === selectedId
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-line bg-surface-2 hover:bg-surface-3'
                                }`}
                        >
                            <p className="font-medium">{item.label}</p>
                            <p className="text-xs opacity-80">{item.meta}</p>
                        </button>
                    ))
                ) : (
                    <p className="text-xs text-slate-500">No data available.</p>
                )}
            </CardContent>
        </Card>
    )
}
