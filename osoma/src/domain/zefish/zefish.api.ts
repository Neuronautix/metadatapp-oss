import type {
    EnvironmentalMetric,
    EnvironmentalObservation,
    EnvironmentalScope,
    EnvironmentalSource,
    ZefishCryoRecord,
    ZefishCryoRecordCreatePayload,
    RoomSnapshot,
    SystemHistoryPoint,
    ZefixLocationExplorerResponse,
    ZefishBatchCreatePayload,
    ZefishAlert,
    ZefishBatchDetail,
    ZefishBatchView,
    MortalityReason,
    ZefishLineDetail,
    ZefishLineSummary,
    ZefishSystemSnapshot,
    ZefixOverview,
} from '@/domain/zefish/zefish.types.ts'
import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import { getDataMode } from '@/lib/mode.ts'
import {
    buildOverview,
    buildRoomSnapshots,
    buildSystemSnapshots,
    buildEnvironmentalTrend,
    getLatestObservationAt,
    getLatestObservationValue,
    evaluateAlertRules,
    sortAlertsByRecent,
} from '@/domain/zefish/zefish.utils.ts'
import { zefishAlerts } from '@/mocks/zefish/alerts.ts'
import { zefishCryoRecords } from '@/mocks/zefish/cryo.ts'
import { zefishBatches } from '@/mocks/zefish/batches.ts'
import { zefishLines } from '@/mocks/zefish/lines.ts'
import {
    roomEnvironmentRecords,
    sensorRecords,
    zefishRooms,
    zefishSystems,
} from '@/mocks/zefish/systems.ts'
import { downloadBlob } from '@/lib/download.ts'

const wait = (ms = 120) => new Promise((resolve) => setTimeout(resolve, ms))

let runtimeAlerts: ZefishAlert[] = [...zefishAlerts]

const runtimeEnvironmentalObservations: EnvironmentalObservation[] = [
    ...sensorRecords.flatMap((record) => [
        {
            observationID: `${record.recordID}-temperature`,
            scopeType: 'system' as const,
            scopeID: record.systemID,
            systemID: record.systemID,
            metric: 'temperature' as const,
            value: record.waterTemperature,
            timestamp: record.timestamp,
            source: 'connected' as const,
        },
        {
            observationID: `${record.recordID}-ph`,
            scopeType: 'system' as const,
            scopeID: record.systemID,
            systemID: record.systemID,
            metric: 'pH' as const,
            value: record.pH,
            timestamp: record.timestamp,
            source: 'connected' as const,
        },
    ]),
    ...roomEnvironmentRecords.map((record) => ({
        observationID: record.recordID,
        scopeType: 'room' as const,
        scopeID: record.roomID,
        roomID: record.roomID,
        metric: 'temperature' as const,
        value: record.roomTemperature,
        timestamp: record.timestamp,
        source: 'connected' as const,
    })),
]

const runtimeCryoRecords: ZefishCryoRecord[] = [...zefishCryoRecords]

const appendRuntimeObservation = (observation: EnvironmentalObservation) => {
    runtimeEnvironmentalObservations.push(observation)
}

const appendRuntimeCryoRecord = (record: ZefishCryoRecord) => {
    runtimeCryoRecords.unshift(record)
}

const cloneRuntimeObservations = () => runtimeEnvironmentalObservations.map((observation) => ({ ...observation }))

const cloneRuntimeCryoRecords = () => runtimeCryoRecords.map((record) => ({ ...record }))

const buildQueryString = (params: object) => {
    const query = new URLSearchParams()
    Object.entries(params as Record<string, string | undefined | null>).forEach(([key, value]) => {
        if (value) {
            query.set(key, value)
        }
    })
    return query.toString()
}

const downloadExportBlob = async (
    path: string,
    filename: string,
    accept: string,
    params: object = {}
) => {
    if (getDataMode() !== 'real') {
        return
    }
    const suffix = buildQueryString(params)
    const blob = await apiFetch<Blob>(`${path}${suffix ? `?${suffix}` : ''}`, {
        headers: {
            Accept: accept,
        },
        responseType: 'blob',
    })

    downloadBlob(blob, filename)
}

const filterRuntimeObservations = (scopeType?: EnvironmentalScope, scopeID?: string) => {
    return cloneRuntimeObservations().filter((observation) => {
        if (scopeType && observation.scopeType !== scopeType) return false
        if (scopeID && (observation.scopeID ?? observation.systemID ?? observation.roomID) !== scopeID) return false
        return true
    })
}

const filterRuntimeCryoRecords = (lineID?: string) => {
    return cloneRuntimeCryoRecords()
        .filter((record) => {
            if (lineID && record.lineID !== lineID) return false
            return true
        })
        .sort((left, right) => new Date(right.storageDate).getTime() - new Date(left.storageDate).getTime())
}

type ApiCryoRecord = {
    '@id'?: string
    id?: string
    line?: {
        id?: string
    } | string | null
    storageDate: string
    storageLocation: string
    protocolUsed: string
    status?: {
        value?: ZefishCryoRecord['status']
    } | ZefishCryoRecord['status']
    notes?: string | null
}

const extractCryoRecordId = (record: ApiCryoRecord, fallbackLineID: string) => {
    const extracted = record.id ?? record['@id']?.split('/').at(-1)
    if (extracted) {
        return extracted
    }

    return new URLSearchParams({
        lineID: fallbackLineID,
        storageDate: record.storageDate,
        storageLocation: record.storageLocation,
        protocolUsed: record.protocolUsed,
        status: extractCryoStatus(record),
        notes: record.notes ?? '',
    }).toString()
}

const extractCryoLineId = (record: ApiCryoRecord, fallbackLineID: string) => {
    if (typeof record.line === 'string') {
        return record.line.split('/').at(-1) ?? fallbackLineID
    }

    return record.line?.id ?? fallbackLineID
}

const extractCryoStatus = (record: ApiCryoRecord): ZefishCryoRecord['status'] => {
    const value = typeof record.status === 'string' ? record.status : record.status?.value
    return value === 'used' || value === 'discarded' ? value : 'stored'
}

const mapApiCryoRecord = (record: ApiCryoRecord, fallbackLineID: string): ZefishCryoRecord => ({
    cryoRecordID: extractCryoRecordId(record, fallbackLineID),
    lineID: extractCryoLineId(record, fallbackLineID),
    storageDate: record.storageDate,
    storageLocation: record.storageLocation,
    protocolUsed: record.protocolUsed,
    status: extractCryoStatus(record),
    notes: record.notes ?? null,
})

const mergeAlerts = () => {
    const computed = evaluateAlertRules(zefishSystems, sensorRecords, zefishBatches)
    const byId = new Map<string, ZefishAlert>()

    runtimeAlerts.concat(computed).forEach((alert) => {
        byId.set(alert.alertID, alert)
    })

    return sortAlertsByRecent(Array.from(byId.values()))
}

export const getZefixOverview = async (): Promise<ZefixOverview> => {
    if (getDataMode() === 'real') {
        return apiFetch<ZefixOverview>('/zefix/overview', {
            headers: {
                Accept: 'application/json',
            },
        })
    }

    await wait()
    return buildOverview(zefishLines, zefishBatches, mergeAlerts(), sensorRecords)
}

export const downloadZefishLineStatisticsCsv = async (search = ''): Promise<void> => {
    if (getDataMode() !== 'real') {
        return
    }
    await downloadExportBlob('/zefix/exports/line-statistics.csv', 'zefix-line-statistics.csv', 'text/csv', {
        search: search.trim() || undefined,
    })
}

export const downloadZefishBatchHistoryCsv = async (search = ''): Promise<void> => {
    if (getDataMode() !== 'real') {
        return
    }
    await downloadExportBlob('/zefix/exports/batch-history.csv', 'zefix-batch-history.csv', 'text/csv', {
        search: search.trim() || undefined,
    })
}

export interface ZefishPoolOccupancyExportQuery {
    plateauID?: string
    roomID?: string
    rackID?: string
    poolID?: string
}

export const downloadZefishPoolOccupancyCsv = async (query: ZefishPoolOccupancyExportQuery = {}): Promise<void> => {
    if (getDataMode() !== 'real') {
        return
    }
    await downloadExportBlob('/zefix/exports/pool-occupancy.csv', 'zefix-pool-occupancy.csv', 'text/csv', query)
}

export const downloadZefishMortalityLogsCsv = async (batchID: string): Promise<void> => {
    if (getDataMode() !== 'real') {
        return
    }
    await downloadExportBlob('/zefix/exports/mortality-logs.csv', `zefix-mortality-logs-${batchID}.csv`, 'text/csv', {
        batchID,
    })
}

export const downloadZefishLineReportPdf = async (lineID: string): Promise<void> => {
    if (getDataMode() !== 'real') {
        return
    }
    await downloadExportBlob(`/zefix/reports/lines/${encodeURIComponent(lineID)}.pdf`, `zefix-line-report-${lineID}.pdf`, 'application/pdf')
}

export const downloadZefishCryoInventoryCsv = async (lineID: string): Promise<void> => {
    if (getDataMode() !== 'real') {
        return
    }

    await downloadExportBlob(
        '/zefix/exports/cryo-inventory.csv',
        `zefix-cryo-inventory-${lineID}.csv`,
        'text/csv',
        { lineID }
    )
}

export const getZefishLines = async (search = ''): Promise<ZefishLineSummary[]> => {
    const trimmedSearch = search.trim()
    const params = new URLSearchParams()
    if (trimmedSearch) {
        params.set('search', trimmedSearch)
    }

    const query = params.toString()
    return apiFetch<ZefishLineSummary[]>(`/zefix/lines${query ? `?${query}` : ''}`, {
        headers: {
            Accept: 'application/json',
        },
    })
}

export const getZefishLineDetail = async (lineID: string): Promise<ZefishLineDetail | null> => {
    return apiFetch<ZefishLineDetail>(`/zefix/lines/${lineID}`, {
        headers: {
            Accept: 'application/json',
        },
    })
}

export const getZefishBatches = async (search = ''): Promise<ZefishBatchView[]> => {
    const trimmedSearch = search.trim()
    const params = new URLSearchParams()

    if (trimmedSearch) {
        params.set('search', trimmedSearch)
    }

    const query = params.toString()

    return apiFetch<ZefishBatchView[]>(`/zefix/batches${query ? `?${query}` : ''}`, {
        headers: {
            Accept: 'application/json',
        },
    })
}

export const getZefishBatchDetail = async (batchID: string): Promise<ZefishBatchDetail> => {
    return apiFetch<ZefishBatchDetail>(`/zefix/batches/${batchID}`, {
        headers: {
            Accept: 'application/json',
        },
    })
}

const resourceIri = (resource: string, id: string) => `/${resource}/${id}`

export const createZefishBatch = async (payload: ZefishBatchCreatePayload): Promise<void> => {
    await apiFetch('/zebrafish_batches.jsonld', {
        method: 'POST',
        body: JSON.stringify({
            ...payload,
            line: resourceIri('zebrafish_lines', payload.line),
            pool: resourceIri('pools', payload.pool),
            lifecycleStatus: payload.lifecycleStatus ?? '/zebrafish_batch_lifecycle_statuses/active',
        }),
    })
}

export interface ZefishMortalityCreatePayload {
    batchID: string
    date: string
    count: number
    reason: MortalityReason
    notes?: string | null
}

export const createZefishMortalityRecord = async (payload: ZefishMortalityCreatePayload): Promise<void> => {
    await apiFetch('/mortality_records.jsonld', {
        method: 'POST',
        body: JSON.stringify({
            batch: resourceIri('zebrafish_batches', payload.batchID),
            date: payload.date,
            count: payload.count,
            reason: resourceIri('mortality_reasons', encodeURIComponent(payload.reason)),
            notes: payload.notes?.trim() ? payload.notes.trim() : null,
        }),
    })
}

export const getZefishSystems = async (): Promise<ZefishSystemSnapshot[]> => {
    if (getDataMode() === 'real') {
        return apiFetch<ZefishSystemSnapshot[]>('/zefix/systems', {
            headers: {
                Accept: 'application/json',
            },
        })
    }

    await wait()
    return buildSystemSnapshots(zefishSystems, zefishBatches, mergeAlerts()).map((system) => ({
        ...system,
        waterTemperature:
            getLatestObservationValue(runtimeEnvironmentalObservations, 'system', system.systemID, 'temperature') ??
            system.waterTemperature,
        pH: getLatestObservationValue(runtimeEnvironmentalObservations, 'system', system.systemID, 'pH') ?? system.pH,
        latestObservationAt: getLatestObservationAt(filterRuntimeObservations('system', system.systemID)),
    }))
}

export const getZefishSystemHistory = async (systemID: string): Promise<SystemHistoryPoint[]> => {
    if (getDataMode() === 'real') {
        return apiFetch<SystemHistoryPoint[]>(`/zefix/systems/${systemID}/history`, {
            headers: {
                Accept: 'application/json',
            },
        })
    }

    await wait()
    const observations = filterRuntimeObservations('system', systemID)
    return buildEnvironmentalTrend(observations).map((point) => ({
        timestamp: point.timestamp,
        temperature: point.temperature ?? 0,
        pH: point.pH ?? 0,
    }))
}

export const getZefishRooms = async (): Promise<RoomSnapshot[]> => {
    if (getDataMode() === 'real') {
        return apiFetch<RoomSnapshot[]>('/zefix/rooms', {
            headers: {
                Accept: 'application/json',
            },
        })
    }

    await wait()
    return buildRoomSnapshots(zefishRooms, roomEnvironmentRecords, zefishBatches).map((room) => {
        const observations = filterRuntimeObservations('room', room.room.roomID)

        return {
            ...room,
            roomTemperature:
                getLatestObservationValue(runtimeEnvironmentalObservations, 'room', room.room.roomID, 'temperature') ??
                room.roomTemperature,
            latestObservationAt: getLatestObservationAt(observations),
        }
    })
}

export interface ZefishEnvironmentalObservationQuery {
    scopeType?: EnvironmentalScope
    scopeID?: string
}

export interface ZefishEnvironmentalObservationCreatePayload {
    scopeType: EnvironmentalScope
    scopeID: string
    metric: EnvironmentalMetric
    value: number
    timestamp: string
    source?: EnvironmentalSource
    notes?: string | null
}

export const getZefishEnvironmentalObservations = async (
    query: ZefishEnvironmentalObservationQuery = {}
): Promise<EnvironmentalObservation[]> => {
    if (getDataMode() === 'real') {
        const params = new URLSearchParams()
        if (query.scopeType) {
            params.set('scopeType', query.scopeType)
        }
        if (query.scopeID) {
            params.set('scopeID', query.scopeID)
            params.set(query.scopeType === 'room' ? 'roomID' : 'systemID', query.scopeID)
        }

        const suffix = params.toString()
        return apiFetch<EnvironmentalObservation[]>(`/zefix/environmental-observations${suffix ? `?${suffix}` : ''}`, {
            headers: {
                Accept: 'application/json',
            },
        })
    }

    await wait(80)
    return filterRuntimeObservations(query.scopeType, query.scopeID)
}

export const createZefishEnvironmentalObservation = async (
    payload: ZefishEnvironmentalObservationCreatePayload
): Promise<void> => {
    const observation: EnvironmentalObservation = {
        observationID: `OBS-${Date.now()}`,
        scopeType: payload.scopeType,
        scopeID: payload.scopeID,
        systemID: 'system' === payload.scopeType ? payload.scopeID : undefined,
        roomID: 'room' === payload.scopeType ? payload.scopeID : undefined,
        metric: payload.metric,
        value: payload.value,
        timestamp: payload.timestamp,
        source: payload.source ?? 'manual',
        notes: payload.notes ?? null,
        recorder: 'Manual entry',
    }

    if (getDataMode() !== 'real') {
        await wait(80)
        appendRuntimeObservation(observation)
        return
    }

    await apiFetch('/zefix/environmental-observations', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            scopeType: payload.scopeType,
            scopeID: payload.scopeID,
            metric: payload.metric,
            value: payload.value,
            timestamp: payload.timestamp,
            source: payload.source ?? 'manual',
            notes: payload.notes ?? null,
        }),
    })
}

export const getZefishCryoRecords = async (lineID: string): Promise<ZefishCryoRecord[]> => {
    if (getDataMode() === 'real') {
        try {
            const response = await apiFetchHydraMapped<ApiCryoRecord, ZefishCryoRecord>(
                `/cryo_records?${new URLSearchParams({ 'line.id': lineID }).toString()}`,
                (record) => mapApiCryoRecord(record, lineID),
                {
                    headers: {
                        Accept: 'application/ld+json',
                    },
                }
            )

            return response.data
        } catch {
            return filterRuntimeCryoRecords(lineID)
        }
    }

    await wait(80)
    return filterRuntimeCryoRecords(lineID)
}

export const createZefishCryoRecord = async (
    payload: ZefishCryoRecordCreatePayload
): Promise<void> => {
    const record: ZefishCryoRecord = {
        cryoRecordID: `CRYO-${Date.now()}`,
        lineID: payload.lineID,
        storageDate: payload.storageDate,
        storageLocation: payload.storageLocation,
        protocolUsed: payload.protocolUsed,
        status: payload.status,
        notes: payload.notes ?? null,
        createdAt: new Date().toISOString(),
    }

    if (getDataMode() !== 'real') {
        await wait(80)
        appendRuntimeCryoRecord(record)
        return
    }

    try {
        await apiFetch(`/zefix/lines/${encodeURIComponent(payload.lineID)}/cryo-records`, {
            method: 'POST',
            body: JSON.stringify({
                storageDate: payload.storageDate,
                storageLocation: payload.storageLocation,
                protocolUsed: payload.protocolUsed,
                status: `/cryo_record_statuses/${payload.status}`,
                notes: payload.notes ?? null,
            }),
        })
    } catch {
        await wait(80)
        appendRuntimeCryoRecord(record)
    }
}

export const getZefishAlerts = async (status?: ZefishAlert['status']): Promise<ZefishAlert[]> => {
    if (getDataMode() === 'real') {
        const params = new URLSearchParams()
        if (status) {
            params.set('status', status)
        }

        const suffix = params.toString()
        return apiFetch<ZefishAlert[]>(`/zefix/alerts${suffix ? `?${suffix}` : ''}`, {
            headers: {
                Accept: 'application/json',
            },
        })
    }

    await wait()
    const merged = mergeAlerts()
    if (!status) return merged
    return merged.filter((alert) => alert.status === status)
}

export const acknowledgeZefishAlert = async (alertID: string): Promise<void> => {
    if (getDataMode() === 'real') {
        await apiFetch(`/zefix/alerts/${encodeURIComponent(alertID)}/acknowledge`, {
            method: 'POST',
            responseType: 'none',
        })
        return
    }

    await wait(80)
    runtimeAlerts = runtimeAlerts.map((alert) =>
        alert.alertID === alertID
            ? { ...alert, status: 'acknowledged', acknowledgedAt: new Date().toISOString() }
            : alert
    )
}

export const resolveZefishAlert = async (alertID: string): Promise<void> => {
    if (getDataMode() === 'real') {
        await apiFetch(`/zefix/alerts/${encodeURIComponent(alertID)}/resolve`, {
            method: 'POST',
            responseType: 'none',
        })
        return
    }

    await wait(80)
    runtimeAlerts = runtimeAlerts.map((alert) =>
        alert.alertID === alertID
            ? { ...alert, status: 'resolved', resolvedAt: new Date().toISOString() }
            : alert
    )
}

export const triggerTemperatureAlert = async (): Promise<ZefishAlert> => {
    await wait(80)
    const now = new Date().toISOString()
    const alert: ZefishAlert = {
        alertID: `ALERT-DRIFT-${Date.now()}`,
        type: 'temperature outside range',
        severity: 'CRITICAL',
        status: 'active',
        affectedEntityType: 'system',
        affectedEntityId: 'SYS-02',
        affectedLabel: 'System SYS-02 · Room 203',
        timestamp: now,
        recommendedAction: 'Emergency cooling protocol: verify loop flow and add backup chiller.',
    }
    runtimeAlerts = [alert, ...runtimeAlerts]
    return alert
}

export const getLocationExplorerData = async (): Promise<ZefixLocationExplorerResponse> => {
    return apiFetch<ZefixLocationExplorerResponse>('/zefix/location-explorer')
}
