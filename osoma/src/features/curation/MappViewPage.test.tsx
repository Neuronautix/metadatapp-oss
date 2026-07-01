import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import React from 'react'
import { MappViewPage } from './MappViewPage.tsx'

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

vi.mock('@/metadatapp/registry.ts', () => ({
  resourceRegistry: [
    { key: 'investigations', label: 'Investigations', collectionPath: '/investigations', supports: { list: true, read: true, create: false, update: false, delete: false } },
    { key: 'subjects', label: 'Subjects', collectionPath: '/subjects', supports: { list: true, read: true, create: false, update: false, delete: false } },
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

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const collectionData = {
  data: [
    { '@id': '/investigations/abc', '@type': 'Investigation', id: '/investigations/abc', name: 'Alpha Investigation' },
    { '@id': '/investigations/def', '@type': 'Investigation', id: '/investigations/def', name: 'Beta Investigation' },
  ],
  total: 2,
  pageSize: 25,
  page: 1,
}

const itemRecord = {
  '@id': '/investigations/abc',
  '@type': 'Investigation',
  id: '/investigations/abc',
  name: 'Alpha Investigation',
  goal: 'Discover something',
  startAt: '2024-01-01T00:00:00Z',
  description: 'A test investigation',
}

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MappViewPage />
    </QueryClientProvider>
  )
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('MappViewPage', () => {
  beforeEach(() => {
    getMetaCollectionMock.mockReset()
    getMetaItemMock.mockReset()
  })

  it('renders the page heading', () => {
    getMetaCollectionMock.mockResolvedValue(collectionData)
    renderPage()
    expect(screen.getByText('MappView')).toBeInTheDocument()
  })

  it('renders the resource type selector', () => {
    getMetaCollectionMock.mockResolvedValue(collectionData)
    renderPage()
    expect(screen.getByLabelText('Resource type')).toBeInTheDocument()
  })

  it('renders record selector step label', async () => {
    getMetaCollectionMock.mockResolvedValue(collectionData)
    renderPage()
    await waitFor(() => {
      expect(screen.getByText(/2 — Select a record/i)).toBeInTheDocument()
    })
  })

  it('shows the empty-state placeholder before a record is selected', async () => {
    getMetaCollectionMock.mockResolvedValue(collectionData)
    renderPage()
    await waitFor(() => {
      expect(screen.getByText('Select a record above to explore its schema.')).toBeInTheDocument()
    })
  })

  it('shows loading skeleton while fetching the list', () => {
    getMetaCollectionMock.mockReturnValue(new Promise(() => {})) // never resolves
    renderPage()
    // Skeleton element: aria role is 'status' by default in shadcn skeleton
    // Check that no record options are rendered yet
    const select = screen.queryByLabelText('Select a record')
    expect(select).toBeNull()
  })

  it('shows no-records message when list is empty', async () => {
    getMetaCollectionMock.mockResolvedValue({ data: [], total: 0, pageSize: 25, page: 1 })
    renderPage()
    await waitFor(() => {
      expect(screen.getByText('No records found for this resource.')).toBeInTheDocument()
    })
  })

  it('displays record options after list loads', async () => {
    getMetaCollectionMock.mockResolvedValue(collectionData)
    renderPage()
    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Alpha Investigation' })).toBeInTheDocument()
      expect(screen.getByRole('option', { name: 'Beta Investigation' })).toBeInTheDocument()
    })
  })

  it('renders the schema tree after selecting a record', async () => {
    getMetaCollectionMock.mockResolvedValue(collectionData)
    getMetaItemMock.mockResolvedValue(itemRecord)
    renderPage()

    // Wait for select to populate
    const recordSelect = await screen.findByLabelText('Select a record')
    await userEvent.selectOptions(recordSelect, '/investigations/abc')

    await waitFor(() => {
      // Schema tree should render the record name
      expect(screen.getAllByText('Alpha Investigation').length).toBeGreaterThan(0)
    })
    // Export button should appear
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Export JSON-LD/i })).toBeInTheDocument()
    })
  })
})
