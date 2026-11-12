import { http, HttpResponse } from 'msw'
import {
  alarms,
  cages,
  facilities,
  getThresholdsForScope,
  metricUnitMap,
  outOfRangeScopes,
  racks,
  rooms,
  sensors,
  subjects,
  thresholdsByScope,
  workOrders,
} from '@/mocks/data/tecniplast.ts'
import { findScenario } from '@/mocks/data/scenarios.ts'
import type { ScenarioOverride } from '@/domain/scenario/scenario.types.ts'
import { applyThresholdOverrides, buildScenarioSnapshot, computeMetricValue } from '@/domain/scenario/state-snapshot.ts'
import type {
  Alarm,
  AlarmSeverity,
  AlarmStatus,
  ConditionMetric,
  ConditionReadings,
  ConditionThreshold,
} from '@/features/integrations/tecniplast/tecniplast.types.ts'
import type { ScopeType } from '@/domain/scope.ts'
import { matches, paginate } from './utils.ts'

const latencyProfiles: Record<string, [number, number]> = {
  fast: [80, 180],
  normal: [220, 620],
  slow: [820, 1400],
}

const withLatencyProfile = async (profile?: string) => {
  const [min, max] = latencyProfiles[profile ?? 'normal'] ?? latencyProfiles.normal
  await new Promise((resolve) => setTimeout(resolve, min + Math.random() * (max - min)))
}

const maybeError = (force?: string | null) => {
  return force === 'true'
}

const roomById = new Map(rooms.map((room) => [room.id, room]))
const rackById = new Map(racks.map((rack) => [rack.id, rack]))
const cageById = new Map(cages.map((cage) => [cage.id, cage]))

const facilityForScope = (scopeType: ScopeType, scopeId: string) => {
  if (scopeType === 'room') return roomById.get(scopeId)?.facilityId
  if (scopeType === 'rack') return roomById.get(rackById.get(scopeId)?.roomId ?? '')?.facilityId
  if (scopeType === 'cage') return roomById.get(rackById.get(cageById.get(scopeId)?.rackId ?? '')?.roomId ?? '')?.facilityId
  return undefined
}

const severityRank: Record<AlarmSeverity, number> = {
  critical: 4,
  high: 3,
  medium: 2,
  low: 1,
}

const statusRank: Record<AlarmStatus, number> = {
  active: 3,
  acknowledged: 2,
  resolved: 1,
}

const resolveScenarioContext = (url: URL, scopeType: ScopeType, scopeId: string) => {
  const scenarioId = url.searchParams.get('scenarioId')
  const atParam = url.searchParams.get('at')
  const scenario = scenarioId ? findScenario(scenarioId) : null
  const effectiveAt = atParam ?? scenario?.baseTimestamp ?? new Date().toISOString()
  const activeScenario = scenario && scenario.scope.id === scopeId && scenario.scope.type === scopeType ? scenario : null
  return { at: effectiveAt, scenario: activeScenario }
}

const computeFacilityStats = (facilityId: string) => {
  const activeAlarms = alarms.filter((alarm) => alarm.status === 'active' && facilityForScope(alarm.scopeType, alarm.scopeId) === facilityId).length
  const sensorsOffline = sensors.filter((sensor) => sensor.status === 'offline' && facilityForScope(sensor.scopeType, sensor.scopeId) === facilityId).length
  const tasksDue = workOrders.filter((order) => order.facilityId === facilityId && order.status !== 'done').length
  const cagesOutOfRange = cages.filter((cage) => outOfRangeScopes.has(cage.id) && facilityForScope('cage', cage.id) === facilityId).length
  return { activeAlarms, sensorsOffline, tasksDue, cagesOutOfRange }
}

const metricValue = (
  metric: ConditionMetric,
  scopeId: string,
  t: number,
  overrides?: ScenarioOverride
) =>
  computeMetricValue(metric, scopeId, t, {
    outOfRangeScopes,
    metricAdjustments: overrides?.metricAdjustments,
  })

const parseIntervalMinutes = (value?: string | null) => {
  if (!value) return 30
  if (value.endsWith('m')) return Number(value.replace('m', ''))
  if (value.endsWith('h')) return Number(value.replace('h', '')) * 60
  if (value.endsWith('d')) return Number(value.replace('d', '')) * 1440
  return Number(value) || 30
}

const buildReadings = (
  scopeType: ScopeType,
  scopeId: string,
  metric: ConditionMetric,
  from: number,
  to: number,
  intervalMinutes: number,
  overrides?: ScenarioOverride
): ConditionReadings => {
  const points = []
  for (let t = from; t <= to; t += intervalMinutes * 60000) {
    const value = metricValue(metric, scopeId, t, overrides)
    points.push({ at: new Date(t).toISOString(), value })
  }
  return {
    scopeType,
    scopeId,
    metric,
    unit: metricUnitMap[metric],
    points,
  }
}

export const tecniplastHandlers = [
  http.get('/api/tp/facilities', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Facility service unavailable.' }, { status: 500 })
    }
    const status = url.searchParams.get('status')
    const filtered = status ? facilities.filter((facility) => facility.status === status) : facilities
    return HttpResponse.json(filtered.map((facility) => ({ ...facility, stats: computeFacilityStats(facility.id) })))
  }),

  http.get('/api/tp/facilities/:id', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Facility unavailable.' }, { status: 500 })
    }
    const facility = facilities.find((item) => item.id === params.id)
    if (!facility) {
      return HttpResponse.json({ message: 'Facility not found.' }, { status: 404 })
    }
    return HttpResponse.json({ ...facility, stats: computeFacilityStats(facility.id) })
  }),

  http.get('/api/tp/rooms', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Room registry unavailable.' }, { status: 500 })
    }
    const facilityId = url.searchParams.get('facilityId')
    const filtered = facilityId ? rooms.filter((room) => room.facilityId === facilityId) : rooms
    return HttpResponse.json(filtered)
  }),

  http.get('/api/tp/rooms/:id', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Room unavailable.' }, { status: 500 })
    }
    const room = rooms.find((item) => item.id === params.id)
    if (!room) {
      return HttpResponse.json({ message: 'Room not found.' }, { status: 404 })
    }
    return HttpResponse.json(room)
  }),

  http.get('/api/tp/racks', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Rack registry unavailable.' }, { status: 500 })
    }
    const roomId = url.searchParams.get('roomId')
    const filtered = roomId ? racks.filter((rack) => rack.roomId === roomId) : racks
    return HttpResponse.json(filtered)
  }),

  http.get('/api/tp/racks/:id', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Rack unavailable.' }, { status: 500 })
    }
    const rack = racks.find((item) => item.id === params.id)
    if (!rack) {
      return HttpResponse.json({ message: 'Rack not found.' }, { status: 404 })
    }
    return HttpResponse.json(rack)
  }),

  http.get('/api/tp/cages', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Cage registry unavailable.' }, { status: 500 })
    }
    const rackId = url.searchParams.get('rackId')
    const filtered = rackId ? cages.filter((cage) => cage.rackId === rackId) : cages
    return HttpResponse.json(filtered)
  }),

  http.get('/api/tp/cages/:id', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Cage unavailable.' }, { status: 500 })
    }
    const cage = cages.find((item) => item.id === params.id)
    if (!cage) {
      return HttpResponse.json({ message: 'Cage not found.' }, { status: 404 })
    }
    return HttpResponse.json(cage)
  }),

  http.get('/api/tp/subjects', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Subject registry unavailable.' }, { status: 500 })
    }
    const cageId = url.searchParams.get('cageId')
    const filtered = cageId ? subjects.filter((subject) => subject.cageId === cageId) : subjects
    return HttpResponse.json(filtered)
  }),

  http.get('/api/tp/subjects/:id', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Subject unavailable.' }, { status: 500 })
    }
    const subject = subjects.find((item) => item.id === params.id)
    if (!subject) {
      return HttpResponse.json({ message: 'Subject not found.' }, { status: 404 })
    }
    return HttpResponse.json(subject)
  }),

  http.get('/api/tp/alarms', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Alarm stream unavailable.' }, { status: 500 })
    }
    const facilityId = url.searchParams.get('facilityId')
    const status = url.searchParams.get('status')
    const severity = url.searchParams.get('severity')
    let scopeType = url.searchParams.get('scopeType') as ScopeType | null
    let scopeId = url.searchParams.get('scopeId')
    const sensorType = url.searchParams.get('sensorType')
    const search = url.searchParams.get('q')
    const sortBy = url.searchParams.get('sortBy')
    const sortDir = url.searchParams.get('sortDir') ?? 'desc'
    const page = Number(url.searchParams.get('page') ?? 1)
    const pageSize = Number(url.searchParams.get('pageSize') ?? 8)
    const scenarioId = url.searchParams.get('scenarioId')
    const atParam = url.searchParams.get('at')
    const scenario = scenarioId ? findScenario(scenarioId) : null

    if (scenarioId && !scenario) {
      return HttpResponse.json({ message: 'Scenario not found.' }, { status: 404 })
    }

    if (scenario) {
      scopeType = scenario.scope.type as ScopeType
      scopeId = scenario.scope.id
    }

    const temporalMode = Boolean(scenario || atParam)

    if (temporalMode && scopeType && scopeId) {
      const { at } = resolveScenarioContext(url, scopeType, scopeId)
      const thresholds = getThresholdsForScope(scopeType, scopeId)
      const snapshot = buildScenarioSnapshot({
        scopeType,
        scopeId,
        scopeLabel: scenario?.scope.label,
        at,
        thresholds: scenario ? applyThresholdOverrides(thresholds, scenario.overrides.thresholds) : thresholds,
        baseAlarms: alarms,
        outOfRangeScopes,
        overrides: scenario?.overrides,
        mode: scenario ? 'scenario' : 'reality',
        scenarioId: scenario?.id ?? undefined,
        scenarioLabel: scenario?.label,
      })

      let computed: Alarm[] = [...snapshot.alarms]
      if (facilityId) {
        computed = computed.filter((alarm) => facilityForScope(alarm.scopeType, alarm.scopeId) === facilityId)
      }
      if (status) computed = computed.filter((alarm) => alarm.status === status)
      if (severity) computed = computed.filter((alarm) => alarm.severity === severity)
      if (scopeType) computed = computed.filter((alarm) => alarm.scopeType === scopeType)
      if (scopeId) computed = computed.filter((alarm) => alarm.scopeId === scopeId)
      if (sensorType) computed = computed.filter((alarm) => alarm.sensorType === sensorType)
      if (search) {
        computed = computed.filter((alarm) =>
          matches(alarm.message, search) || matches(alarm.scopeLabel, search) || matches(alarm.id, search)
        )
      }
      if (sortBy === 'severity') {
        computed.sort((a, b) =>
          sortDir === 'asc' ? severityRank[a.severity] - severityRank[b.severity] : severityRank[b.severity] - severityRank[a.severity]
        )
      } else if (sortBy === 'status') {
        computed.sort((a, b) =>
          sortDir === 'asc' ? statusRank[a.status] - statusRank[b.status] : statusRank[b.status] - statusRank[a.status]
        )
      } else {
        computed.sort((a, b) =>
          sortDir === 'asc'
            ? new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime()
            : new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()
        )
      }

      const { data, total } = paginate(computed, page, pageSize)
      return HttpResponse.json({ data, page, pageSize, total, scenarioId: scenario?.id ?? undefined, at })
    }

    let filtered: Alarm[] = [...alarms]

    if (facilityId) {
      filtered = filtered.filter((alarm) => facilityForScope(alarm.scopeType, alarm.scopeId) === facilityId)
    }

    if (status) filtered = filtered.filter((alarm) => alarm.status === status)
    if (severity) filtered = filtered.filter((alarm) => alarm.severity === severity)
    if (scopeType) filtered = filtered.filter((alarm) => alarm.scopeType === scopeType)
    if (scopeId) filtered = filtered.filter((alarm) => alarm.scopeId === scopeId)
    if (sensorType) filtered = filtered.filter((alarm) => alarm.sensorType === sensorType)

    if (search) {
      filtered = filtered.filter((alarm) =>
        matches(alarm.message, search) || matches(alarm.scopeLabel, search) || matches(alarm.id, search)
      )
    }

    if (sortBy === 'severity') {
      filtered.sort((a, b) => (sortDir === 'asc' ? severityRank[a.severity] - severityRank[b.severity] : severityRank[b.severity] - severityRank[a.severity]))
    } else if (sortBy === 'status') {
      filtered.sort((a, b) => (sortDir === 'asc' ? statusRank[a.status] - statusRank[b.status] : statusRank[b.status] - statusRank[a.status]))
    } else {
      filtered.sort((a, b) => (sortDir === 'asc' ? new Date(a.createdAt).getTime() - new Date(b.createdAt).getTime() : new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()))
    }

    const { data, total } = paginate(filtered, page, pageSize)

    return HttpResponse.json({ data, page, pageSize, total })
  }),

  http.get('/api/tp/alarms/:id', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Alarm unavailable.' }, { status: 500 })
    }
    const alarm = alarms.find((item) => item.id === params.id)
    if (!alarm) {
      return HttpResponse.json({ message: 'Alarm not found.' }, { status: 404 })
    }
    return HttpResponse.json(alarm)
  }),

  http.post('/api/tp/alarms/:id/ack', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    const alarm = alarms.find((item) => item.id === params.id)
    if (!alarm) {
      return HttpResponse.json({ message: 'Alarm not found.' }, { status: 404 })
    }
    alarm.status = 'acknowledged'
    alarm.acknowledgedAt = new Date().toISOString()
    alarm.updatedAt = new Date().toISOString()
    return HttpResponse.json(alarm)
  }),

  http.post('/api/tp/alarms/:id/assign', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    const alarm = alarms.find((item) => item.id === params.id)
    if (!alarm) {
      return HttpResponse.json({ message: 'Alarm not found.' }, { status: 404 })
    }
    const assignee = url.searchParams.get('assignee') ?? 'TECH-01'
    alarm.assignedTo = assignee
    alarm.updatedAt = new Date().toISOString()
    return HttpResponse.json(alarm)
  }),

  http.post('/api/tp/alarms/:id/resolve', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    const alarm = alarms.find((item) => item.id === params.id)
    if (!alarm) {
      return HttpResponse.json({ message: 'Alarm not found.' }, { status: 404 })
    }
    alarm.status = 'resolved'
    alarm.resolvedAt = new Date().toISOString()
    alarm.updatedAt = new Date().toISOString()
    return HttpResponse.json(alarm)
  }),

  http.get('/api/tp/sensors', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Sensor registry unavailable.' }, { status: 500 })
    }
    const scopeType = url.searchParams.get('scopeType')
    const scopeId = url.searchParams.get('scopeId')
    const facilityId = url.searchParams.get('facilityId')
    let filtered = [...sensors]
    if (scopeType) filtered = filtered.filter((sensor) => sensor.scopeType === scopeType)
    if (scopeId) filtered = filtered.filter((sensor) => sensor.scopeId === scopeId)
    if (facilityId) {
      filtered = filtered.filter((sensor) => facilityForScope(sensor.scopeType, sensor.scopeId) === facilityId)
    }
    return HttpResponse.json(filtered)
  }),

  http.get('/api/tp/sensors/:id', async ({ params, request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Sensor unavailable.' }, { status: 500 })
    }
    const sensor = sensors.find((item) => item.id === params.id)
    if (!sensor) {
      return HttpResponse.json({ message: 'Sensor not found.' }, { status: 404 })
    }
    return HttpResponse.json(sensor)
  }),

  http.get('/api/tp/conditions/snapshot', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Condition snapshot unavailable.' }, { status: 500 })
    }
    const scopeType = url.searchParams.get('scopeType') as ScopeType | null
    const scopeId = url.searchParams.get('scopeId')
    if (!scopeType || !scopeId) {
      return HttpResponse.json({ message: 'scopeType and scopeId required.' }, { status: 400 })
    }
    const { at, scenario } = resolveScenarioContext(url, scopeType, scopeId)
    const thresholds = getThresholdsForScope(scopeType, scopeId)
    const effectiveThresholds = scenario ? applyThresholdOverrides(thresholds, scenario.overrides.thresholds) : thresholds
    const snapshot = buildScenarioSnapshot({
      scopeType,
      scopeId,
      at,
      thresholds: effectiveThresholds,
      baseAlarms: alarms,
      outOfRangeScopes,
      overrides: scenario?.overrides,
      mode: scenario ? 'scenario' : 'reality',
      scenarioId: scenario?.id ?? undefined,
      scenarioLabel: scenario?.label,
    })
    return HttpResponse.json({ ...snapshot, collectedAt: snapshot.at })
  }),

  http.get('/api/tp/conditions/readings', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Condition readings unavailable.' }, { status: 500 })
    }
    const scopeType = url.searchParams.get('scopeType') as ScopeType | null
    const scopeId = url.searchParams.get('scopeId')
    const metric = url.searchParams.get('metric') as ConditionMetric | null
    const from = url.searchParams.get('from')
    const to = url.searchParams.get('to')
    const interval = url.searchParams.get('interval')
    if (!scopeType || !scopeId || !metric || !from || !to) {
      return HttpResponse.json({ message: 'Missing parameters.' }, { status: 400 })
    }
    const fromTime = new Date(from).getTime()
    const toTime = new Date(to).getTime()
    const intervalMinutes = parseIntervalMinutes(interval)
    const { scenario } = resolveScenarioContext(url, scopeType, scopeId)
    const offset = (scenario?.overrides?.scheduleOffsetMinutes ?? 0) * 60000
    const readings = buildReadings(scopeType, scopeId, metric, fromTime + offset, toTime + offset, intervalMinutes, scenario?.overrides)
    return HttpResponse.json({ ...readings, scopeType })
  }),

  http.get('/api/tp/conditions/thresholds', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Threshold service unavailable.' }, { status: 500 })
    }
    const scopeType = url.searchParams.get('scopeType') as ScopeType | null
    const scopeId = url.searchParams.get('scopeId')
    if (!scopeType || !scopeId) {
      return HttpResponse.json({ message: 'scopeType and scopeId required.' }, { status: 400 })
    }
    const { scenario } = resolveScenarioContext(url, scopeType, scopeId)
    const thresholds = getThresholdsForScope(scopeType, scopeId)
    const effective = scenario ? applyThresholdOverrides(thresholds, scenario.overrides.thresholds) : thresholds
    return HttpResponse.json(effective)
  }),

  http.put('/api/tp/conditions/thresholds', async ({ request }) => {
    await withLatencyProfile('normal')
    const url = new URL(request.url)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Threshold update failed.' }, { status: 500 })
    }
    const scopeType = url.searchParams.get('scopeType') as ScopeType | null
    const scopeId = url.searchParams.get('scopeId')
    if (!scopeType || !scopeId) {
      return HttpResponse.json({ message: 'scopeType and scopeId required.' }, { status: 400 })
    }
    const body = (await request.json()) as { thresholds?: ConditionThreshold[] }
    const thresholds = body.thresholds
    if (!thresholds) {
      return HttpResponse.json({ message: 'thresholds required.' }, { status: 400 })
    }
    getThresholdsForScope(scopeType, scopeId)
    const key = `${scopeType}:${scopeId}`
    const updated = thresholds.map((threshold) => ({
      ...threshold,
      unit: metricUnitMap[threshold.metric],
    }))
    thresholdsByScope.set(key, updated)
    return HttpResponse.json(updated)
  }),

  http.get('/api/tp/work-orders', async ({ request }) => {
    const url = new URL(request.url)
    await withLatencyProfile(url.searchParams.get('latency') ?? undefined)
    if (maybeError(url.searchParams.get('error'))) {
      return HttpResponse.json({ message: 'Work orders unavailable.' }, { status: 500 })
    }
    const facilityId = url.searchParams.get('facilityId')
    const filtered = facilityId ? workOrders.filter((order) => order.facilityId === facilityId) : workOrders
    return HttpResponse.json(filtered)
  }),
]
