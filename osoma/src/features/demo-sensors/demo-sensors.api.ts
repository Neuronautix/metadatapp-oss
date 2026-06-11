import { apiFetch } from '@/lib/api.ts'
import type {
  LiveSensorHealthResponse,
  LiveSensorHistoryResponse,
  LiveSensorLatestResponse,
  LiveSensorSnapshot,
  LiveSensorThresholdStatusResponse,
} from './demo-sensors.types.ts'

export function getLiveSensorHealth() {
  return apiFetch<LiveSensorHealthResponse>('/demo/sensors/health')
}

export function getLiveSensorLatest() {
  return apiFetch<LiveSensorLatestResponse>('/demo/sensors/latest')
}

export function getLiveSensorThresholdStatus() {
  return apiFetch<LiveSensorThresholdStatusResponse>('/demo/sensors/threshold-status')
}

export function getLiveSensorHistory(params?: { rangeMinutes?: number; maxPoints?: number }) {
  const searchParams = new URLSearchParams()
  if (params?.rangeMinutes) searchParams.set('rangeMinutes', String(params.rangeMinutes))
  if (params?.maxPoints) searchParams.set('maxPoints', String(params.maxPoints))
  const query = searchParams.toString()

  return apiFetch<LiveSensorHistoryResponse>(`/demo/sensors/history${query ? `?${query}` : ''}`)
}

/**
 * Loads the live sensor health, latest observation, and threshold state in
 * parallel, then derives a UI-friendly connection state for the panel.
 */
export async function getLiveSensorSnapshot(): Promise<LiveSensorSnapshot> {
  const [health, latest, threshold] = await Promise.all([
    getLiveSensorHealth(),
    getLiveSensorLatest(),
    getLiveSensorThresholdStatus(),
  ])

  const connectionState =
    !health.enabled ? 'disabled'
      : !health.upstreamReachable ? 'unreachable'
        : health.status === 'waiting_for_data' ? 'waiting'
          : health.status === 'stale' ? 'stale'
            : 'connected'

  return {
    connectionState,
    health,
    latest,
    threshold,
  }
}
