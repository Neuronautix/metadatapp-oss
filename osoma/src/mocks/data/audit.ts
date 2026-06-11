import type { AuditLogEntry, AuditDiff } from '@/domain/audit.ts'
import { calendarEvents } from './calendar.ts'
import { datasets } from './datasets.ts'
import { studies } from './studies.ts'
import { organizations } from './organizations.ts'
import { investigations } from './investigations.ts'
import { assays } from './assays.ts'
import { samples } from './samples.ts'
import { subjects } from './subjects.ts'
import { users } from './users.ts'
import { alarms, cages, rooms, racks, sensors, workOrders } from './tecniplast.ts'

const actors = ['Amelia Rivera', 'Luca Bennett', 'Isabelle Chen', 'Sofia Alvarez', 'System']

const makeDiff = (field: string, before: string | number | null, after: string | number | null): AuditDiff => ({
  field,
  before,
  after,
})

const buildAuditEntries = () => {
  const entries: AuditLogEntry[] = []
  let counter = 1

  const pushEntry = (entry: Omit<AuditLogEntry, 'id' | 'correlationId'> & { correlationId?: string }) => {
    const id = `AUD-${String(counter).padStart(4, '0')}`
    const correlationId = entry.correlationId ?? `CORR-${String(1200 + counter)}`
    // correlationId is excluded safely
    const rest = { ...entry }
    delete rest.correlationId
    entries.push({ ...rest, id, correlationId })
    counter += 1
  }

  investigations.forEach((investigation, index) => {
    const actor = actors[index % actors.length]
    pushEntry({
      at: investigation.createdAt,
      actor,
      action: 'created',
      resourceType: 'investigation',
      resourceId: investigation.id,
      summary: `Investigation ${investigation.name} created`,
    })
    pushEntry({
      at: investigation.updatedAt,
      actor: 'Data Steward',
      action: 'updated',
      resourceType: 'investigation',
      resourceId: investigation.id,
      summary: `FAIR profile updated for ${investigation.name}`,
      diff: [makeDiff('fairScore.total', investigation.fairScore.total - 4, investigation.fairScore.total)],
    })
  })

  studies.forEach((study, index) => {
    const actor = actors[(index + 1) % actors.length]
    pushEntry({
      at: study.createdAt,
      actor,
      action: 'created',
      resourceType: 'study',
      resourceId: study.id,
      summary: `Study ${study.name} initialized`,
    })
    pushEntry({
      at: study.startedAt,
      actor: 'Run Ops',
      action: 'status.changed',
      resourceType: 'study',
      resourceId: study.id,
      summary: `Status moved to ${study.status}`,
      diff: [makeDiff('status', 'draft', study.status)],
    })
  })

  samples.forEach((sample, index) => {
    const actor = actors[(index + 2) % actors.length]
    pushEntry({
      at: sample.createdAt,
      actor,
      action: 'created',
      resourceType: 'sample',
      resourceId: sample.id,
      summary: `Sample ${sample.label} registered`,
    })
    pushEntry({
      at: sample.updatedAt,
      actor: 'QC Bot',
      action: 'status.changed',
      resourceType: 'sample',
      resourceId: sample.id,
      summary: `Sample status updated to ${sample.status}`,
      diff: [makeDiff('status', 'collected', sample.status)],
    })
  })

  subjects.forEach((subject, index) => {
    const actor = actors[(index + 3) % actors.length]
    pushEntry({
      at: subject.createdAt,
      actor,
      action: 'created',
      resourceType: 'subject',
      resourceId: subject.id,
      summary: `Subject ${subject.label} onboarded`,
    })
    pushEntry({
      at: subject.updatedAt,
      actor: 'Compliance',
      action: 'access.changed',
      resourceType: 'subject',
      resourceId: subject.id,
      summary: `Consent updated for ${subject.label}`,
      diff: [makeDiff('consent', 'granted', subject.consent)],
    })
  })

  assays.forEach((assay, index) => {
    const actor = actors[(index + 4) % actors.length]
    pushEntry({
      at: assay.createdAt,
      actor,
      action: 'created',
      resourceType: 'assay',
      resourceId: assay.id,
      summary: `Assay ${assay.name} published`,
    })
    pushEntry({
      at: assay.updatedAt,
      actor: assay.owner,
      action: 'updated',
      resourceType: 'assay',
      resourceId: assay.id,
      summary: `Assay ${assay.id} revised`,
      diff: [makeDiff('version', 'v1.0', assay.version)],
    })
  })

  datasets.forEach((dataset, index) => {
    const actor = actors[index % actors.length]
    pushEntry({
      at: dataset.createdAt,
      actor,
      action: 'created',
      resourceType: 'dataset',
      resourceId: dataset.id,
      summary: `Dataset ${dataset.title} registered`,
    })
    pushEntry({
      at: dataset.updatedAt,
      actor: 'Release Manager',
      action: 'exported',
      resourceType: 'dataset',
      resourceId: dataset.id,
      summary: `Dataset ${dataset.id} exported for reuse`,
    })
  })

  users.forEach((user, index) => {
    const actor = actors[index % actors.length]
    pushEntry({
      at: user.lastActive,
      actor,
      action: 'access.changed',
      resourceType: 'user',
      resourceId: user.id,
      summary: `Access updated for ${user.name}`,
      diff: [makeDiff('accessLevel', 'viewer', user.accessLevel)],
    })
  })

  organizations.forEach((organization, index) => {
    const actor = actors[index % actors.length]
    pushEntry({
      at: organization.createdAt,
      actor,
      action: 'created',
      resourceType: 'organization',
      resourceId: organization.id,
      summary: `Organization ${organization.name} onboarded`,
    })
    pushEntry({
      at: organization.updatedAt,
      actor: 'Ops',
      action: 'updated',
      resourceType: 'organization',
      resourceId: organization.id,
      summary: `Lab footprint updated for ${organization.name}`,
      diff: [makeDiff('labs', Math.max(1, organization.labs - 1), organization.labs)],
    })
  })

  calendarEvents.slice(0, 5).forEach((event, index) => {
    const actor = actors[index % actors.length]
    pushEntry({
      at: event.start,
      actor,
      action: 'scheduled',
      resourceType: 'calendar',
      resourceId: event.id,
      summary: `Scheduled ${event.title}`,
    })
  })

  rooms.slice(0, 3).forEach((room, index) => {
    pushEntry({
      at: new Date(Date.now() - (index + 2) * 86400000).toISOString(),
      actor: actors[index % actors.length],
      action: 'updated',
      resourceType: 'room',
      resourceId: room.id,
      summary: `Room ${room.name} inspection completed`,
      diff: [makeDiff('status', 'maintenance', room.status)],
    })
  })

  racks.slice(0, 3).forEach((rack, index) => {
    pushEntry({
      at: new Date(Date.now() - (index + 3) * 86400000).toISOString(),
      actor: 'Facilities Ops',
      action: 'updated',
      resourceType: 'rack',
      resourceId: rack.id,
      summary: `Rack ${rack.label} maintenance logged`,
      diff: [makeDiff('status', 'maintenance', rack.status)],
    })
  })

  cages.slice(0, 4).forEach((cage, index) => {
    pushEntry({
      at: new Date(Date.now() - (index + 1) * 3600000).toISOString(),
      actor: 'Animal Care',
      action: 'updated',
      resourceType: 'cage',
      resourceId: cage.id,
      summary: `Cage ${cage.label} cleaned and reset`,
      diff: [makeDiff('status', 'cleaning', cage.status)],
    })
  })

  alarms.slice(0, 3).forEach((alarm) => {
    pushEntry({
      at: alarm.createdAt,
      actor: 'Monitoring',
      action: 'created',
      resourceType: 'alarm',
      resourceId: alarm.id,
      summary: `${alarm.sensorType} alarm raised for ${alarm.scopeLabel}`,
      correlationId: alarm.correlationId,
    })
  })

  sensors.slice(0, 3).forEach((sensor) => {
    pushEntry({
      at: sensor.lastSeen,
      actor: 'System',
      action: 'status.changed',
      resourceType: 'sensor',
      resourceId: sensor.id,
      summary: `${sensor.metric} sensor status updated`,
      diff: [makeDiff('status', 'online', sensor.status)],
    })
  })

  workOrders.slice(0, 2).forEach((order) => {
    pushEntry({
      at: order.dueAt,
      actor: 'Operations',
      action: 'scheduled',
      resourceType: 'workorder',
      resourceId: order.id,
      summary: `Work order scheduled: ${order.title}`,
    })
  })

  return entries
}

export const auditEntries = buildAuditEntries()
