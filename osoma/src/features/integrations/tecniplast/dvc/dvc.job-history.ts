import type {
  TaskAggregationType,
  TaskPartitionType,
  TaskResourceType,
  TaskState,
} from './dvc.integration.api.ts'

const STORAGE_KEY = 'dvc-extraction-jobs'
export const DVC_EXTRACTION_JOBS_UPDATED_EVENT = 'dvc-extraction-jobs-updated'

export interface DvcExtractionJob extends TaskState {
  appId: string
  metrics: string[]
  outputFilename: string
  partitionType: TaskPartitionType
  resourceType: TaskResourceType
  selectedIds: string[]
  aggrType: TaskAggregationType
  lastCheckedAt?: string
  targetLabel?: string
  requestedStart?: string
  requestedStop?: string
}

const isBrowser = () => typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'

const isValidExtractionJob = (value: unknown): value is DvcExtractionJob => {
  if (!value || typeof value !== 'object') {
    return false
  }

  const candidate = value as Partial<DvcExtractionJob>
  return (
    typeof candidate.id === 'number' &&
    Number.isFinite(candidate.id) &&
    typeof candidate.appId === 'string' &&
    candidate.appId.length > 0 &&
    typeof candidate.createdAt === 'string' &&
    candidate.createdAt.length > 0 &&
    typeof candidate.state === 'string'
  )
}

export const loadDvcExtractionJobs = (): DvcExtractionJob[] => {
  if (!isBrowser()) {
    return []
  }

  const raw = window.localStorage.getItem(STORAGE_KEY)
  if (!raw) {
    return []
  }

  try {
    const parsed = JSON.parse(raw)
    const jobs = Array.isArray(parsed) ? parsed.filter(isValidExtractionJob) : []

    if (Array.isArray(parsed) && jobs.length !== parsed.length) {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(jobs))
    }

    return jobs
  } catch {
    return []
  }
}

export const saveDvcExtractionJobs = (jobs: DvcExtractionJob[]) => {
  if (!isBrowser()) {
    return
  }

  const sanitizedJobs = jobs.filter(isValidExtractionJob)
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(sanitizedJobs))
  window.dispatchEvent(new CustomEvent(DVC_EXTRACTION_JOBS_UPDATED_EVENT, { detail: sanitizedJobs }))
}

export const upsertDvcExtractionJobs = (nextJobs: DvcExtractionJob[]) => {
  const current = loadDvcExtractionJobs()
  const merged = new Map<number, DvcExtractionJob>()

  current.forEach((job) => merged.set(job.id, job))
  nextJobs.filter(isValidExtractionJob).forEach((job) => merged.set(job.id, job))

  const ordered = Array.from(merged.values()).sort((left, right) => {
    return new Date(right.createdAt).getTime() - new Date(left.createdAt).getTime()
  })

  saveDvcExtractionJobs(ordered)
  return ordered
}

export const getDvcExtractionJobsForApp = (appId: string) => {
  return loadDvcExtractionJobs().filter((job) => job.appId === appId)
}
