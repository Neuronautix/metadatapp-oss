import type {
  LiveSensorHealthResponse,
  LiveSensorHistoryResponse,
  LiveSensorLatestResponse,
  LiveSensorThresholdStatusResponse,
} from '@/features/demo-sensors/demo-sensors.types.ts'

export const liveSensorHealth: LiveSensorHealthResponse = {
  ok: true,
  enabled: true,
  upstreamReachable: true,
  status: 'ready',
  latestObservationAvailable: true,
  lastObservationAt: '2026-04-01T13:26:00Z',
  freshnessAgeSeconds: 4,
  isFresh: true,
  message: null,
}

export const liveSensorLatest: LiveSensorLatestResponse = {
  ok: true,
  enabled: true,
  upstreamReachable: true,
  sensorId: 'arduino-ttyACM0',
  sourcePort: '/dev/ttyACM0',
  schemaVersion: 'sensor-observation-v1',
  observedAt: '2026-04-01T13:26:00Z',
  temperatureC: 23.4,
  humidityPct: 51.2,
  pressureHpa: 1008.7,
  message: null,
}

export const liveSensorThresholdStatus: LiveSensorThresholdStatusResponse = {
  ok: true,
  enabled: true,
  upstreamReachable: true,
  observedAt: '2026-04-01T13:26:00Z',
  sensorId: 'arduino-ttyACM0',
  sourcePort: '/dev/ttyACM0',
  schemaVersion: 'sensor-observation-v1',
  hasActiveAlarms: true,
  overallStatus: 'warning',
  activeAlarms: [
    {
      code: 'humidity_alarm',
      level: 'warning',
      message: 'Humidity threshold is in alarm state.',
    },
  ],
  thresholdStatus: {
    temperature: { available: true, value: 23.4, unit: 'C', status: 'normal', alarm: false },
    humidity: { available: true, value: 51.2, unit: '%', status: 'warning', alarm: true },
    pressure: { available: true, value: 1008.7, unit: 'hPa', status: 'normal', alarm: false },
  },
  message: null,
}

const baseTime = new Date('2026-04-01T13:26:00Z').getTime()

export const liveSensorHistory: LiveSensorHistoryResponse = {
  ok: true,
  enabled: true,
  upstreamReachable: true,
  id: 'demo-sensor-history',
  sensorId: 'arduino-ttyACM0',
  sourcePort: '/dev/ttyACM0',
  schemaVersion: 'sensor-observation-v1',
  observedAt: '2026-04-01T13:26:00Z',
  rangeMinutes: 180,
  maxPoints: 180,
  points: Array.from({ length: 36 }, (_, index) => {
    const step = 35 - index
    const timestamp = new Date(baseTime - step * 5 * 60_000).toISOString()

    return {
      observedAt: timestamp,
      temperatureC: Number((22.6 + Math.sin(index / 4) * 0.8 + index * 0.02).toFixed(1)),
      humidityPct: Number((49.5 + Math.cos(index / 5) * 3.2 - index * 0.04).toFixed(1)),
      pressureHpa: Number((1008.2 + Math.sin(index / 6) * 1.1).toFixed(1)),
    }
  }),
  message: null,
}
