import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { DatasetsPage } from './DatasetsPage.tsx'
import { getDatasets } from './dataset.api.ts'

vi.mock('./dataset.api.ts', () => ({
  getDatasets: vi.fn(),
}))

const mockedGetDatasets = vi.mocked(getDatasets)

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

describe('DatasetsPage', () => {
  it('shows dataset create as unavailable instead of linking to a missing resource', async () => {
    mockedGetDatasets.mockResolvedValue({
      data: [],
      total: 0,
      page: 1,
      pageSize: 7,
    })

    render(<DatasetsPage />, { wrapper: createWrapper() })

    expect(screen.getByRole('button', { name: 'New dataset unavailable' })).toBeDisabled()
    expect(screen.queryByRole('link', { name: /new dataset/i })).not.toBeInTheDocument()
    expect(await screen.findByText('No datasets found')).toBeInTheDocument()
  })
})
