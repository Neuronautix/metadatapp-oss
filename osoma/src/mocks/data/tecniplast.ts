import type {
  Alarm,
  AnimalSubject,
  Cage,
  ConditionMetric,
  ConditionThreshold,
  Facility,
  Rack,
  Room,
  Sensor,
  Technician,
  WorkOrder,
} from '@/features/integrations/tecniplast/tecniplast.types.ts'
import type { ScopeType } from '@/domain/scope.ts'

export const facilities: Facility[] = [
  {
    id: 'FAC-NE-01',
    name: 'North Campus Vivarium',
    location: 'Cambridge, MA',
    timezone: 'America/New_York',
    status: 'active',
    roomCount: 4,
  },
  {
    id: 'FAC-WC-02',
    name: 'West Clinical Barrier',
    location: 'San Diego, CA',
    timezone: 'America/Los_Angeles',
    status: 'maintenance',
    roomCount: 3,
  },
]

export const rooms: Room[] = [
  { id: 'RM-101', facilityId: 'FAC-NE-01', name: 'Room 101', roomType: 'barrier', rackCount: 3, status: 'operational' },
  { id: 'RM-102', facilityId: 'FAC-NE-01', name: 'Room 102', roomType: 'standard', rackCount: 2, status: 'operational' },
  { id: 'RM-201', facilityId: 'FAC-NE-01', name: 'Room 201', roomType: 'quarantine', rackCount: 1, status: 'maintenance' },
  { id: 'RM-301', facilityId: 'FAC-WC-02', name: 'Room 301', roomType: 'barrier', rackCount: 2, status: 'operational' },
  { id: 'RM-302', facilityId: 'FAC-WC-02', name: 'Room 302', roomType: 'standard', rackCount: 2, status: 'operational' },
  { id: 'RM-401', facilityId: 'FAC-WC-02', name: 'Room 401', roomType: 'standard', rackCount: 1, status: 'operational' },
]

export const racks: Rack[] = [
  { id: 'RK-101-A', roomId: 'RM-101', label: 'Rack A', row: 'A', position: 'North wall', cageCount: 4, status: 'operational' },
  { id: 'RK-101-B', roomId: 'RM-101', label: 'Rack B', row: 'B', position: 'North wall', cageCount: 4, status: 'operational' },
  { id: 'RK-101-C', roomId: 'RM-101', label: 'Rack C', row: 'C', position: 'South wall', cageCount: 4, status: 'operational' },
  { id: 'RK-102-A', roomId: 'RM-102', label: 'Rack A', row: 'A', position: 'East wall', cageCount: 4, status: 'operational' },
  { id: 'RK-102-B', roomId: 'RM-102', label: 'Rack B', row: 'B', position: 'East wall', cageCount: 4, status: 'operational' },
  { id: 'RK-201-A', roomId: 'RM-201', label: 'Rack A', row: 'A', position: 'Quarantine zone', cageCount: 3, status: 'maintenance' },
  { id: 'RK-301-A', roomId: 'RM-301', label: 'Rack A', row: 'A', position: 'West wall', cageCount: 4, status: 'operational' },
  { id: 'RK-301-B', roomId: 'RM-301', label: 'Rack B', row: 'B', position: 'West wall', cageCount: 4, status: 'operational' },
  { id: 'RK-302-A', roomId: 'RM-302', label: 'Rack A', row: 'A', position: 'South wall', cageCount: 4, status: 'operational' },
  { id: 'RK-302-B', roomId: 'RM-302', label: 'Rack B', row: 'B', position: 'South wall', cageCount: 4, status: 'operational' },
  { id: 'RK-401-A', roomId: 'RM-401', label: 'Rack A', row: 'A', position: 'East wall', cageCount: 3, status: 'operational' },
]

const cageStatuses: Cage['status'][] = ['occupied', 'occupied', 'occupied', 'empty', 'quarantine', 'cleaning']

export const cages: Cage[] = racks.flatMap((rack, rackIndex) => {
  const count = rack.cageCount
  return Array.from({ length: count }).map((_, index) => {
    const number = String(index + 1).padStart(2, '0')
    return {
      id: `CG-${rack.id}-${number}`,
      rackId: rack.id,
      label: `${rack.label}-${number}`,
      status: cageStatuses[(rackIndex + index) % cageStatuses.length],
      occupancy: (rackIndex + index) % 5,
      species: rackIndex % 2 === 0 ? 'Mouse' : 'Rat',
      lastCleanedAt: new Date(Date.now() - (index + 2) * 86400000).toISOString(),
    }
  })
})

export const subjects: AnimalSubject[] = cages.flatMap((cage, cageIndex) => {
  const count = Math.max(0, Math.min(5, cage.occupancy))
  return Array.from({ length: count }).map((_, index) => ({
    id: `SUB-${cage.id}-${index + 1}`,
    cageId: cage.id,
    label: `${cage.label}-S${index + 1}`,
    species: cage.species,
    sex: index % 2 === 0 ? 'female' : 'male',
    strain: cageIndex % 2 === 0 ? 'C57BL/6J' : 'Sprague-Dawley',
    status: cage.status === 'quarantine' ? 'quarantine' : 'active',
    dob: new Date(Date.now() - (120 + index * 10) * 86400000).toISOString(),
  }))
})

export const technicians: Technician[] = [
  { id: 'TECH-01', name: 'Maya Ortiz', role: 'Lead Technician', facilityId: 'FAC-NE-01' },
  { id: 'TECH-02', name: 'Arjun Patel', role: 'Animal Care', facilityId: 'FAC-NE-01' },
  { id: 'TECH-03', name: 'Sofia Marin', role: 'Facilities Ops', facilityId: 'FAC-WC-02' },
]

const metrics: ConditionMetric[] = ['temperature', 'humidity', 'pressure', 'co2', 'light', 'noise']

const metricUnits: Record<ConditionMetric, string> = {
  temperature: 'C',
  humidity: '%',
  pressure: 'hPa',
  co2: 'ppm',
  light: 'lux',
  noise: 'dB',
}

export const defaultThresholds: Record<ConditionMetric, { min: number; max: number }> = {
  temperature: { min: 20, max: 26 },
  humidity: { min: 40, max: 60 },
  pressure: { min: 990, max: 1030 },
  co2: { min: 400, max: 1200 },
  light: { min: 20, max: 140 },
  noise: { min: 30, max: 60 },
}

export const outOfRangeScopes = new Set<string>(['RM-201', 'RK-301-B', 'CG-RK-101-B-03', 'CG-RK-302-A-04'])

const buildSensorsForScope = (
  scopeType: ScopeType,
  scopeId: string,
  scopeLabel: string,
  includeAll = false
): Sensor[] => {
  const scopeMetrics = includeAll ? metrics : metrics.filter((metric) => ['temperature', 'humidity', 'co2'].includes(metric))
  return scopeMetrics.map((metric, index) => ({
    id: `SNS-${scopeId}-${metric}`,
    scopeType,
    scopeId,
    scopeLabel,
    metric,
    status: index === 2 && scopeId.endsWith('1') ? 'offline' : 'online',
    lastSeen: new Date(Date.now() - (index === 2 && scopeId.endsWith('1') ? 26 : 1) * 3600000).toISOString(),
    battery: 60 + ((scopeId.length + index) % 30),
    calibrationDueAt: new Date(Date.now() + (index + 2) * 86400000 * 20).toISOString(),
  }))
}

export const sensors: Sensor[] = [
  ...rooms.flatMap((room) => buildSensorsForScope('room', room.id, room.name, true)),
  ...racks.flatMap((rack) => buildSensorsForScope('rack', rack.id, rack.label)),
  ...cages.flatMap((cage) => buildSensorsForScope('cage', cage.id, cage.label)),
]

export const alarms: Alarm[] = [
  {
    id: 'ALM-1001',
    severity: 'critical',
    status: 'active',
    scopeType: 'room',
    scopeId: 'RM-201',
    scopeLabel: 'Room 201',
    sensorType: 'temperature',
    message: 'Temperature exceeded upper threshold (28.7 C).',
    createdAt: new Date(Date.now() - 45 * 60000).toISOString(),
    updatedAt: new Date(Date.now() - 30 * 60000).toISOString(),
    correlationId: 'CORR-TP-1001',
  },
  {
    id: 'ALM-1002',
    severity: 'high',
    status: 'acknowledged',
    scopeType: 'rack',
    scopeId: 'RK-301-B',
    scopeLabel: 'Rack B',
    sensorType: 'co2',
    message: 'CO2 rising above 1100 ppm. Ventilation check required.',
    createdAt: new Date(Date.now() - 3 * 3600000).toISOString(),
    updatedAt: new Date(Date.now() - 2 * 3600000).toISOString(),
    acknowledgedAt: new Date(Date.now() - 110 * 60000).toISOString(),
    assignedTo: 'TECH-03',
    correlationId: 'CORR-TP-1002',
  },
  {
    id: 'ALM-1003',
    severity: 'medium',
    status: 'active',
    scopeType: 'cage',
    scopeId: 'CG-RK-101-B-03',
    scopeLabel: 'Rack B-03',
    sensorType: 'humidity',
    message: 'Humidity below minimum threshold (34%).',
    createdAt: new Date(Date.now() - 90 * 60000).toISOString(),
    updatedAt: new Date(Date.now() - 70 * 60000).toISOString(),
    correlationId: 'CORR-TP-1003',
  },
  {
    id: 'ALM-1004',
    severity: 'low',
    status: 'resolved',
    scopeType: 'cage',
    scopeId: 'CG-RK-302-A-04',
    scopeLabel: 'Rack A-04',
    sensorType: 'temperature',
    message: 'Temperature spike resolved after HVAC cycle.',
    createdAt: new Date(Date.now() - 12 * 3600000).toISOString(),
    updatedAt: new Date(Date.now() - 10 * 3600000).toISOString(),
    resolvedAt: new Date(Date.now() - 10 * 3600000).toISOString(),
    correlationId: 'CORR-TP-1004',
  },
]

export const workOrders: WorkOrder[] = [
  {
    id: 'WO-2001',
    facilityId: 'FAC-NE-01',
    scopeType: 'room',
    scopeId: 'RM-101',
    scopeLabel: 'Room 101',
    title: 'Daily barrier wipe-down',
    dueAt: new Date(Date.now() + 2 * 3600000).toISOString(),
    status: 'open',
    assignedTo: 'TECH-01',
  },
  {
    id: 'WO-2002',
    facilityId: 'FAC-NE-01',
    scopeType: 'cage',
    scopeId: 'CG-RK-101-A-02',
    scopeLabel: 'Rack A-02',
    title: 'Cage changeout (breeding pair)',
    dueAt: new Date(Date.now() + 6 * 3600000).toISOString(),
    status: 'in-progress',
    assignedTo: 'TECH-02',
  },
  {
    id: 'WO-2003',
    facilityId: 'FAC-WC-02',
    scopeType: 'room',
    scopeId: 'RM-301',
    scopeLabel: 'Room 301',
    title: 'HVAC filter inspection',
    dueAt: new Date(Date.now() + 24 * 3600000).toISOString(),
    status: 'open',
    assignedTo: 'TECH-03',
  },
]

export const thresholdsByScope = new Map<string, ConditionThreshold[]>()

export const getThresholdsForScope = (scopeType: ScopeType, scopeId: string) => {
  const key = `${scopeType}:${scopeId}`
  if (!thresholdsByScope.has(key)) {
    const thresholds = metrics.map((metric) => ({
      metric,
      min: defaultThresholds[metric].min,
      max: defaultThresholds[metric].max,
      unit: metricUnits[metric],
    }))
    thresholdsByScope.set(key, thresholds)
  }
  return thresholdsByScope.get(key) ?? []
}

export const metricUnitMap = metricUnits
