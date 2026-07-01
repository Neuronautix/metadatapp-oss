import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { MappIndexPage } from './MappIndexPage.tsx'

vi.mock('@/metadatapp/registry.ts', () => ({
  resourceRegistry: [
    {
      key: 'investigations',
      label: 'Investigations',
      collectionPath: '/investigations',
      supports: { list: true, create: true, read: true, update: true, delete: true },
    },
    {
      key: 'projects',
      label: 'Investigations',
      collectionPath: '/projects',
      supports: { list: true, create: true, read: true, update: true, delete: true },
    },
    {
      key: 'experiments',
      label: 'Studies',
      collectionPath: '/experiments',
      supports: { list: true, create: true, read: true, update: true, delete: true },
    },
    {
      key: 'procedures',
      label: 'Assays',
      collectionPath: '/procedures',
      supports: { list: true, create: true, read: true, update: true, delete: true },
    },
    {
      key: 'users',
      label: 'Users',
      collectionPath: '/users',
      supports: { list: true, create: false, read: true, update: true, delete: false },
    },
  ],
}))

const renderPage = () =>
  render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <MappIndexPage />
    </MemoryRouter>
  )

describe('MappIndexPage', () => {
  it('explains the metadata catalog purpose', () => {
    renderPage()

    expect(screen.getByRole('heading', { name: 'Metadata Catalog' })).toBeInTheDocument()
    expect(screen.getByText(/browse the metadata collections exposed by the platform/i)).toBeInTheDocument()
    expect(screen.getByText('What this workspace is for')).toBeInTheDocument()
    expect(screen.getByText('Catalog overview')).toBeInTheDocument()
    expect(screen.getAllByText('Open collection →')).toHaveLength(5)
  })

  it('filters collections by search query', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.type(screen.getByPlaceholderText('Search collections by name or API key'), 'user')

    expect(screen.getByText('Users')).toBeInTheDocument()
    expect(screen.queryByText('Investigations')).not.toBeInTheDocument()
  })

  it('shows governed labels for generic MAPP collections', () => {
    renderPage()

    expect(screen.getAllByText('Investigations').length).toBeGreaterThan(0)
    expect(screen.getByText('Studies')).toBeInTheDocument()
    expect(screen.getByText('Assays')).toBeInTheDocument()
  })
})
