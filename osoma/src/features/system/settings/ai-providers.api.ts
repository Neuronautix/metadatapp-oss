import { apiFetch } from '@/lib/api.ts'

export type LlmProviderName = 'openai' | 'anthropic'

export type LlmProviderCredential = {
    provider: LlmProviderName
    model: string
    configured: boolean
    source: 'user' | 'environment' | 'none'
    apiKeyHint?: string | null
    active: boolean
    updatedAt?: string
}

export type LlmProviderStatus = {
    enabled: boolean
    available: boolean
    configured: boolean
    realProvider: boolean
    provider: LlmProviderName
    model: string
    message: string
    capabilities: string[]
}

export function getLlmProviders() {
    return apiFetch<{ providers: LlmProviderCredential[] }>('/ai/providers')
}

export function saveLlmProvider(provider: LlmProviderName, payload: { apiKey?: string; model?: string; active?: boolean }) {
    return apiFetch<LlmProviderCredential>(`/ai/providers/${provider}`, {
        method: 'PATCH',
        body: JSON.stringify(payload),
    })
}

export function testLlmProvider(provider: LlmProviderName) {
    return apiFetch<{ status: LlmProviderStatus }>(`/ai/providers/${provider}/test`, {
        method: 'POST',
        body: '{}',
    })
}
