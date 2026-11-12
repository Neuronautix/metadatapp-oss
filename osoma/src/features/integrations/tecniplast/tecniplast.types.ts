import type { ScopeType } from '@/domain/scope.ts'

export type FacilityStatus = 'active' | 'maintenance'

export type Facility = {
  id: string
  name: string
  location: string
  timezone: string
  status: FacilityStatus
  roomCount: number
  stats?: FacilityStats
}

export type FacilityStats = {
  activeAlarms: number
  cagesOutOfRange: number
  tasksDue: number
  sensorsOffline: number
}

export type Room = {
  id: string
  facilityId: string
  name: string
  roomType: 'barrier' | 'standard' | 'quarantine'
  rackCount: number
  status: 'operational' | 'maintenance'
}

export type Rack = {
  id: string
  roomId: string
  label: string
  row: string
  position: string
  cageCount: number
  status: 'operational' | 'maintenance'
}

export type Cage = {
  id: string
  rackId: string
  label: string
  status: 'occupied' | 'empty' | 'quarantine' | 'cleaning'
  occupancy: number
  species: string
  lastCleanedAt: string
}

export type AnimalSubject = {
  id: string
  cageId: string
  label: string
  species: string
  sex: 'male' | 'female'
  strain: string
  status: 'active' | 'quarantine' | 'retired'
  dob: string
}

export type Technician = {
  id: string
  name: string
  role: string
  facilityId: string
}

export type AlarmSeverity = 'critical' | 'high' | 'medium' | 'low'
export type AlarmStatus = 'active' | 'acknowledged' | 'resolved'
export type Alarm = {
  id: string
  severity: AlarmSeverity
  status: AlarmStatus
  scopeType: ScopeType
  scopeId: string
  scopeLabel: string
  sensorType: ConditionMetric
  message: string
  createdAt: string
  updatedAt: string
  acknowledgedAt?: string
  assignedTo?: string
  resolvedAt?: string
  correlationId: string
}

export type WorkOrderStatus = 'open' | 'in-progress' | 'done'
export type WorkOrder = {
  id: string
  facilityId: string
  scopeType: ScopeType
  scopeId: string
  scopeLabel: string
  title: string
  dueAt: string
  status: WorkOrderStatus
  assignedTo?: string
}

export type SensorStatus = 'online' | 'offline'
export type Sensor = {
  id: string
  scopeType: ScopeType
  scopeId: string
  scopeLabel: string
  metric: ConditionMetric
  status: SensorStatus
  lastSeen: string
  battery: number
  calibrationDueAt: string
}

export type ConditionMetric = 'temperature' | 'humidity' | 'pressure' | 'co2' | 'light' | 'noise'

export type ConditionThreshold = {
  metric: ConditionMetric
  min: number
  max: number
  unit: string
}

export type ConditionSnapshotMetric = ConditionThreshold & {
  value: number
  status: 'ok' | 'out-of-range'
}

export type ConditionSnapshot = {
  scopeType: ScopeType
  scopeId: string
  collectedAt: string
  at?: string
  mode?: 'reality' | 'scenario'
  scenarioId?: string
  scenarioLabel?: string
  thresholds?: ConditionThreshold[]
  alarms?: Alarm[]
  metrics: ConditionSnapshotMetric[]
}

export type ConditionReading = {
  at: string
  value: number
}

export type ConditionReadings = {
  scopeType: ScopeType
  scopeId: string
  metric: ConditionMetric
  unit: string
  points: ConditionReading[]
}
