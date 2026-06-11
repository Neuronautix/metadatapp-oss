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

export function requestAiSuggestion(payload: AiSuggestRequest) {
  return apiFetch<AiSuggestResponse>('/ai/suggest', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}
