import { beforeEach, describe, expect, it, vi } from 'vitest'

const realSession = {
  accessToken: 'real-access-token',
  idToken: 'real-id-token',
  refreshToken: 'real-refresh-token',
  expiresAt: Math.floor(Date.now() / 1000) + 3600,
  user: { sub: 'user-1' },
}

describe('downloadTaskResult', () => {
  beforeEach(() => {
    localStorage.setItem('use_msw', 'false')
    localStorage.setItem('metadatapp_auth_session', JSON.stringify(realSession))
    vi.resetModules()
  })

  it('uses the authenticated browser fetch path for ZIP export', async () => {
    const fetchSpy = vi.fn().mockResolvedValue({
      ok: true,
      blob: async () => new Blob(['zip-content']),
    })
    vi.stubGlobal('fetch', fetchSpy)

    const { downloadTaskResult } = await import('./dvc.integration.api.ts')

    const blob = await downloadTaskResult('app-123', 42)
    const [, init] = fetchSpy.mock.calls[0]

    expect(fetchSpy).toHaveBeenCalledWith(
      '/api/connected_apps/app-123/dvc/42/download',
      expect.any(Object),
    )
    expect(init.method).toBe('GET')
    expect(init.headers.Authorization).toBe('Bearer real-access-token')
    expect(blob.size).toBeGreaterThan(0)
  })
})
