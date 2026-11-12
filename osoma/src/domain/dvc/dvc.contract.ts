import { z } from 'zod'
import type { components } from './dvc-proxy.generated.ts'

export type DvcMetric = components['schemas']['Metric']
export type DvcRfidInterval = components['schemas']['RfidInterval']
export type DvcGlobResult = components['schemas']['GlobResult']
export type DvcGlobSearchResponse = components['schemas']['CageSearchResponse']
export type DvcTaskSubmission = components['schemas']['TaskSubmission']
export type DvcTaskSubmissionResponse = components['schemas']['TaskSubmissionResponse']
export type DvcTaskState = components['schemas']['TaskState']
export type DvcTestApiKeyResponse = components['schemas']['TestApiKeyResponse']
export type DvcGlobSearch = components['schemas']['GlobSearchRequest']
export interface DvcTaskSubmissionFailure {
  requestId?: string
  message?: string
  detail?: string
}

const dvcResponseDateTimeSchema = z.string().datetime({ offset: true })
const dvcRequestDateTimeSchema = z.string().regex(
  /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/,
  'Expected YYYY-MM-DDTHH:MM:SSZ'
)

export const dvcMetricSchema: z.ZodType<DvcMetric> = z.object({
  id: z.string().min(1),
  description: z.string(),
  type: z.enum(['METRIC', 'ENV_PARAM']),
})

export const dvcMetricsSchema = z.array(dvcMetricSchema)

export const dvcRfidIntervalSchema: z.ZodType<DvcRfidInterval> = z.object({
  rfid: z.string().min(1),
  start: z.number().finite(),
  stop: z.number().finite(),
})

export const dvcGlobResultSchema: z.ZodType<DvcGlobResult> = z.object({
  uuid: z.string().min(1),
  humanReadableId: z.string().min(1),
  status: z.enum(['RUNNING', 'OUT_OF_RACK', 'TERMINATED']),
  lastEventDate: dvcResponseDateTimeSchema,
  registrationEventDate: dvcResponseDateTimeSchema,
  rfids: z.array(dvcRfidIntervalSchema),
})

export const dvcGlobResultsSchema = z.array(dvcGlobResultSchema)

export const dvcGlobSearchResponseSchema: z.ZodType<DvcGlobSearchResponse> = z.object({
  cages: dvcGlobResultsSchema,
  min_date: dvcResponseDateTimeSchema.nullable(),
  max_date: dvcResponseDateTimeSchema.nullable(),
})

export const dvcTaskSubmissionSchema: z.ZodType<DvcTaskSubmission> = z.object({
  aggrType: z.enum(['HOUR', 'MINUTE']),
  ids: z.array(z.string().min(1)).min(1),
  metrics: z.array(z.string().min(1)).min(1),
  outputFilename: z.string().min(1),
  resourceType: z.enum(['CAGE', 'ANIMAL']),
  start: dvcRequestDateTimeSchema,
  stop: dvcRequestDateTimeSchema,
  partitionType: z.enum(['SINGLE_FILE', 'SINGLE_FILE_BY_DAY', 'SINGLE_FILE_BY_CAGE']),
})

export const dvcTaskSubmissionResponseSchema: z.ZodType<DvcTaskSubmissionResponse> = z.object({
  taskId: z.number().finite(),
  requestId: z.string().min(1),
})

export const dvcTaskSubmissionFailureSchema: z.ZodType<DvcTaskSubmissionFailure> = z.object({
  requestId: z.string().min(1).optional(),
  message: z.string().min(1).optional(),
  detail: z.string().min(1).optional(),
})

export const dvcTaskStateSchema: z.ZodType<DvcTaskState> = z.object({
  id: z.number().finite(),
  state: z.enum(['SUBMITTED', 'RUNNING', 'COMPLETED', 'FAILED']),
  createdAt: dvcResponseDateTimeSchema,
  finishedAt: dvcResponseDateTimeSchema.optional(),
  errorMessage: z.string().optional(),
  fileSize: z.number().finite().optional(),
  checksum: z.string().optional(),
})

export const dvcTaskStateResponseSchema = z.union([
  dvcTaskStateSchema,
  z.array(dvcTaskStateSchema),
])

export const dvcGlobSearchSchema: z.ZodType<DvcGlobSearch> = z.object({
  patterns: z.array(z.string().min(1)).min(1),
})

export const dvcTestApiKeyResponseSchema: z.ZodType<DvcTestApiKeyResponse> = z.union([
  z.string(),
  z.record(z.string(), z.unknown()),
])

const summarizeIssues = (issues: z.ZodIssue[]) =>
  issues
    .slice(0, 3)
    .map((issue) => {
      const path = issue.path.length ? issue.path.join('.') : 'root'
      return `${path}: ${issue.message}`
    })
    .join('; ')

export const parseDvcContract = <T>(schema: z.ZodType<T>, payload: unknown, context: string): T => {
  const parsed = schema.safeParse(payload)

  if (parsed.success) {
    return parsed.data
  }

  throw new Error(`${context} returned an unexpected response shape. ${summarizeIssues(parsed.error.issues)}`)
}

export const validateDvcRequest = <T>(schema: z.ZodType<T>, payload: unknown, context: string): T => {
  const parsed = schema.safeParse(payload)

  if (parsed.success) {
    return parsed.data
  }

  throw new Error(`${context} is invalid. ${summarizeIssues(parsed.error.issues)}`)
}
