import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { StudiesPage } from './StudiesPage.tsx'
import { getStudies } from './study.api.ts'

vi.mock('./study.api.ts', () => ({
  getStudies: vi.fn(),
}))

const mockedGetStudies = vi.mocked(getStudies)

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

describe('StudiesPage', () => {
  it('shows the ElabFTW sync badge only for synced studies', async () => {
    mockedGetStudies.mockResolvedValue({
      data: [
        {
          id: 'EXP-401',
          name: 'Synced study',
          elaftwExternalId: 'elab-exp-401',
          investigationId: 'PRJ-1',
          investigationName: 'Investigation 1',
          assayId: 'PRO-1',
          assayName: 'Assay 1',
          status: 'running',
          startedAt: '2026-04-01T10:00:00Z',
          runCount: 1,
          sampleCount: 2,
          dataVolumeGb: 3,
          qcStatus: 'pass',
          fairScore: { total: 0, findable: 0, accessible: 0, interoperable: 0, reusable: 0 },
          identifiers: [],
          provenance: [],
          createdAt: '2026-04-01T10:00:00Z',
          updatedAt: '2026-04-01T10:00:00Z',
        },
        {
          id: 'EXP-402',
          name: 'Local only study',
          investigationId: 'PRJ-2',
          investigationName: 'Investigation 2',
          assayId: 'PRO-2',
          assayName: 'Assay 2',
          status: 'draft',
          startedAt: '2026-04-02T10:00:00Z',
          runCount: 0,
          sampleCount: 0,
          dataVolumeGb: 0,
          qcStatus: 'review',
          fairScore: { total: 0, findable: 0, accessible: 0, interoperable: 0, reusable: 0 },
          identifiers: [],
          provenance: [],
          createdAt: '2026-04-02T10:00:00Z',
          updatedAt: '2026-04-02T10:00:00Z',
        },
      ],
      total: 2,
      page: 1,
      pageSize: 7,
    })

    render(<StudiesPage />, { wrapper: createWrapper() })

    expect(await screen.findByText('Synced study')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'New study' })).toHaveAttribute('href', '/metadata/experiments/new')
    expect(screen.getAllByText('Synced to ElabFTW')).toHaveLength(1)
    expect(screen.queryByAltText('ElabFTW')).not.toBeInTheDocument()
  })
})
