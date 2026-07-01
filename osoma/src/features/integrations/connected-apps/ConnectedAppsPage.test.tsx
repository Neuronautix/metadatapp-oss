import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { ConnectedAppsPage } from './ConnectedAppsPage.tsx'
import { getConnectedApps } from './connected-apps.api.ts'

vi.mock('./connected-apps.api.ts', () => ({
  getConnectedApps: vi.fn(),
}))

vi.mock('@/feature-flags/useFeatureFlag.ts', () => ({
  useFeatureFlag: vi.fn(() => false),
}))

const mockedGetConnectedApps = vi.mocked(getConnectedApps)

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  })

  return ({ children }: { children: ReactNode }) => (
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
    </MemoryRouter>
  )
}

describe('ConnectedAppsPage', () => {
  it('renders the configured app logo on the ElabFTW card', async () => {
    mockedGetConnectedApps.mockResolvedValue({
      'hydra:member': [
        {
          id: '1',
          '@id': '/connected_apps/1',
          name: 'ElabFTW',
          code: 'elabftw',
          description: 'Open source electronic lab notebook for researchers.',
          isActive: true,
          lastSyncAt: '2026-04-01T10:00:00Z',
          logoUrl: '/images/apps/elabftw.svg',
        },
      ],
      'hydra:totalItems': 1,
    })

    render(<ConnectedAppsPage />, { wrapper: createWrapper() })

    expect(await screen.findByRole('img', { name: 'ElabFTW' })).toHaveAttribute('src', '/Connected Apps Logo/Elabftw.png')
  })

  it('renders the NIH CDE Repository card', async () => {
    mockedGetConnectedApps.mockResolvedValue({
      'hydra:member': [
        {
          id: 'nih_cde',
          '@id': '/connected_apps/nih_cde',
          name: 'NIH CDE Repository',
          code: 'nih_cde',
          description: 'NIH Common Data Elements repository.',
          isActive: false,
          lastSyncAt: null,
        },
      ],
      'hydra:totalItems': 1,
    })

    render(<ConnectedAppsPage />, { wrapper: createWrapper() })

    expect(await screen.findByRole('heading', { name: 'NIH CDE Repository' })).toBeInTheDocument()
    expect(screen.getByLabelText('View details for NIH CDE Repository')).toHaveAttribute('href', '/connected-apps/nih_cde')
  })
})
