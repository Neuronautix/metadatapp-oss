import type { AuditLogEntry } from '@/domain/audit.ts'
import type { Study } from '@/domain/resources.ts'
import type { Scenario } from '@/domain/scenario/scenario.types.ts'
import type {
  Alarm,
  ConditionSnapshot,
  ConditionSnapshotMetric,
  Room,
  Rack,
  Cage,
  Sensor,
  WorkOrder,
} from '@/features/integrations/tecniplast/tecniplast.types.ts'
import type {
  HealthMetric,
  HeatmapRow,
  Insight,
  MetricSeries,
  PulseLane,
  PulseRange,
  TopologyNode,
  TrendDirection,
} from './observatory.types.ts'
import { pulseRangeConfig } from './observatory.tokens.ts'

const dayMs = 24 * 60 * 60 * 1000

const toTimestamp = (value: string) => new Date(value).getTime()

const buildTrend = (recent: number, baseline: number): { direction: TrendDirection; delta: number } => {
  if (baseline <= 0) {
    return { direction: recent > 0 ? 'up' : 'flat', delta: recent }
  }
  const diff = recent - baseline
  if (diff > 0.6) return { direction: 'up', delta: diff }
  if (diff < -0.6) return { direction: 'down', delta: diff }
  return { direction: 'flat', delta: diff }
}

const deriveTone = (value: number) => {
  if (value <= 0) return 'ok'
  if (value <= 2) return 'watch'
  return 'risk'
}

export const buildHealthMetrics = (input: {
  alarms: Alarm[]
  sensors: Sensor[]
  workOrders: WorkOrder[]
  studies: Study[]
  outOfRangeRooms: number
  now?: number
}): HealthMetric[] => {
  const now = input.now ?? Date.now()
  const dayAgo = now - dayMs
  const weekAgo = now - dayMs * 7

  const alarmsActive = input.alarms.filter((alarm) => alarm.status === 'active')
  const alarmsRecent = alarmsActive.filter((alarm) => toTimestamp(alarm.createdAt) >= dayAgo).length
  const alarmsWeek = alarmsActive.filter((alarm) => toTimestamp(alarm.createdAt) >= weekAgo).length / 7
  const alarmTrend = buildTrend(alarmsRecent, alarmsWeek)

  const sensorsOffline = input.sensors.filter((sensor) => sensor.status === 'offline')
  const sensorsRecent = sensorsOffline.filter((sensor) => toTimestamp(sensor.lastSeen) >= dayAgo).length
  const sensorsWeek = sensorsOffline.filter((sensor) => toTimestamp(sensor.lastSeen) >= weekAgo).length / 7
  const sensorTrend = buildTrend(sensorsRecent, sensorsWeek)

  const overdueOrders = input.workOrders.filter((order) => order.status !== 'done' && toTimestamp(order.dueAt) < now)
  const overdueRecent = overdueOrders.filter((order) => toTimestamp(order.dueAt) >= dayAgo).length
  const overdueWeek = overdueOrders.filter((order) => toTimestamp(order.dueAt) >= weekAgo).length / 7
  const overdueTrend = buildTrend(overdueRecent, overdueWeek)

  const studiesAtRisk = input.studies.filter(
    (study) =>
      study.qcStatus !== 'pass' || study.status === 'paused' || study.status === 'failed'
  )
  const studiesRecent = studiesAtRisk.filter((study) => toTimestamp(study.updatedAt) >= dayAgo).length
  const studiesWeek = studiesAtRisk.filter((study) => toTimestamp(study.updatedAt) >= weekAgo).length / 7
  const studyTrend = buildTrend(studiesRecent, studiesWeek)

  const outOfRangeTrend = buildTrend(input.outOfRangeRooms, input.outOfRangeRooms)

  return [
    {
      id: 'alarms',
      label: 'Active alarms',
      value: String(alarmsActive.length),
      helper: 'Severity-weighted alerts',
      tone: deriveTone(alarmsActive.length),
      href: '/tp/alarms',
      trend: { direction: alarmTrend.direction, delta: alarmTrend.delta, label: '24h vs 7d' },
    },
    {
      id: 'out-of-range',
      label: 'Entities out-of-range',
      value: String(input.outOfRangeRooms),
      helper: 'Rooms outside thresholds',
      tone: deriveTone(input.outOfRangeRooms),
      href: '/tp/conditions',
      trend: { direction: outOfRangeTrend.direction, delta: outOfRangeTrend.delta, label: 'snapshot' },
    },
    {
      id: 'studies',
      label: 'Studies at risk',
      value: String(studiesAtRisk.length),
      helper: 'Paused or QC review',
      tone: deriveTone(studiesAtRisk.length),
      href: '/studies',
      trend: { direction: studyTrend.direction, delta: studyTrend.delta, label: '24h vs 7d' },
    },
    {
      id: 'sensors',
      label: 'Sensors offline',
      value: String(sensorsOffline.length),
      helper: 'Last seen > 4h',
      tone: deriveTone(sensorsOffline.length),
      href: '/tp/sensors',
      trend: { direction: sensorTrend.direction, delta: sensorTrend.delta, label: '24h vs 7d' },
    },
    {
      id: 'tasks',
      label: 'Tasks overdue',
      value: String(overdueOrders.length),
      helper: 'Work orders past due',
      tone: deriveTone(overdueOrders.length),
      href: '/tp',
      trend: { direction: overdueTrend.direction, delta: overdueTrend.delta, label: '24h vs 7d' },
    },
  ]
}

type PulseEvent = {
  id: string
  at: string
  label: string
  intensity: number
}

const bucketize = (events: PulseEvent[], range: PulseRange) => {
  const now = Date.now()
  const config = pulseRangeConfig[range]
  const totalWindowMs = config.minutes * 60000
  const start = now - totalWindowMs
  const bucketSize = totalWindowMs / config.bins
  const bins = Array.from({ length: config.bins }, (_, index) => ({ index, count: 0, sampleLabel: undefined as string | undefined }))

  events.forEach((event) => {
    const at = toTimestamp(event.at)
    if (Number.isNaN(at) || at < start || at > now) return
    const bucketIndex = Math.min(config.bins - 1, Math.max(0, Math.floor((at - start) / bucketSize)))
    bins[bucketIndex].count += event.intensity
    if (!bins[bucketIndex].sampleLabel) {
      bins[bucketIndex].sampleLabel = event.label
    }
  })

  const max = Math.max(1, ...bins.map((bin) => bin.count))
  const total = events.filter((event) => toTimestamp(event.at) >= start).length

  return { bins, max, total }
}

export const buildPulseLanes = (input: {
  alarms: Alarm[]
  audits: AuditLogEntry[]
  range: PulseRange
}): PulseLane[] => {
  const severityIntensity: Record<Alarm['severity'], number> = {
    critical: 4,
    high: 3,
    medium: 2,
    low: 1,
  }

  const alarmEvents = input.alarms
    .filter((alarm) => ['critical', 'high'].includes(alarm.severity))
    .map((alarm) => ({
      id: alarm.id,
      at: alarm.createdAt,
      label: alarm.message,
      intensity: severityIntensity[alarm.severity],
    }))

  const anomalyEvents = input.alarms
    .filter((alarm) => ['medium', 'low'].includes(alarm.severity))
    .map((alarm) => ({
      id: `anom-${alarm.id}`,
      at: alarm.createdAt,
      label: alarm.message,
      intensity: severityIntensity[alarm.severity],
    }))

  const assayEvents = input.audits
    .filter((entry) => entry.resourceType === 'assay' && entry.action === 'updated')
    .map((entry) => ({
      id: entry.id,
      at: entry.at,
      label: entry.summary,
      intensity: 2,
    }))

  const humanEvents = input.audits
    .filter((entry) => entry.actor && entry.actor !== 'System')
    .filter((entry) => ['updated', 'created', 'status.changed', 'scheduled'].includes(entry.action))
    .map((entry) => ({
      id: `human-${entry.id}`,
      at: entry.at,
      label: entry.summary,
      intensity: 1,
    }))

  const alarmBuckets = bucketize(alarmEvents, input.range)
  const anomalyBuckets = bucketize(anomalyEvents, input.range)
  const assayBuckets = bucketize(assayEvents, input.range)
  const humanBuckets = bucketize(humanEvents, input.range)

  return [
    {
      id: 'alarms',
      label: 'Alarms',
      href: '/tp/alarms',
      accent: 'amber',
      bins: alarmBuckets.bins,
      max: alarmBuckets.max,
      total: alarmBuckets.total,
    },
    {
      id: 'assays',
      label: 'Assay changes',
      href: '/assays',
      accent: 'violet',
      bins: assayBuckets.bins,
      max: assayBuckets.max,
      total: assayBuckets.total,
    },
    {
      id: 'anomalies',
      label: 'Environmental anomalies',
      href: '/tp/conditions',
      accent: 'ocean',
      bins: anomalyBuckets.bins,
      max: anomalyBuckets.max,
      total: anomalyBuckets.total,
    },
    {
      id: 'interventions',
      label: 'Human interventions',
      href: '/audit',
      accent: 'slate',
      bins: humanBuckets.bins,
      max: humanBuckets.max,
      total: humanBuckets.total,
    },
  ]
}

const metricOrder = ['temperature', 'humidity', 'pressure']

export const buildHeatmapRows = (rooms: Room[], snapshots: ConditionSnapshot[]): HeatmapRow[] => {
  return rooms.map((room) => {
    const snapshot = snapshots.find((item) => item.scopeId === room.id)
    const metrics = snapshot?.metrics ?? []
    const cells = metricOrder.map((metric) => {
      const metricEntry = metrics.find((item) => item.metric === metric) as ConditionSnapshotMetric | undefined
      if (!metricEntry) {
        return {
          metric,
          value: 0,
          min: 0,
          max: 0,
          unit: '',
          status: 'ok' as const,
        }
      }
      return {
        metric,
        value: metricEntry.value,
        min: metricEntry.min,
        max: metricEntry.max,
        unit: metricEntry.unit,
        status: metricEntry.status,
      }
    })
    const status = cells.some((cell) => cell.status === 'out-of-range') ? 'watch' : 'ok'
    return { id: room.id, label: room.name, cells, status }
  })
}

export const buildMetricSeries = (rows: HeatmapRow[]): MetricSeries[] => {
  return metricOrder.map((metric) => {
    const values = rows.map((row) => row.cells.find((cell) => cell.metric === metric)?.value ?? 0)
    const labels = rows.map((row) => row.label)
    const min = Math.min(...values)
    const max = Math.max(...values)
    const unit = rows.find((row) => row.cells.find((cell) => cell.metric === metric))?.cells.find((cell) => cell.metric === metric)
      ?.unit
    return {
      metric,
      values,
      labels,
      min: Number.isFinite(min) ? min : 0,
      max: Number.isFinite(max) ? max : 0,
      unit: unit ?? '',
    }
  })
}

const toRoomId = (scopeType: Alarm['scopeType'], scopeId: string, rackRoom: Map<string, string>, cageRoom: Map<string, string>) => {
  if (scopeType === 'room') return scopeId
  if (scopeType === 'rack') return rackRoom.get(scopeId)
  if (scopeType === 'cage') return cageRoom.get(scopeId)
  return undefined
}

export const buildTopologyTree = (input: {
  facilityName: string
  rooms: Room[]
  racks: Rack[]
  cages: Cage[]
  alarms: Alarm[]
  sensors: Sensor[]
  snapshots: ConditionSnapshot[]
}): TopologyNode => {
  const rackRoom = new Map(input.racks.map((rack) => [rack.id, rack.roomId]))
  const cageRoom = new Map(input.cages.map((cage) => [cage.id, rackRoom.get(cage.rackId) ?? '']))

  const alarmCounts = input.alarms.reduce<Record<string, number>>((acc, alarm) => {
    const roomId = toRoomId(alarm.scopeType, alarm.scopeId, rackRoom, cageRoom)
    if (!roomId) return acc
    acc[roomId] = (acc[roomId] ?? 0) + (alarm.status === 'active' ? 1 : 0)
    return acc
  }, {})

  const outOfRangeCounts = input.snapshots.reduce<Record<string, number>>((acc, snapshot) => {
    const outOfRange = snapshot.metrics.filter((metric) => metric.status === 'out-of-range').length
    if (outOfRange) {
      acc[snapshot.scopeId] = outOfRange
    }
    return acc
  }, {})

  const offlineSensors = input.sensors.filter((sensor) => sensor.status === 'offline')
  const sensorRoomCounts = offlineSensors.reduce<Record<string, number>>((acc, sensor) => {
    const roomId = toRoomId(sensor.scopeType as Alarm['scopeType'], sensor.scopeId, rackRoom, cageRoom)
    if (!roomId) return acc
    acc[roomId] = (acc[roomId] ?? 0) + 1
    return acc
  }, {})

  const racksByRoom = input.racks.reduce<Record<string, Rack[]>>((acc, rack) => {
    acc[rack.roomId] = acc[rack.roomId] ? [...acc[rack.roomId], rack] : [rack]
    return acc
  }, {})

  const cagesByRack = input.cages.reduce<Record<string, Cage[]>>((acc, cage) => {
    acc[cage.rackId] = acc[cage.rackId] ? [...acc[cage.rackId], cage] : [cage]
    return acc
  }, {})

  const roomNodes: TopologyNode[] = input.rooms.map((room) => {
    const roomAlarmCount = alarmCounts[room.id] ?? 0
    const roomOutOfRange = outOfRangeCounts[room.id] ?? 0
    const roomOffline = sensorRoomCounts[room.id] ?? 0
    const impact = roomAlarmCount * 2 + roomOutOfRange + roomOffline
    const status = impact >= 4 ? 'risk' : impact >= 2 ? 'watch' : 'ok'

    const rackNodes: TopologyNode[] = (racksByRoom[room.id] ?? []).map((rack) => {
      const rackCages = cagesByRack[rack.id] ?? []
      const rackImpact = rackCages.filter((cage) => cage.status !== 'occupied').length
      const rackStatus = rackImpact >= 2 ? 'watch' : 'ok'
      return {
        id: rack.id,
        label: rack.label,
        type: 'rack',
        status: rackStatus,
        impact: rackImpact,
        children: rackCages.map((cage) => ({
          id: cage.id,
          label: cage.label,
          type: 'cage',
          status: cage.status === 'quarantine' || cage.status === 'cleaning' ? 'watch' : 'ok',
          impact: cage.occupancy,
          href: `/tp/cages/${cage.id}`,
        })),
      }
    })

    return {
      id: room.id,
      label: room.name,
      type: 'room',
      status,
      impact,
      href: `/tp/rooms/${room.id}`,
      children: rackNodes,
    }
  })

  const facilityImpact = roomNodes.reduce((sum, room) => sum + room.impact, 0)
  const facilityStatus = roomNodes.some((room) => room.status === 'risk')
    ? 'risk'
    : roomNodes.some((room) => room.status === 'watch')
      ? 'watch'
      : 'ok'

  return {
    id: 'facility-root',
    label: input.facilityName,
    type: 'facility',
    status: facilityStatus,
    impact: facilityImpact,
    children: roomNodes,
  }
}

export const buildInsights = (input: {
  alarms: Alarm[]
  audits: AuditLogEntry[]
  scenarios: Scenario[]
  rooms: Room[]
  racks: Rack[]
  cages: Cage[]
}): Insight[] => {
  const alarms = input.alarms
  const audits = input.audits

  const rootGroups = alarms.reduce<Record<string, Alarm[]>>((acc, alarm) => {
    acc[alarm.correlationId] = acc[alarm.correlationId] ? [...acc[alarm.correlationId], alarm] : [alarm]
    return acc
  }, {})
  const topGroup = Object.values(rootGroups).sort((a, b) => b.length - a.length)[0]

  const assayEdits = audits.filter((entry) => entry.resourceType === 'assay' && entry.action === 'updated')
  const recentAssayEdits = assayEdits.filter((entry) => toTimestamp(entry.at) >= Date.now() - dayMs * 7)
  const alarmsAfterEdits = recentAssayEdits.reduce((count, edit) => {
    const editTime = toTimestamp(edit.at)
    return (
      count +
      alarms.filter((alarm) => {
        const alarmTime = toTimestamp(alarm.createdAt)
        return alarmTime >= editTime && alarmTime <= editTime + dayMs / 4
      }).length
    )
  }, 0)

  const rackRoom = new Map(input.racks.map((rack) => [rack.id, rack.roomId]))
  const cageRoom = new Map(input.cages.map((cage) => [cage.id, rackRoom.get(cage.rackId) ?? '']))
  const roomIncidentCounts = alarms.reduce<Record<string, number>>((acc, alarm) => {
    const roomId = toRoomId(alarm.scopeType, alarm.scopeId, rackRoom, cageRoom)
    if (!roomId) return acc
    acc[roomId] = (acc[roomId] ?? 0) + 1
    return acc
  }, {})
  const topRoomId = Object.entries(roomIncidentCounts).sort((a, b) => b[1] - a[1])[0]?.[0]
  const topRoomLabel = input.rooms.find((room) => room.id === topRoomId)?.name ?? 'Primary room'
  const topRoomShare = alarms.length
    ? Math.round(((roomIncidentCounts[topRoomId ?? ''] ?? 0) / alarms.length) * 100)
    : 0

  const scenario = input.scenarios[0]
  const scenarioLabel = scenario?.label ?? 'Scenario replay'
  const scenarioRoom = scenario?.scope.id

  return [
    {
      id: 'root-cause',
      title: topGroup && topGroup.length > 1 ? `${topGroup.length} alarms share one root cause` : 'No dominant root cause yet',
      detail: topGroup?.[0]
        ? `Correlation ${topGroup[0].correlationId} links ${topGroup.length} recent alarms.`
        : 'Spread suggests multiple concurrent causes. Continue to monitor.',
      actionLabel: 'Explain',
      actionHref: '/audit',
      tone: topGroup && topGroup.length > 2 ? 'warning' : 'neutral',
      badge: topGroup?.[0]?.correlationId,
    },
    {
      id: 'assay-drift',
      title: alarmsAfterEdits ? 'Delays cluster after assay edits' : 'Assay edits stable',
      detail: alarmsAfterEdits
        ? `${alarmsAfterEdits} alarms followed ${recentAssayEdits.length} assay revisions in the last 7 days.`
        : 'No alarms clustered within 6h of assay changes this week.',
      actionLabel: 'Drill-down',
      actionHref: '/assays',
      tone: alarmsAfterEdits ? 'warning' : 'signal',
      badge: recentAssayEdits.length ? `${recentAssayEdits.length} edits` : undefined,
    },
    {
      id: 'room-hotspot',
      title: `${topRoomLabel} generates ${topRoomShare}% of incidents`,
      detail: topRoomId
        ? 'Concentrate inspections and calibrations on this zone.'
        : 'Incident distribution is balanced across rooms.',
      actionLabel: 'Drill-down',
      actionHref: topRoomId ? `/tp/rooms/${topRoomId}` : '/tp/map',
      tone: topRoomShare >= 35 ? 'warning' : 'signal',
      badge: topRoomId ? `Room ${topRoomLabel}` : undefined,
    },
    {
      id: 'scenario-replay',
      title: scenario ? `Scenario ready: ${scenarioLabel}` : 'Scenario replay idle',
      detail: scenario
        ? 'Replay the latest scenario to compare reality vs intervention.'
        : 'Create a scenario to explore potential mitigations.',
      actionLabel: scenario ? 'Replay' : 'Explain',
      actionHref: scenarioRoom ? `/tp/rooms/${scenarioRoom}` : '/tp',
      tone: scenario ? 'signal' : 'neutral',
      badge: scenario?.scope.label,
    },
  ]
}
