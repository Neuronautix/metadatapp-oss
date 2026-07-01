import type { CalendarEvent } from '@/features/system/calendar/calendar.types.ts'

const base = new Date()

const at = (days: number, hour: number, minutes = 0) => {
  const date = new Date(base)
  date.setDate(base.getDate() + days)
  date.setHours(hour, minutes, 0, 0)
  return date.toISOString()
}

const range = (days: number, startHour: number, durationHours: number) => {
  const start = new Date(at(days, startHour))
  const end = new Date(start)
  end.setHours(start.getHours() + durationHours)
  return { start: start.toISOString(), end: end.toISOString() }
}

export const calendarEvents: CalendarEvent[] = [
  {
    id: 'evt-001',
    title: 'Synapse Proteome Run 14',
    ...range(1, 9, 6),
    status: 'in-progress',
    kind: 'study',
    resource: { type: 'study', id: 'EXP-401', label: 'EXP-401' },
    location: 'Helix Institute Core Lab',
  },
  {
    id: 'evt-002',
    title: 'Microbiome Baseline QC Review',
    ...range(2, 10, 2),
    status: 'scheduled',
    kind: 'study',
    resource: { type: 'study', id: 'EXP-402', label: 'EXP-402' },
    location: 'Atlas BioLab',
  },
  {
    id: 'evt-003',
    title: 'Rare Variant Validation Sequencing',
    ...range(4, 8, 8),
    status: 'scheduled',
    kind: 'study',
    resource: { type: 'study', id: 'EXP-403', label: 'EXP-403' },
  },
  {
    id: 'evt-004',
    title: 'Protocol Review: Long-read sequencing',
    ...range(3, 13, 1),
    status: 'scheduled',
    kind: 'protocol',
    resource: { type: 'protocol', id: 'PRO-301', label: 'PRO-301' },
  },
  {
    id: 'evt-005',
    title: 'Protocol Audit: Metagenomic surveillance',
    ...range(9, 11, 1),
    status: 'blocked',
    kind: 'protocol',
    resource: { type: 'protocol', id: 'PRO-420', label: 'PRO-420' },
  },
  {
    id: 'evt-006',
    title: 'Sample Collection - SYN-015-CSF',
    ...range(0, 7, 1),
    status: 'scheduled',
    kind: 'sample',
    resource: { type: 'sample', id: 'SMP-9002', label: 'SMP-9002' },
  },
  {
    id: 'evt-007',
    title: 'Sample Processing - META-040-PLASMA',
    ...range(5, 9, 2),
    status: 'in-progress',
    kind: 'sample',
    resource: { type: 'sample', id: 'SMP-9030', label: 'SMP-9030' },
  },
  {
    id: 'evt-008',
    title: 'Investigation Steering Sync',
    ...range(6, 15, 1),
    status: 'scheduled',
    kind: 'appointment',
    resource: { type: 'investigation', id: 'PRJ-2014', label: 'Neuroimmune Synapse Map' },
  },
  {
    id: 'evt-009',
    title: 'Dataset Release Gate Review',
    ...range(12, 14, 1),
    status: 'scheduled',
    kind: 'appointment',
    resource: { type: 'investigation', id: 'PRJ-2031', label: 'Microbiome Resilience Atlas' },
  },
  {
    id: 'evt-010',
    title: 'Instrument Maintenance Window',
    ...range(7, 18, 2),
    status: 'blocked',
    kind: 'appointment',
    location: 'Core Facility',
  },
  {
    id: 'evt-011',
    title: 'Protocol Review: LC-MS baseline',
    ...range(-2, 11, 1),
    status: 'completed',
    kind: 'protocol',
    resource: { type: 'protocol', id: 'PRO-254', label: 'PRO-254' },
  },
  {
    id: 'evt-012',
    title: 'Sample QC Gate - WTR-001-INFLUENT',
    ...range(8, 9, 1),
    status: 'scheduled',
    kind: 'sample',
    resource: { type: 'sample', id: 'SMP-9050', label: 'SMP-9050' },
  },
  {
    id: 'evt-013',
    title: 'Study Run Completion',
    ...range(10, 16, 2),
    status: 'scheduled',
    kind: 'study',
    resource: { type: 'study', id: 'EXP-406', label: 'EXP-406' },
  },
  {
    id: 'evt-014',
    title: 'Clinical Advisory Board',
    ...range(14, 12, 1),
    status: 'scheduled',
    kind: 'appointment',
    location: 'Hybrid session',
  },
]
