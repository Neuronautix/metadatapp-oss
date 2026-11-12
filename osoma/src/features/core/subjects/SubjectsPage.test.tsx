import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { SubjectsPage } from './SubjectsPage.tsx'
import { getSubjects } from './subject.api.ts'

vi.mock('./subject.api.ts', () => ({
  getSubjects: vi.fn(),
}))

const mockedGetSubjects = vi.mocked(getSubjects)

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

describe('SubjectsPage', () => {
  it('links the new action to the generic subject create page', async () => {
    mockedGetSubjects.mockResolvedValue({
      data: [],
      total: 0,
      page: 1,
      pageSize: 10,
    })

    render(<SubjectsPage />, { wrapper: createWrapper() })

    expect(screen.getByRole('link', { name: 'New subject' })).toHaveAttribute('href', '/metadata/subjects/new')
    expect(await screen.findByText('No subjects found')).toBeInTheDocument()
  })
})
