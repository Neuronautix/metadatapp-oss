import type { DvcExtractionJob } from './dvc.job-history.ts'
import {
  TaskAggregationType,
  TaskPartitionType,
  TaskResourceType,
  TaskStateEnum,
  type GlobResult,
  type TaskSubmission,
} from './dvc.integration.api.ts'

export type TargetKind = 'CAGE' | 'ANIMAL'
export type WindowMode = 'MAX_PER_TARGET' | 'SHARED'

export interface DvcPendingSubmission {
  payload: TaskSubmission
  queuedJob: Omit<DvcExtractionJob, 'id' | 'state' | 'createdAt' | 'lastCheckedAt'>
}

interface BuildDvcSubmissionPlanArgs {
  appId: string
  targetKind: TargetKind
  windowMode: WindowMode
  selectedTargets: GlobResult[]
  selectedMetrics: string[]
  filename: string
  aggregation: TaskAggregationType
  partition: TaskPartitionType
  startDate: string
  endDate: string
}

const buildResourceType = (targetKind: TargetKind) =>
  targetKind === 'CAGE' ? TaskResourceType.CAGE : TaskResourceType.ANIMAL

const ensureTargetWindow = (target: GlobResult) => {
  if (!target.registrationEventDate || !target.lastEventDate) {
    throw new Error(`Target ${target.humanReadableId} is missing a recording window.`)
  }
}

export const toDvcUtcDateTime = (value: string) => {
  const normalized = new Date(value).toISOString()
  return normalized.replace(/\.\d{3}Z$/, 'Z')
}

export const createQueuedJobFromSubmission = (
  pending: DvcPendingSubmission,
  taskId: number,
  createdAt = new Date().toISOString(),
): DvcExtractionJob => ({
  ...pending.queuedJob,
  id: taskId,
  state: TaskStateEnum.SUBMITTED,
  createdAt,
  lastCheckedAt: createdAt,
})

export const buildDvcSubmissionPlan = ({
  appId,
  targetKind,
  windowMode,
  selectedTargets,
  selectedMetrics,
  filename,
  aggregation,
  partition,
  startDate,
  endDate,
}: BuildDvcSubmissionPlanArgs): DvcPendingSubmission[] => {
  if (!selectedTargets.length) {
    return []
  }

  if (!selectedMetrics.length) {
    return []
  }

  if (windowMode === 'SHARED' && (!startDate || !endDate)) {
    throw new Error('Shared-window extractions require both a start and stop date.')
  }

  const resourceType = buildResourceType(targetKind)

  if (windowMode === 'MAX_PER_TARGET') {
    return selectedTargets.map((target) => {
      ensureTargetWindow(target)
      const outputFilename = `${filename}-${target.humanReadableId.toLowerCase()}`

      return {
        payload: {
          aggrType: aggregation,
          ids: [target.humanReadableId],
          metrics: selectedMetrics,
          outputFilename,
          resourceType,
          start: toDvcUtcDateTime(target.registrationEventDate),
          stop: toDvcUtcDateTime(target.lastEventDate),
          partitionType: partition,
        },
        queuedJob: {
          appId,
          aggrType: aggregation,
          metrics: selectedMetrics,
          outputFilename,
          partitionType: partition,
          resourceType,
          selectedIds: [target.humanReadableId],
          targetLabel: target.humanReadableId,
          requestedStart: target.registrationEventDate,
          requestedStop: target.lastEventDate,
        },
      }
    })
  }

  return [{
    payload: {
      aggrType: aggregation,
      ids: selectedTargets.map((target) => target.humanReadableId),
      metrics: selectedMetrics,
      outputFilename: filename,
      resourceType,
      start: toDvcUtcDateTime(startDate),
      stop: toDvcUtcDateTime(endDate),
      partitionType: partition,
    },
    queuedJob: {
      appId,
      aggrType: aggregation,
      metrics: selectedMetrics,
      outputFilename: filename,
      partitionType: partition,
      resourceType,
      selectedIds: selectedTargets.map((target) => target.humanReadableId),
      targetLabel: `${selectedTargets.length} ${targetKind === 'CAGE' ? 'targets' : 'animals'}`,
      requestedStart: toDvcUtcDateTime(startDate),
      requestedStop: toDvcUtcDateTime(endDate),
    },
  }]
}
