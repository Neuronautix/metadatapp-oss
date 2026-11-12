import { apiFetch } from '@/lib/api'
import type { AiChatRequest, AiChatResponse, AiStatusResponse, AiSuggestRequest, AiSuggestResponse } from './assistant.types'

export function getAiStatus() {
  return apiFetch<AiStatusResponse>('/ai/status')
}

export function sendAiChat(payload: AiChatRequest) {
  return apiFetch<AiChatResponse>('/ai/chat', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

/**
 * Agentic chat: the backend runs a bounded multi-step tool-use loop and returns
 * the final answer plus a tool-call provenance trail. Safe to call regardless of
 * server config — it degrades to single-shot chat when AI_AGENTIC_MODE_ENABLED is
 * off. Gated on the frontend by the `feature.aiAssistant.agentic` flag.
 */
export function sendAgenticChat(payload: AiChatRequest) {
  return apiFetch<AiChatResponse>('/ai/agentic-chat', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function requestAiSuggestion(payload: AiSuggestRequest) {
  return apiFetch<AiSuggestResponse>('/ai/suggest', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}
