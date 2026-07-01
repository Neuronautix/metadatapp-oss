import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { DataCurationModule } from './DataCurationModule.tsx'

vi.mock('@/metadatapp/registry.ts', () => ({
  resourceRegistry: [
    {
      key: 'investigations',
      label: 'Investigations',
      collectionPath: '/investigations',
      supports: { list: true, read: true, create: false, update: false, delete: false },
    },
  ],
}))

const { getMetaCollectionMock, getMetaItemMock } = vi.hoisted(() => ({
  getMetaCollectionMock: vi.fn(),
  getMetaItemMock: vi.fn(),
}))

vi.mock('@/metadatapp/api.ts', () => ({
  getMetaCollection: getMetaCollectionMock,
  getMetaItem: getMetaItemMock,
}))

vi.mock('@/components/ui/toast.tsx', () => ({
  useToast: () => ({ toast: vi.fn(), dismiss: vi.fn(), toasts: [] }),
}))

const collectionData = {
  data: [
    {
      '@id': '/investigations/abc',
      '@type': 'Investigation',
      id: '/investigations/abc',
      name: 'Alpha Investigation',
    },
  ],
  total: 1,
  pageSize: 25,
  page: 1,
}

function renderModule(initialEntry: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter
        initialEntries={[initialEntry]}
        future={{ v7_startTransition: true, v7_relativeSplatPath: true }}
      >
        <Routes>
          <Route path="/curation/*" element={<DataCurationModule />} />
        </Routes>
      </MemoryRouter>
    </QueryClientProvider>
  )
}

describe('DataCurationModule', () => {
  beforeEach(() => {
    getMetaCollectionMock.mockReset()
    getMetaItemMock.mockReset()
    getMetaCollectionMock.mockResolvedValue(collectionData)
  })

  it('renders MappView on the nested schemas route', async () => {
    renderModule('/curation/schemas/mapp-view')

    expect(await screen.findByText('MappView')).toBeInTheDocument()
    expect(await screen.findByLabelText('Resource type')).toBeInTheDocument()
  })

  it('navigates to MappView from the schema registry entry point', async () => {
    const user = userEvent.setup()
    renderModule('/curation/schemas')

    await user.click(screen.getByRole('button', { name: /Open MappView/i }))

    await waitFor(() => {
      expect(screen.getByText('MappView')).toBeInTheDocument()
    })
    expect(screen.getByLabelText('Resource type')).toBeInTheDocument()
  })

  it('renders the workflow stepper on every route', () => {
    renderModule('/curation/import')
    expect(screen.getByRole('navigation', { name: /curation workflow steps/i })).toBeInTheDocument()
    const stepLabels = ['Upload', 'Map', 'Validate', 'Resolve', 'Review', 'Graph']
    for (const label of stepLabels) {
      expect(screen.getByRole('link', { name: label })).toBeInTheDocument()
    }
  })

  it('marks the current step as active', () => {
    renderModule('/curation/mapping')
    const current = screen.getByRole('link', { current: 'step' })
    expect(current.textContent).toContain('Map')
  })
})
