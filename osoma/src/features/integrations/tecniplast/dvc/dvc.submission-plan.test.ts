import { describe, expect, it } from 'vitest'
import {
  buildDvcSubmissionPlan,
  createQueuedJobFromSubmission,
} from './dvc.submission-plan.ts'
import {
  TaskAggregationType,
  TaskPartitionType,
} from './dvc.integration.api.ts'

const target = {
  uuid: 'cage-1',
  humanReadableId: 'S81P-40648',
  status: 'TERMINATED' as const,
  registrationEventDate: '2026-02-18T00:00:00Z',
  lastEventDate: '2026-02-19T00:00:00Z',
  rfids: [],
}

describe('buildDvcSubmissionPlan', () => {
  it('builds the shared-window payload and queued job metadata used by the UI', () => {
    const [pending] = buildDvcSubmissionPlan({
      appId: 'app-1',
      targetKind: 'CAGE',
      windowMode: 'SHARED',
      selectedTargets: [target],
      selectedMetrics: ['ACTIVATION'],
      filename: 'dvc-export',
      aggregation: TaskAggregationType.HOUR,
      partition: TaskPartitionType.SINGLE_FILE_BY_CAGE,
      startDate: '2026-02-18T00:00:00Z',
      endDate: '2026-02-19T00:00:00Z',
    })

    expect(pending.payload).toEqual({
      aggrType: 'HOUR',
      ids: ['S81P-40648'],
      metrics: ['ACTIVATION'],
      outputFilename: 'dvc-export',
      resourceType: 'CAGE',
      start: '2026-02-18T00:00:00Z',
      stop: '2026-02-19T00:00:00Z',
      partitionType: 'SINGLE_FILE_BY_CAGE',
    })

    expect(createQueuedJobFromSubmission(pending, 26052, '2026-03-13T09:00:00Z')).toMatchObject({
      appId: 'app-1',
      id: 26052,
      state: 'SUBMITTED',
      createdAt: '2026-03-13T09:00:00Z',
      lastCheckedAt: '2026-03-13T09:00:00Z',
      targetLabel: '1 targets',
      selectedIds: ['S81P-40648'],
      requestedStart: '2026-02-18T00:00:00Z',
      requestedStop: '2026-02-19T00:00:00Z',
    })
  })

  it('builds one maximal-window submission per selected target', () => {
    const plan = buildDvcSubmissionPlan({
      appId: 'app-1',
      targetKind: 'CAGE',
      windowMode: 'MAX_PER_TARGET',
      selectedTargets: [target],
      selectedMetrics: ['ACTIVATION'],
      filename: 'dvc-export',
      aggregation: TaskAggregationType.HOUR,
      partition: TaskPartitionType.SINGLE_FILE_BY_CAGE,
      startDate: '',
      endDate: '',
    })

    expect(plan).toHaveLength(1)
    expect(plan[0].payload).toEqual({
      aggrType: 'HOUR',
      ids: ['S81P-40648'],
      metrics: ['ACTIVATION'],
      outputFilename: 'dvc-export-s81p-40648',
      resourceType: 'CAGE',
      start: '2026-02-18T00:00:00Z',
      stop: '2026-02-19T00:00:00Z',
      partitionType: 'SINGLE_FILE_BY_CAGE',
    })
    expect(plan[0].queuedJob.targetLabel).toBe('S81P-40648')
  })
})
