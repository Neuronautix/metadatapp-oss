import { beforeEach, describe, expect, it, vi } from 'vitest'

describe('apiFetch request resolution', () => {
  beforeEach(() => {
    localStorage.setItem('use_msw', 'false')
    vi.resetModules()
    vi.unstubAllEnvs()
    vi.unstubAllGlobals()
  })

  it('routes Tecniplast API calls through /api when VITE_API_URL points at the docker-only frontend hostname', async () => {
    vi.stubEnv('VITE_API_URL', 'https://frontend')

    const fetchSpy = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: [], page: 1, pageSize: 8, total: 0 }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchSpy)

    const { getAlarms } = await import('@/features/integrations/tecniplast/alarms/alarms.api.ts')

    await getAlarms({})

    expect(fetchSpy).toHaveBeenCalledWith('/api/tp/alarms', expect.any(Object))
  })

  it('routes API calls through /api when VITE_API_URL points at the docker-only api hostname', async () => {
    vi.stubEnv('VITE_API_URL', 'http://api')

    const fetchSpy = vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ data: [], page: 1, pageSize: 8, total: 0 }), {
        status: 200,
        headers: { 'Content-Type': 'application/json' },
      }),
    )

    vi.stubGlobal('fetch', fetchSpy)

    const { getAlarms } = await import('@/features/integrations/tecniplast/alarms/alarms.api.ts')

    await getAlarms({})

    expect(fetchSpy).toHaveBeenCalledWith('/api/tp/alarms', expect.any(Object))
  })
})
