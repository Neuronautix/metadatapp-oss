import { render, screen, act } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { LiveSensorPanel } from './LiveSensorPanel.tsx'
import { getLiveSensorHistory, getLiveSensorSnapshot } from './demo-sensors.api.ts'

vi.mock('./demo-sensors.api.ts', () => ({
  getLiveSensorSnapshot: vi.fn(),
  getLiveSensorHistory: vi.fn(),
}))

vi.mock('recharts', async (importOriginal) => {
  const actual = await importOriginal<typeof import('recharts')>()

  return {
    ...actual,
    ResponsiveContainer: ({ children }: { children: ReactNode }) => (
      <div style={{ width: 1024, height: 288 }}>{children}</div>
    ),
  }
})

const mockedGetLiveSensorSnapshot = vi.mocked(getLiveSensorSnapshot)
const mockedGetLiveSensorHistory = vi.mocked(getLiveSensorHistory)

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  })

  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  )
}

describe('LiveSensorPanel', () => {
  afterEach(() => {
    vi.useRealTimers()
    mockedGetLiveSensorSnapshot.mockReset()
    mockedGetLiveSensorHistory.mockReset()
  })

  it('renders live metrics, waiting/stale messaging, and hides pressure when unavailable', async () => {
    mockedGetLiveSensorSnapshot.mockResolvedValue({
      connectionState: 'stale',
      health: {
        ok: true,
        enabled: true,
        upstreamReachable: true,
        status: 'stale',
        latestObservationAvailable: true,
        lastObservationAt: '2026-04-01T12:00:00Z',
        freshnessAgeSeconds: 42,
        isFresh: false,
        message: 'Latest sensor data is stale (42 seconds old).',
      },
      latest: {
        ok: true,
        enabled: true,
        upstreamReachable: true,
        sensorId: 'arduino-ttyACM0',
        sourcePort: '/dev/ttyACM0',
        schemaVersion: 'sensor-observation-v1',
        observedAt: '2026-04-01T12:00:00Z',
        temperatureC: 23.4,
        humidityPct: 51.2,
        pressureHpa: null,
        message: 'Latest sensor data is stale (42 seconds old).',
      },
      threshold: {
        ok: true,
        enabled: true,
        upstreamReachable: true,
        observedAt: '2026-04-01T12:00:00Z',
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
          pressure: { available: false, value: null, unit: 'hPa', status: 'unavailable', alarm: false },
        },
        message: 'Latest sensor data is stale (42 seconds old).',
      },
    })
    mockedGetLiveSensorHistory.mockResolvedValue({
      ok: true,
      enabled: true,
      upstreamReachable: true,
      id: 'demo-sensor-history',
      sensorId: 'arduino-ttyACM0',
      sourcePort: '/dev/ttyACM0',
      schemaVersion: 'sensor-observation-v1',
      observedAt: '2026-04-01T12:00:00Z',
      rangeMinutes: 180,
      maxPoints: 180,
      points: [
        { observedAt: '2026-04-01T11:50:00Z', temperatureC: 22.8, humidityPct: 52.1, pressureHpa: 1005.4 },
        { observedAt: '2026-04-01T12:00:00Z', temperatureC: 23.4, humidityPct: 51.2, pressureHpa: 1005.2 },
      ],
      message: null,
    })

    render(<LiveSensorPanel refetchIntervalMs={false as never} />, { wrapper: createWrapper() })

    expect(await screen.findByText('Stale')).toBeInTheDocument()
    expect(screen.getByText('23.4')).toBeInTheDocument()
    expect(screen.getByText('51.2')).toBeInTheDocument()
    expect(screen.queryByText('Pressure')).not.toBeInTheDocument()
    expect(screen.getByText(/humidity threshold is in alarm state/i)).toBeInTheDocument()
    expect(screen.getByText(/latest sensor data is stale/i)).toBeInTheDocument()
    expect(screen.getByText(/live environment timeline/i)).toBeInTheDocument()
    expect(screen.getByText(/2 points/i)).toBeInTheDocument()
  })

  it('polls for refreshed values', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true })
    mockedGetLiveSensorSnapshot
      .mockResolvedValueOnce({
        connectionState: 'connected',
        health: {
          ok: true,
          enabled: true,
          upstreamReachable: true,
          status: 'ready',
          latestObservationAvailable: true,
          lastObservationAt: '2026-04-01T12:00:00Z',
          freshnessAgeSeconds: 5,
          isFresh: true,
          message: null,
        },
        latest: {
          ok: true,
          enabled: true,
          upstreamReachable: true,
          sensorId: 'arduino-ttyACM0',
          sourcePort: '/dev/ttyACM0',
          schemaVersion: 'sensor-observation-v1',
          observedAt: '2026-04-01T12:00:00Z',
          temperatureC: 21.5,
          humidityPct: 50.1,
          pressureHpa: 1005.5,
          message: null,
        },
        threshold: {
          ok: true,
          enabled: true,
          upstreamReachable: true,
          observedAt: '2026-04-01T12:00:00Z',
          sensorId: 'arduino-ttyACM0',
          sourcePort: '/dev/ttyACM0',
          schemaVersion: 'sensor-observation-v1',
          hasActiveAlarms: false,
          overallStatus: 'normal',
          activeAlarms: [],
          thresholdStatus: {
            temperature: { available: true, value: 21.5, unit: 'C', status: 'normal', alarm: false },
            humidity: { available: true, value: 50.1, unit: '%', status: 'normal', alarm: false },
            pressure: { available: true, value: 1005.5, unit: 'hPa', status: 'normal', alarm: false },
          },
          message: null,
        },
      })
      .mockResolvedValue({
        connectionState: 'connected',
        health: {
          ok: true,
          enabled: true,
          upstreamReachable: true,
          status: 'ready',
          latestObservationAvailable: true,
          lastObservationAt: '2026-04-01T12:00:05Z',
          freshnessAgeSeconds: 1,
          isFresh: true,
          message: null,
        },
        latest: {
          ok: true,
          enabled: true,
          upstreamReachable: true,
          sensorId: 'arduino-ttyACM0',
          sourcePort: '/dev/ttyACM0',
          schemaVersion: 'sensor-observation-v1',
          observedAt: '2026-04-01T12:00:05Z',
          temperatureC: 22.9,
          humidityPct: 49.8,
          pressureHpa: 1005.2,
          message: null,
        },
        threshold: {
          ok: true,
          enabled: true,
          upstreamReachable: true,
          observedAt: '2026-04-01T12:00:05Z',
          sensorId: 'arduino-ttyACM0',
          sourcePort: '/dev/ttyACM0',
          schemaVersion: 'sensor-observation-v1',
          hasActiveAlarms: false,
          overallStatus: 'normal',
          activeAlarms: [],
          thresholdStatus: {
            temperature: { available: true, value: 22.9, unit: 'C', status: 'normal', alarm: false },
            humidity: { available: true, value: 49.8, unit: '%', status: 'normal', alarm: false },
            pressure: { available: true, value: 1005.2, unit: 'hPa', status: 'normal', alarm: false },
          },
          message: null,
        },
      })
    mockedGetLiveSensorHistory
      .mockResolvedValueOnce({
        ok: true,
        enabled: true,
        upstreamReachable: true,
        id: 'demo-sensor-history',
        sensorId: 'arduino-ttyACM0',
        sourcePort: '/dev/ttyACM0',
        schemaVersion: 'sensor-observation-v1',
        observedAt: '2026-04-01T12:00:00Z',
        rangeMinutes: 180,
        maxPoints: 180,
        points: [
          { observedAt: '2026-04-01T11:55:00Z', temperatureC: 21.2, humidityPct: 50.5, pressureHpa: 1005.0 },
          { observedAt: '2026-04-01T12:00:00Z', temperatureC: 21.5, humidityPct: 50.1, pressureHpa: 1005.5 },
        ],
        message: null,
      })
      .mockResolvedValue({
        ok: true,
        enabled: true,
        upstreamReachable: true,
        id: 'demo-sensor-history',
        sensorId: 'arduino-ttyACM0',
        sourcePort: '/dev/ttyACM0',
        schemaVersion: 'sensor-observation-v1',
        observedAt: '2026-04-01T12:00:05Z',
        rangeMinutes: 180,
        maxPoints: 180,
        points: [
          { observedAt: '2026-04-01T12:00:00Z', temperatureC: 21.5, humidityPct: 50.1, pressureHpa: 1005.5 },
          { observedAt: '2026-04-01T12:00:05Z', temperatureC: 22.9, humidityPct: 49.8, pressureHpa: 1005.2 },
        ],
        message: null,
      })

    render(<LiveSensorPanel refetchIntervalMs={20} />, { wrapper: createWrapper() })

    expect(await screen.findByText('21.5')).toBeInTheDocument()

    await act(async () => {
      vi.advanceTimersByTime(20)
    })

    expect(await screen.findByText('22.9')).toBeInTheDocument()
  })
})
