import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { MappResourceListPage } from './MappResourceListPage.tsx'
import { getMetaCollectionByRef } from '@/metadatapp/api.ts'

vi.mock('@/metadatapp/api.ts', () => ({
  getMetaCollectionByRef: vi.fn(),
}))

const mockedGetMetaCollectionByRef = vi.mocked(getMetaCollectionByRef)

const createWrapper = (initialPath: string) => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  })

  return ({ children }: { children: ReactNode }) => (
    <MemoryRouter initialEntries={[initialPath]} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <QueryClientProvider client={queryClient}>
        <Routes>
          <Route path="/metadata/:resource" element={children} />
        </Routes>
      </QueryClientProvider>
    </MemoryRouter>
  )
}

describe('MappResourceListPage', () => {
  it('shows the ElabFTW sync badge on the active study list route', async () => {
    mockedGetMetaCollectionByRef.mockResolvedValueOnce({
      data: [
        {
          id: 'EXP-401',
          name: 'Synced study',
          startAt: '2026-04-01T10:00:00Z',
          endAt: null,
          elaftwExternalId: 'elab-exp-401',
        },
      ],
      total: 1,
      page: 1,
      pageSize: 10,
    })

    render(<MappResourceListPage />, { wrapper: createWrapper('/metadata/experiments') })

    expect(await screen.findByText('Synced study')).toBeInTheDocument()
    expect(screen.getByText('Synced to ElabFTW')).toBeInTheDocument()
  })

  it('shows the ElabFTW sync badge on the active assay list route', async () => {
    mockedGetMetaCollectionByRef.mockResolvedValueOnce({
      data: [
        {
          id: 'PRO-112',
          name: 'Synced assay',
          startAt: '2026-04-01T10:00:00Z',
          endAt: null,
          elaftwExternalId: 'elab-pro-112',
        },
      ],
      total: 1,
      page: 1,
      pageSize: 10,
    })

    render(<MappResourceListPage />, { wrapper: createWrapper('/metadata/procedures') })

    expect(await screen.findByRole('heading', { name: 'Assays' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'New Assay' })).toHaveAttribute('href', '/metadata/procedures/new')
    expect(await screen.findByText('Synced assay')).toBeInTheDocument()
    expect(screen.getByText('Synced to ElabFTW')).toBeInTheDocument()
  })

  it('adds a readable primary column when the configured list fields do not include a name', async () => {
    mockedGetMetaCollectionByRef.mockResolvedValueOnce({
      data: [
        {
          id: 'cage-1',
          name: 'Primary cage',
          type: 'IVC',
          format: 'Type II long',
          beddingType: 'Corn cob',
          animalFacility: 'Facility A',
        },
      ],
      total: 1,
      page: 1,
      pageSize: 10,
    })

    render(<MappResourceListPage />, { wrapper: createWrapper('/metadata/cages') })

    expect(await screen.findByRole('columnheader', { name: 'Name' })).toBeInTheDocument()
    expect(screen.getByText('Primary cage')).toBeInTheDocument()
    expect(screen.getByText((_, element) => element?.textContent === '5 visible columns')).toBeInTheDocument()
    expect(screen.getByRole('columnheader', { name: 'Bedding Type' })).toBeInTheDocument()
  })
})
