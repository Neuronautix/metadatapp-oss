export type AiProviderStatus = {
  enabled: boolean
  available: boolean
  configured: boolean
  realProvider: boolean
  provider: string
  message: string
  capabilities: string[]
}

export type AiTrace = {
  traceId: string
  action: string
  route: string
  recordedAt: string
  resourceType?: string | null
  resourceId?: string | null
}

export type AiProvenance = {
  label: string
  value: string
  detail?: string
  candidates?: Array<{ id: string; label: string; score?: number }>
}

export type AiSuggestionItem = {
  field: string
  label: string
  preview: string
  reason: string
  value: unknown
}

export type AiStatusResponse = {
  status: AiProviderStatus
  trace: AiTrace
}

export type AiChatMessage = {
  role: 'assistant' | 'user'
  content: string
}

export type AiChatRequest = {
  messages: Array<{ role: 'assistant' | 'user' | 'system'; content: string }>
  context?: Record<string, unknown>
}

export type AiChatResponse = {
  reply: string
  status: AiProviderStatus
  trace: AiTrace
  provenance: AiProvenance[]
  warnings: string[]
  structuredOutput: Record<string, unknown>
}

export type AiSuggestAction =
  | 'suggest_form_inputs'
  | 'explain_missing_metadata'
  | 'draft_query_filters'
  | 'generate_export_content'

export type AiSuggestRequest = {
  action: AiSuggestAction
  contextType: string
  context: Record<string, unknown>
  prompt?: string
}

export type AiSuggestResponse = {
  action: AiSuggestAction
  summary: string
  status: AiProviderStatus
  trace: AiTrace
  suggestions: AiSuggestionItem[]
  structuredOutput: Record<string, unknown>
  provenance: AiProvenance[]
  warnings: string[]
  reviewRequired: boolean
  canApplyDirectly: boolean
}
