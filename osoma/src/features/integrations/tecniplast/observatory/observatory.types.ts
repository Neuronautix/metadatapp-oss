export type TrendDirection = 'up' | 'down' | 'flat'

export type HealthMetric = {
  id: string
  label: string
  value: string
  helper: string
  tone: 'ok' | 'watch' | 'risk'
  href: string
  trend: {
    direction: TrendDirection
    delta: number
    label: string
  }
}

export type PulseRange = '24h' | '7d' | '30d'

export type PulseBin = {
  index: number
  count: number
  sampleLabel?: string
}

export type PulseLane = {
  id: string
  label: string
  href: string
  accent: 'ocean' | 'amber' | 'violet' | 'slate'
  bins: PulseBin[]
  max: number
  total: number
}

export type HeatmapCell = {
  metric: string
  value: number
  min: number
  max: number
  unit: string
  status: 'ok' | 'out-of-range'
}

export type HeatmapRow = {
  id: string
  label: string
  cells: HeatmapCell[]
  status: 'ok' | 'watch'
}

export type MetricSeries = {
  metric: string
  unit: string
  values: number[]
  labels: string[]
  min: number
  max: number
}

export type TopologyNode = {
  id: string
  label: string
  type: 'facility' | 'room' | 'rack' | 'cage'
  status: 'ok' | 'watch' | 'risk'
  impact: number
  href?: string
  children?: TopologyNode[]
}

export type Insight = {
  id: string
  title: string
  detail: string
  actionLabel: string
  actionHref: string
  tone: 'signal' | 'warning' | 'neutral'
  badge?: string
}
