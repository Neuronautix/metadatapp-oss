import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { AssaysPage } from './AssaysPage.tsx'
import { getAssays } from './assay.api.ts'

vi.mock('./assay.api.ts', () => ({
  getAssays: vi.fn(),
}))

const mockedGetAssays = vi.mocked(getAssays)

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

describe('AssaysPage', () => {
  it('shows the ElabFTW sync badge only for synced assays', async () => {
    mockedGetAssays.mockResolvedValue({
      data: [
        {
          id: 'PRO-112',
          name: 'Synced assay',
          elaftwExternalId: 'elab-pro-112',
          version: '1.0',
          method: 'Proteomics',
          owner: 'Lab',
          reviewStatus: 'current',
          lastReviewedAt: '2026-04-01T10:00:00Z',
          createdAt: '2026-04-01T10:00:00Z',
          updatedAt: '2026-04-01T10:00:00Z',
        },
        {
          id: 'PRO-113',
          name: 'Internal assay',
          version: '1.0',
          method: 'Imaging',
          owner: 'Lab',
          reviewStatus: 'review-due',
          lastReviewedAt: '2026-04-02T10:00:00Z',
          createdAt: '2026-04-02T10:00:00Z',
          updatedAt: '2026-04-02T10:00:00Z',
        },
      ],
      total: 2,
      page: 1,
      pageSize: 7,
    })

    render(<AssaysPage />, { wrapper: createWrapper() })

    expect(await screen.findByText('Synced assay')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'New assay' })).toHaveAttribute('href', '/metadata/procedures/new')
    expect(screen.getAllByText('Synced to ElabFTW')).toHaveLength(1)
    expect(screen.queryByAltText('ElabFTW')).not.toBeInTheDocument()
  })
})
