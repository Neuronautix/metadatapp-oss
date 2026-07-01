import type {
    BatchMortalityEvent,
    BatchTimelinePoint,
    EnvironmentalMetric,
    EnvironmentalObservation,
    EnvironmentalTrendPoint,
    EnvironmentalScope,
    FishPopulation,
    LocationExplorerData,
    LocationRackNode,
    LocationRoomNode,
    LocationSystemNode,
    RoomSnapshot,
    SensorRecord,
    SystemHistoryPoint,
    TankSnapshot,
    ZefishAlert,
    ZefishBatch,
    ZefishBatchDetail,
    ZefishBatchView,
    ZefishLine,
    ZefishLineDetail,
    ZefishLineSummary,
    ZefishLocation,
    ZefishRoom,
    ZefishSystem,
    ZefishSystemSnapshot,
    ZefixOverview,
    ZefishMortalityRecord,
} from '@/domain/zefish/zefish.types.ts'

const DAY_MS = 24 * 60 * 60 * 1000

const startOfDay = (date: Date) => new Date(date.getFullYear(), date.getMonth(), date.getDate())

const normalizeObservationMetric = (observation: EnvironmentalObservation): EnvironmentalMetric | null => {
    return observation.metric ?? observation.metricType ?? null
}

const normalizeObservationValue = (observation: EnvironmentalObservation): number | null => {
    return typeof observation.value === 'number' && Number.isFinite(observation.value) ? observation.value : null
}

const normalizeObservationScopeID = (observation: EnvironmentalObservation): string => {
    return observation.scopeID ?? observation.systemID ?? observation.roomID ?? ''
}

const normalizeObservationScope = (observation: EnvironmentalObservation) => {
    if (observation.scopeType) return observation.scopeType
    if (observation.systemID) return 'system'
    if (observation.roomID) return 'room'
    return null
}

export const formatMetric = (metric: EnvironmentalMetric): string => {
    return metric === 'pH' ? 'pH' : 'Temperature'
}

export const toObservationTimestamp = (value: string): string => {
    return new Date(value).toISOString()
}

export const sortRecentObservations = (
    observations: EnvironmentalObservation[]
): EnvironmentalObservation[] => {
    return observations
        .slice()
        .sort((left, right) => new Date(right.timestamp).getTime() - new Date(left.timestamp).getTime())
}

export const getAgeDays = (birthDate: string) => {
    const diff = Date.now() - new Date(birthDate).getTime()
    return Math.max(0, Math.floor(diff / DAY_MS))
}

export const formatAgeLabel = (ageDays: number) => {
    if (ageDays < 31) return `${ageDays} days`
    if (ageDays < 365) return `${Math.floor(ageDays / 30)} months`
    return `${Math.floor(ageDays / 365)} years`
}

const getMortalityCount = (batch: ZefishBatch) =>
    batch.events
        .filter((event): event is BatchMortalityEvent => event.type === 'mortality')
        .reduce((sum, event) => sum + event.number, 0)

const getLatestEventDate = (batch: ZefishBatch) => {
    if (!batch.events.length) return new Date(batch.birthDate).getTime()
    return Math.max(...batch.events.map((event) => new Date(event.date).getTime()))
}

const byRecentActivity = (left: ZefishBatch, right: ZefishBatch) => getLatestEventDate(right) - getLatestEventDate(left)

export const buildBatchView = (
    batch: ZefishBatch,
    linesById: Map<string, ZefishLine>,
    locationsById: Map<string, ZefishLocation>
): ZefishBatchView => {
    const line = linesById.get(batch.lineID)
    const location = locationsById.get(batch.locationId)
    const ageDays = getAgeDays(batch.birthDate)

    return {
        ...batch,
        lineName: line?.name ?? batch.lineID,
        roomName: location?.roomName ?? batch.roomID,
        tankLocation: location
            ? `${location.facility} / ${location.roomName} / ${location.systemID} / ${location.rack} / ${location.tank}`
            : batch.locationId,
        ageDays,
        ageLabel: formatAgeLabel(ageDays),
        mortalityCount: getMortalityCount(batch),
    }
}

export const buildEnvironmentalTrend = (observations: EnvironmentalObservation[]): EnvironmentalTrendPoint[] => {
    const points = new Map<string, EnvironmentalTrendPoint>()

    observations
        .slice()
        .sort((left, right) => new Date(left.timestamp).getTime() - new Date(right.timestamp).getTime())
        .forEach((observation) => {
            const metric = normalizeObservationMetric(observation)
            const value = normalizeObservationValue(observation)
            if (!metric || null === value) return

            const key = observation.timestamp
            const existing = points.get(key) ?? { timestamp: key }

            if ('temperature' === metric) {
                existing.temperature = value
            } else if ('pH' === metric) {
                existing.pH = value
            }

            existing.source = observation.source ?? existing.source ?? null
            points.set(key, existing)
        })

    return Array.from(points.values())
}

export const filterEnvironmentalObservations = (
    observations: EnvironmentalObservation[],
    scopeType: EnvironmentalScope,
    scopeID: string
): EnvironmentalObservation[] => {
    return observations.filter((observation) => {
        const observationScope = normalizeObservationScope(observation)
        return observationScope === scopeType && normalizeObservationScopeID(observation) === scopeID
    })
}

export const getLatestObservationAt = (observations: EnvironmentalObservation[]): string | null => {
    const latest = observations
        .map((observation) => observation.timestamp)
        .sort((left, right) => new Date(right).getTime() - new Date(left).getTime())[0]

    return latest ?? null
}

export const getLatestObservationValue = (
    observations: EnvironmentalObservation[],
    scopeType: EnvironmentalScope,
    scopeID: string,
    metric: EnvironmentalMetric
): number | null => {
    const sorted = filterEnvironmentalObservations(observations, scopeType, scopeID)
        .filter((observation) => normalizeObservationMetric(observation) === metric)
        .sort((left, right) => new Date(right.timestamp).getTime() - new Date(left.timestamp).getTime())

    const latest = sorted[0]
    return latest ? normalizeObservationValue(latest) : null
}

export const deriveLineSummaries = (lines: ZefishLine[], batches: ZefishBatch[]): ZefishLineSummary[] => {
    return lines.map((line) => {
        const relatedBatches = batches.filter((batch) => batch.lineID === line.lineID)
        const activeBatches = relatedBatches.filter((batch) => batch.individualCount > 0).length
        const totalAnimals = relatedBatches.reduce((sum, batch) => sum + batch.individualCount, 0)
        const initialAnimals = relatedBatches.reduce((sum, batch) => sum + batch.initialCount, 0)
        const totalMortalities = relatedBatches.reduce((sum, batch) => sum + getMortalityCount(batch), 0)
        const mortalityRate = initialAnimals > 0 ? (totalMortalities / initialAnimals) * 100 : 0

        let reproductionStatus: FishPopulation['reproductionStatus'] = 'Stable'
        if (activeBatches >= 4) reproductionStatus = 'Breeding'
        if (mortalityRate >= 14) reproductionStatus = 'Watch'
        if (activeBatches === 0) reproductionStatus = 'Paused'

        return {
            ...line,
            totalAnimals,
            activeBatches,
            mortalityRate: Number(mortalityRate.toFixed(1)),
            reproductionStatus,
        }
    })
}

export const buildLineDetail = (
    lineID: string,
    lines: ZefishLine[],
    batches: ZefishBatch[],
    locations: ZefishLocation[]
): ZefishLineDetail | null => {
    const line = lines.find((entry) => entry.lineID === lineID)
    if (!line) return null

    const lineSummaries = deriveLineSummaries(lines, batches)
    const summary = lineSummaries.find((entry) => entry.lineID === lineID)
    if (!summary) return null

    const linesById = new Map(lines.map((entry) => [entry.lineID, entry]))
    const locationsById = new Map(locations.map((entry) => [entry.locationId, entry]))
    const lineBatches = batches
        .filter((batch) => batch.lineID === lineID)
        .sort(byRecentActivity)
        .map((batch) => buildBatchView(batch, linesById, locationsById))

    return {
        line: summary,
        batches: lineBatches,
    }
}

export const buildBatchTimeline = (batch: ZefishBatch): BatchTimelinePoint[] => {
    return batch.events
        .slice()
        .sort((left, right) => new Date(left.date).getTime() - new Date(right.date).getTime())
        .map((event) => {
            if (event.type === 'birth') {
                return {
                    id: event.id,
                    title: 'Birth',
                    subtitle: `${event.count} fish`,
                    timestamp: event.date,
                    color: 'green',
                }
            }

            if (event.type === 'transfer') {
                return {
                    id: event.id,
                    title: 'Transfer',
                    subtitle: `${event.count} transferred`,
                    timestamp: event.date,
                    color: 'indigo',
                }
            }

            return {
                id: event.id,
                title: 'Mortality',
                subtitle: `${event.number} · ${event.reason}`,
                timestamp: event.date,
                color: event.number >= 10 ? 'red' : 'amber',
            }
        })
}

export const buildBatchDetail = (
    batchID: string,
    lines: ZefishLine[],
    batches: ZefishBatch[],
    locations: ZefishLocation[]
): ZefishBatchDetail | null => {
    const batch = batches.find((entry) => entry.batchID === batchID)
    if (!batch) return null

    const line = lines.find((entry) => entry.lineID === batch.lineID)
    const location = locations.find((entry) => entry.locationId === batch.locationId)
    if (!line || !location) return null

    const linesById = new Map(lines.map((entry) => [entry.lineID, entry]))
    const locationsById = new Map(locations.map((entry) => [entry.locationId, entry]))
    const mortalityEvents = batch.events.filter((event): event is BatchMortalityEvent => event.type === 'mortality')

    return {
        batch: buildBatchView(batch, linesById, locationsById),
        line,
        location,
        timeline: buildBatchTimeline(batch),
        mortalityEvents: mortalityEvents.map((event): ZefishMortalityRecord => ({
            mortalityRecordID: event.id,
            batchID: batch.batchID,
            date: event.date,
            count: event.number,
            reason: event.reason,
            notes: null,
            recorder: 'Mock data',
        })),
    }
}

export const evaluateAlertRules = (
    systems: ZefishSystem[],
    sensorData: SensorRecord[],
    batches: ZefishBatch[]
): ZefishAlert[] => {
    const alerts: ZefishAlert[] = []

    systems.forEach((system) => {
        const records = sensorData
            .filter((record) => record.systemID === system.systemID)
            .sort((left, right) => new Date(right.timestamp).getTime() - new Date(left.timestamp).getTime())
        const latest = records[0]
        if (!latest) return

        if (latest.waterTemperature < 26 || latest.waterTemperature > 29) {
            alerts.push({
                alertID: `RULE-TEMP-${system.systemID}`,
                type: 'temperature outside range',
                severity: latest.waterTemperature > 30 ? 'CRITICAL' : 'WARNING',
                status: 'active',
                affectedEntityType: 'system',
                affectedEntityId: system.systemID,
                affectedLabel: `System ${system.systemID} · ${system.roomName}`,
                timestamp: latest.timestamp,
                recommendedAction: 'Check heater/chiller loop and verify calibration probe.',
            })
        }

        if (latest.filtrationStatus !== 'OK') {
            alerts.push({
                alertID: `RULE-FILTRATION-${system.systemID}`,
                type: 'system failure',
                severity: latest.filtrationStatus === 'FAILURE' ? 'CRITICAL' : 'WARNING',
                status: 'active',
                affectedEntityType: 'system',
                affectedEntityId: system.systemID,
                affectedLabel: `System ${system.systemID} filtration`,
                timestamp: latest.timestamp,
                recommendedAction: 'Inspect filtration module and switch to backup unit.',
            })
        }

        const minutesSinceUpdate = (Date.now() - new Date(latest.timestamp).getTime()) / 60_000
        if (minutesSinceUpdate > 120) {
            alerts.push({
                alertID: `RULE-SENSOR-${system.systemID}`,
                type: 'sensor missing data',
                severity: 'WARNING',
                status: 'active',
                affectedEntityType: 'sensor',
                affectedEntityId: `SENSOR-${system.systemID}`,
                affectedLabel: `Telemetry stream ${system.systemID}`,
                timestamp: latest.timestamp,
                recommendedAction: 'Verify gateway and reconnect telemetry node.',
            })
        }
    })

    batches.forEach((batch) => {
        const mortality = getMortalityCount(batch)
        const ratio = batch.initialCount > 0 ? mortality / batch.initialCount : 0
        if (ratio >= 0.18) {
            alerts.push({
                alertID: `RULE-MORTALITY-${batch.batchID}`,
                type: 'high mortality in batch',
                severity: ratio >= 0.25 ? 'CRITICAL' : 'WARNING',
                status: 'active',
                affectedEntityType: 'batch',
                affectedEntityId: batch.batchID,
                affectedLabel: `Batch ${batch.batchID}`,
                timestamp: batch.events[batch.events.length - 1]?.date ?? batch.birthDate,
                recommendedAction: 'Initiate veterinary review and correlate with room and water parameters.',
            })
        }
    })

    return alerts
}

export const buildSystemSnapshots = (
    systems: ZefishSystem[],
    batches: ZefishBatch[],
    alerts: ZefishAlert[]
): ZefishSystemSnapshot[] => {
    return systems.map((system) => {
        const relatedBatches = batches.filter((batch) => batch.systemID === system.systemID)
        const activeAlerts = alerts.filter(
            (alert) =>
                alert.status === 'active' &&
                (alert.affectedEntityId === system.systemID || relatedBatches.some((batch) => batch.batchID === alert.affectedEntityId))
        )

        return {
            ...system,
            activeBatches: relatedBatches.length,
            fishCount: relatedBatches.reduce((sum, batch) => sum + batch.individualCount, 0),
            activeAlerts: activeAlerts.length,
            latestObservationAt: null,
            temperatureTrend: [],
            pHTrend: [],
        }
    })
}

export const buildRoomSnapshots = (
    rooms: ZefishRoom[],
    roomRecords: Array<{
        roomID: string
        timestamp: string
        roomTemperature: number
        humidity: number
        lightCycle: string
        noiseLevel: number
    }>,
    batches: ZefishBatch[]
): RoomSnapshot[] => {
    const thresholdDate = Date.now() - 30 * DAY_MS

    return rooms.map((room) => {
        const latest = roomRecords
            .filter((record) => record.roomID === room.roomID)
            .sort((left, right) => new Date(right.timestamp).getTime() - new Date(left.timestamp).getTime())[0]

        const roomBatches = batches.filter((batch) => batch.roomID === room.roomID)
        const mortality30d = roomBatches
            .flatMap((batch) => batch.events)
            .filter((event): event is BatchMortalityEvent => event.type === 'mortality')
            .filter((event) => new Date(event.date).getTime() >= thresholdDate)
            .reduce((sum, event) => sum + event.number, 0)

        const breedingEvents30d = roomBatches
            .flatMap((batch) => batch.events)
            .filter((event) => event.type === 'birth')
            .filter((event) => new Date(event.date).getTime() >= thresholdDate).length

        return {
            room,
            roomTemperature: latest?.roomTemperature ?? 0,
            humidity: latest?.humidity ?? 0,
            lightCycle: latest?.lightCycle ?? room.lightCycle,
            noiseLevel: latest?.noiseLevel ?? 0,
            mortality30d,
            breedingEvents30d,
            latestObservationAt: latest?.timestamp ?? null,
            temperatureTrend: [],
            pHTrend: [],
        }
    })
}

export const buildSystemHistory = (systemID: string, sensorData: SensorRecord[]): SystemHistoryPoint[] => {
    return sensorData
        .filter((record) => record.systemID === systemID)
        .sort((left, right) => new Date(left.timestamp).getTime() - new Date(right.timestamp).getTime())
        .map((record) => ({
            timestamp: record.timestamp,
            temperature: record.waterTemperature,
            pH: record.pH,
        }))
}

export const buildLocationExplorer = (
    facility: string,
    locations: ZefishLocation[],
    lines: ZefishLine[],
    batches: ZefishBatch[],
    alerts: ZefishAlert[]
): LocationExplorerData => {
    const linesById = new Map(lines.map((line) => [line.lineID, line]))
    const locationsById = new Map(locations.map((location) => [location.locationId, location]))
    const activeAlerts = alerts.filter((alert) => alert.status === 'active')

    const tanks: TankSnapshot[] = locations.map((location) => {
        const batch = batches.find((entry) => entry.locationId === location.locationId) ?? null
        const batchView = batch ? buildBatchView(batch, linesById, locationsById) : null
        const tankAlerts = activeAlerts.filter(
            (alert) =>
                alert.affectedEntityId === location.locationId ||
                alert.affectedEntityId === location.systemID ||
                (batch ? alert.affectedEntityId === batch.batchID : false)
        )

        return {
            location,
            batch: batchView,
            alerts: tankAlerts,
        }
    })

    const rooms: LocationRoomNode[] = Array.from(new Set(locations.map((location) => location.roomID))).map((roomID) => {
        const roomLocations = locations.filter((location) => location.roomID === roomID)
        const roomName = roomLocations[0]?.roomName ?? roomID

        const systems: LocationSystemNode[] = Array.from(new Set(roomLocations.map((location) => location.systemID))).map(
            (systemID) => {
                const systemLocations = roomLocations.filter((location) => location.systemID === systemID)
                const racks: LocationRackNode[] = Array.from(new Set(systemLocations.map((location) => location.rack))).map((rack) => ({
                    rack,
                    tanks: tanks.filter((tank) => tank.location.systemID === systemID && tank.location.rack === rack),
                }))

                return {
                    systemID,
                    roomID,
                    roomName,
                    racks,
                }
            }
        )

        return {
            roomID,
            roomName,
            systems,
        }
    })

    return {
        facility,
        rooms,
    }
}

export const buildOverview = (
    lines: ZefishLine[],
    batches: ZefishBatch[],
    alerts: ZefishAlert[],
    sensorData: SensorRecord[]
): ZefixOverview => {
    const linesSummary = deriveLineSummaries(lines, batches)
    const totalFish = batches.reduce((sum, batch) => sum + batch.individualCount, 0)
    const activeAlerts = alerts.filter((alert) => alert.status === 'active').length

    const todayStart = startOfDay(new Date()).getTime()
    const todaysMortalities = batches
        .flatMap((batch) => batch.events)
        .filter((event): event is BatchMortalityEvent => event.type === 'mortality')
        .filter((event) => new Date(event.date).getTime() >= todayStart)
        .reduce((sum, event) => sum + event.number, 0)

    const mortalityTrend = Array.from({ length: 14 }).map((_, index) => {
        const date = new Date(Date.now() - (13 - index) * DAY_MS)
        const dayStart = startOfDay(date).getTime()
        const dayEnd = dayStart + DAY_MS
        const mortalities = batches
            .flatMap((batch) => batch.events)
            .filter((event): event is BatchMortalityEvent => event.type === 'mortality')
            .filter((event) => {
                const eventTs = new Date(event.date).getTime()
                return eventTs >= dayStart && eventTs < dayEnd
            })
            .reduce((sum, event) => sum + event.number, 0)

        return {
            date: date.toISOString(),
            mortalities,
        }
    })

    const temperatureTrend = buildSystemHistory('SYS-02', sensorData).slice(-18).map((point) => ({
        timestamp: point.timestamp,
        temperature: point.temperature,
    }))

    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']
    const breedingOutput = monthNames.map((period, index) => ({
        period,
        births: 6 + ((index + batches.length) % 5),
    }))

    return {
        totalLines: linesSummary.filter((line) => line.reproductionStatus !== 'Paused').length,
        totalBatches: batches.length,
        totalFish,
        todaysMortalities,
        activeAlerts,
        populationByLine: linesSummary.map((line) => ({ line: line.name, fish: line.totalAnimals })),
        mortalityTrend,
        temperatureTrend,
        breedingOutput,
    }
}

export const asLineStatusVariant = (status: FishPopulation['reproductionStatus']) => {
    if (status === 'Breeding') return 'success'
    if (status === 'Watch') return 'warning'
    if (status === 'Paused') return 'critical'
    return 'outline'
}

export const asAlertSeverityVariant = (severity: ZefishAlert['severity']) => {
    if (severity === 'CRITICAL') return 'critical'
    if (severity === 'WARNING') return 'warning'
    if (severity === 'INFO') return 'success'
    return 'outline'
}

export const asAlertStatusVariant = (status: ZefishAlert['status']) => {
    if (status === 'active') return 'critical'
    if (status === 'acknowledged') return 'warning'
    if (status === 'resolved') return 'success'
    return 'outline'
}

export const sortAlertsByRecent = (alerts: ZefishAlert[]) =>
    alerts
        .slice()
        .sort((left, right) => new Date(right.timestamp).getTime() - new Date(left.timestamp).getTime())

export const getActiveAlerts = (alerts: ZefishAlert[]) => alerts.filter((alert) => alert.status === 'active')

export const getActiveAlertsForEntityIds = (alerts: ZefishAlert[], entityIds: string[]) => {
    const ids = new Set(entityIds.filter((id): id is string => Boolean(id)))
    const matching = new Map<string, ZefishAlert>()

    getActiveAlerts(alerts).forEach((alert) => {
        if (ids.has(alert.affectedEntityId)) {
            matching.set(alert.alertID, alert)
        }
    })

    return sortAlertsByRecent(Array.from(matching.values()))
}

export const countActiveAlertsForEntityIds = (alerts: ZefishAlert[], entityIds: string[]) =>
    getActiveAlertsForEntityIds(alerts, entityIds).length

