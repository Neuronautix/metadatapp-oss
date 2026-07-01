import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { CagesPage } from './CagesPage.tsx'
import { getCages } from './cage.api.ts'

vi.mock('./cage.api.ts', () => ({
  getCages: vi.fn(),
}))

const mockedGetCages = vi.mocked(getCages)

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

describe('CagesPage', () => {
  it('links the new action to the generic cage create page', async () => {
    mockedGetCages.mockResolvedValue({
      data: [],
      total: 0,
      page: 1,
      pageSize: 10,
    })

    render(<CagesPage />, { wrapper: createWrapper() })

    expect(screen.getByRole('link', { name: 'New cage' })).toHaveAttribute('href', '/metadata/cages/new')
    expect(await screen.findByText('No cages')).toBeInTheDocument()
  })
})
