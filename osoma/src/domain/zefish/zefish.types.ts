export type ReproductionStatus = 'Breeding' | 'Stable' | 'Watch' | 'Paused'
export type ZefishCryoStatus = 'stored' | 'used' | 'discarded'

export interface ZefishLine {
    lineID: string
    name: string
    genotype: string
    origin: string
    description: string
    creationDate: string
    responsibleScientist: string
}

export interface ZefishLineIndicators {
    totalAnimals: number
    activeBatches: number
    mortalityRate: number
    reproductionStatus: ReproductionStatus
}

export interface ZefishLineSummary extends ZefishLine, ZefishLineIndicators { }

export interface ZefishCryoRecord {
    cryoRecordID: string
    lineID: string
    storageDate: string
    storageLocation: string
    protocolUsed: string
    status: ZefishCryoStatus
    notes?: string | null
    createdAt?: string
}

export interface ZefishCryoRecordCreatePayload {
    lineID: string
    storageDate: string
    storageLocation: string
    protocolUsed: string
    status: ZefishCryoStatus
    notes?: string | null
}

export type MortalityReason =
    | 'natural'
    | 'disease'
    | 'experimental endpoint'
    | 'unknown'
    | 'system issue'

export type EnvironmentalScope = 'system' | 'room'
export type EnvironmentalMetric = 'temperature' | 'pH'
export type EnvironmentalSource = 'manual' | 'connected'

export interface BatchSexRatio {
    male: number
    female: number
    unknown: number
}

export interface BatchBirthEvent {
    id: string
    type: 'birth'
    date: string
    count: number
}

export interface BatchTransferEvent {
    id: string
    type: 'transfer'
    date: string
    count: number
    fromLocationId: string
    toLocationId: string
}

export interface BatchMortalityEvent {
    id: string
    type: 'mortality'
    date: string
    number: number
    reason: MortalityReason
}

export type BatchEvent = BatchBirthEvent | BatchTransferEvent | BatchMortalityEvent

export interface ZefishMortalityRecord {
    mortalityRecordID?: string
    id?: string
    batchID: string
    date: string
    count?: number
    number?: number
    reason?: MortalityReason
    category?: MortalityReason
    notes?: string | null
    recorder?: string | null
}

export interface ZefishBatch {
    batchID: string
    lineID: string
    birthDate: string
    sexRatio: BatchSexRatio
    initialCount: number
    individualCount: number
    locationId: string
    systemID: string
    roomID: string
    events: BatchEvent[]
}

export interface ZefishBatchView extends ZefishBatch {
    lineName: string
    roomName: string
    tankLocation: string
    ageDays: number
    ageLabel: string
    mortalityCount: number
}

export type ZefishBatchLifecycleStatus = 'active' | 'quarantined' | 'completed' | 'archived'

export interface ZefishBatchCreatePayload {
    line: string
    pool: string
    birthDate: string
    maleCount: number
    femaleCount: number
    unknownSexCount: number
    totalPopulation: number
    lifecycleStatus?: ZefishBatchLifecycleStatus
}

export interface ZefishLocation {
    locationId: string
    facility: string
    roomID: string
    roomName: string
    systemID: string
    rack: string
    tank: string
}

export interface ZefishSystem {
    systemID: string
    manufacturer: string
    model: string
    roomID: string
    roomName: string
    waterTemperature: number
    pH: number
    conductivity: number
    oxygen: number
    waterFlow: number
    filtrationStatus: 'OK' | 'MAINTENANCE' | 'FAILURE'
}

export interface SensorRecord {
    recordID: string
    systemID: string
    timestamp: string
    waterTemperature: number
    pH: number
    conductivity: number
    oxygen: number
    waterFlow: number
    filtrationStatus: 'OK' | 'MAINTENANCE' | 'FAILURE'
}

export interface ZefishRoom {
    roomID: string
    roomName: string
    facility: string
    lightCycle: string
}

export interface RoomEnvironmentRecord {
    recordID: string
    roomID: string
    timestamp: string
    roomTemperature: number
    humidity: number
    lightCycle: string
    noiseLevel: number
}

export type AlertSeverity = 'INFO' | 'WARNING' | 'CRITICAL'
export type AlertStatus = 'active' | 'acknowledged' | 'resolved'

export interface ZefishAlert {
    alertID: string
    type: string
    severity: AlertSeverity
    status: AlertStatus
    affectedEntityType: 'batch' | 'system' | 'room' | 'sensor' | 'tank'
    affectedEntityId: string
    affectedLabel: string
    timestamp: string
    recommendedAction: string
    acknowledgedAt?: string | null
    resolvedAt?: string | null
}

export interface FishPopulation {
    lineID: string
    totalAnimals: number
    activeBatches: number
    mortalityRate: number
    reproductionStatus: ReproductionStatus
}

export interface BatchTimelinePoint {
    id: string
    title: string
    subtitle: string
    timestamp: string
    color: 'blue' | 'green' | 'amber' | 'red' | 'slate' | 'indigo' | 'purple'
}

export interface ZefishBatchDetail {
    batch: ZefishBatchView
    line: ZefishLine
    location: ZefishLocation
    timeline: BatchTimelinePoint[]
    mortalityEvents: ZefishMortalityRecord[]
}

export interface ZefishLineDetail {
    line: ZefishLineSummary
    batches: ZefishBatchView[]
}

export interface SystemHistoryPoint {
    timestamp: string
    temperature: number
    pH: number
}

export interface EnvironmentalObservation {
    observationID?: string
    id?: string
    scopeType?: EnvironmentalScope
    scopeID?: string
    systemID?: string
    roomID?: string
    metric?: EnvironmentalMetric
    metricType?: EnvironmentalMetric
    value?: number
    timestamp: string
    source?: EnvironmentalSource
    notes?: string | null
    recorder?: string | null
}

export interface EnvironmentalTrendPoint {
    timestamp: string
    temperature?: number | null
    pH?: number | null
    source?: EnvironmentalSource | null
}

export interface ZefishSystemSnapshot extends ZefishSystem {
    activeBatches: number
    fishCount: number
    activeAlerts: number
    latestObservationAt?: string | null
    temperatureTrend?: EnvironmentalTrendPoint[]
    pHTrend?: EnvironmentalTrendPoint[]
}

export interface RoomSnapshot {
    room: ZefishRoom
    roomTemperature: number
    humidity: number
    lightCycle: string
    noiseLevel: number
    mortality30d: number
    breedingEvents30d: number
    latestObservationAt?: string | null
    temperatureTrend?: EnvironmentalTrendPoint[]
    pHTrend?: EnvironmentalTrendPoint[]
}

export interface TankSnapshot {
    location: ZefishLocation
    batch: ZefishBatchView | null
    alerts: ZefishAlert[]
}

export interface LocationRackNode {
    rack: string
    tanks: TankSnapshot[]
}

export interface LocationSystemNode {
    systemID: string
    roomID: string
    roomName: string
    racks: LocationRackNode[]
}

export interface LocationRoomNode {
    roomID: string
    roomName: string
    systems: LocationSystemNode[]
}

export interface LocationExplorerData {
    facility: string
    rooms: LocationRoomNode[]
}

export interface ZefixLocationExplorerBatch {
    batchId: string
    lineId: string
    lineName: string
    birthDate: string
    totalPopulation: number
    lifecycleStatus: ZefishBatchLifecycleStatus
}

export interface ZefixLocationExplorerPool {
    poolId: string
    position: string
    capacity: number
    status: 'available' | 'occupied' | 'maintenance' | 'inactive'
    occupied: boolean
    batch: ZefixLocationExplorerBatch | null
}

export interface ZefixLocationExplorerRack {
    rackId: string
    code: string
    systemId?: string | null
    pools: ZefixLocationExplorerPool[]
}

export interface ZefixLocationExplorerRoom {
    roomId: string
    name: string
    racks: ZefixLocationExplorerRack[]
}

export interface ZefixLocationExplorerPlateau {
    plateauId: string
    name: string
    code: string
    rooms: ZefixLocationExplorerRoom[]
}

export interface ZefixLocationExplorerResponse {
    facility: string
    plateaux: ZefixLocationExplorerPlateau[]
}

export interface ZefixOverview {
    totalLines: number
    totalBatches: number
    totalFish: number
    todaysMortalities: number
    activeAlerts: number
    populationByLine: Array<{ line: string; fish: number }>
    mortalityTrend: Array<{ date: string; mortalities: number }>
    temperatureTrend: Array<{ timestamp: string; temperature: number }>
    breedingOutput: Array<{ period: string; births: number }>
}
