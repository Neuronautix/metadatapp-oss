import { http, HttpResponse } from 'msw'

let mockProviderConfig = {
  provider: null as string | null,
  keyPreview: null as string | null,
  model: null as string | null,
  configured: false,
}

export const aiProviderHandlers = [
  http.get('/api/ai/provider-config', () => {
    return HttpResponse.json({ ...mockProviderConfig })
  }),

  http.patch('/api/ai/provider-config', async ({ request }) => {
    const body = await request.json() as {
      provider?: string | null
      apiKey?: string
      model?: string | null
    }

    if (body.provider !== undefined) {
      mockProviderConfig.provider = body.provider
    }

    if (body.apiKey !== undefined) {
      const key = body.apiKey ?? ''
      if (key === '') {
        mockProviderConfig.keyPreview = null
      } else {
        const len = key.length
        mockProviderConfig.keyPreview =
          len <= 8
            ? '•'.repeat(len)
            : key.slice(0, 4) + '•'.repeat(Math.max(0, len - 8)) + key.slice(-4)
      }
    }

    if (body.model !== undefined) {
      mockProviderConfig.model = body.model
    }

    mockProviderConfig.configured =
      !!mockProviderConfig.provider && !!mockProviderConfig.keyPreview

    return HttpResponse.json({ ...mockProviderConfig })
  }),
]
