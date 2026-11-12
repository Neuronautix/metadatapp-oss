export type LiveSensorHealthStatus = 'ready' | 'stale' | 'waiting_for_data'

export type LiveSensorAlarm = {
  code: string
  level: 'warning' | 'critical'
  message: string
}

export type LiveSensorThresholdMetric = 'temperature' | 'humidity' | 'pressure'

export type LiveSensorHealthResponse = {
  ok: boolean
  enabled: boolean
  upstreamReachable: boolean
  status: LiveSensorHealthStatus
  latestObservationAvailable: boolean
  lastObservationAt: string | null
  freshnessAgeSeconds: number | null
  isFresh: boolean
  message: string | null
}

export type LiveSensorLatestResponse = {
  ok: boolean
  enabled: boolean
  upstreamReachable: boolean
  sensorId: string | null
  sourcePort: string | null
  schemaVersion: string | null
  observedAt: string | null
  temperatureC: number | null
  humidityPct: number | null
  pressureHpa: number | null
  message: string | null
}

export type LiveSensorHistoryPoint = {
  observedAt: string
  temperatureC: number | null
  humidityPct: number | null
  pressureHpa: number | null
}

export type LiveSensorHistoryResponse = {
  ok: boolean
  enabled: boolean
  upstreamReachable: boolean
  id: string
  sensorId: string | null
  sourcePort: string | null
  schemaVersion: string | null
  observedAt: string | null
  rangeMinutes: number
  maxPoints: number
  points: LiveSensorHistoryPoint[]
  message: string | null
}

export type LiveSensorThresholdMetricStatus = {
  available: boolean
  value: number | null
  unit: string
  status: string
  alarm: boolean
}

export type LiveSensorThresholdStatusResponse = {
  ok: boolean
  enabled: boolean
  upstreamReachable: boolean
  observedAt: string | null
  sensorId: string | null
  sourcePort: string | null
  schemaVersion: string | null
  hasActiveAlarms: boolean
  overallStatus: 'normal' | 'warning' | 'critical' | string
  activeAlarms: LiveSensorAlarm[]
  thresholdStatus: Record<LiveSensorThresholdMetric, LiveSensorThresholdMetricStatus>
  message: string | null
}

export type LiveSensorConnectionState = 'connected' | 'stale' | 'waiting' | 'unreachable' | 'disabled'

export type LiveSensorSnapshot = {
  connectionState: LiveSensorConnectionState
  health: LiveSensorHealthResponse
  latest: LiveSensorLatestResponse
  threshold: LiveSensorThresholdStatusResponse
}
