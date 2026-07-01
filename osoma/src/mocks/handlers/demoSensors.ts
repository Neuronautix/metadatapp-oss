import { http, HttpResponse } from 'msw'
import { liveSensorHealth, liveSensorHistory, liveSensorLatest, liveSensorThresholdStatus } from '@/mocks/data/demoSensors.ts'
import { withLatency } from './utils.ts'

export const demoSensorsHandlers = [
  http.get('/api/demo/sensors/health', async () => {
    await withLatency()
    return HttpResponse.json(liveSensorHealth)
  }),
  http.get('/api/demo/sensors/latest', async () => {
    await withLatency()
    return HttpResponse.json(liveSensorLatest)
  }),
  http.get('/api/demo/sensors/threshold-status', async () => {
    await withLatency()
    return HttpResponse.json(liveSensorThresholdStatus)
  }),
  http.get('/api/demo/sensors/history', async () => {
    await withLatency()
    return HttpResponse.json(liveSensorHistory)
  }),
]
