export type TimelineEventKind =
  | 'created'
  | 'updated'
  | 'linked'
  | 'unlinked'
  | 'status'
  | 'scheduled'
  | 'exported'

export type TimelineEventDiff = {
  field: string
  before: string | number | null
  after: string | number | null
}

export type TimelineEvent = {
  id: string
  at: string
  title: string
  detail?: string
  actor?: string
  kind: TimelineEventKind
  diff?: TimelineEventDiff[]
}
