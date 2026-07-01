import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Link, Route, Routes } from 'react-router-dom'
import { describe, expect, it, vi } from 'vitest'
import { Sidebar } from './Sidebar.tsx'

vi.mock('@/feature-flags/useFeatureFlag.ts', () => ({
  useFeatureFlag: (flag: string) => flag === 'metadatapp-feat.enabled',
}))

vi.mock('@/app/role-context.tsx', () => ({
  useRole: () => ({ role: 'editor' }),
}))

function SidebarHarness() {
  return (
    <MemoryRouter initialEntries={['/studies']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <div className="flex">
        <Sidebar />
        <Routes>
          <Route path="/studies" element={<Link to="/samples">Go to samples</Link>} />
          <Route path="/samples" element={<div>Samples page</div>} />
        </Routes>
      </div>
    </MemoryRouter>
  )
}

describe('Sidebar', () => {
  it('opens the section that owns the active route after navigation', async () => {
    const user = userEvent.setup()

    render(<SidebarHarness />)

    const researchSection = screen.getByRole('button', { name: 'Research & Studies' })
    const subjectsSection = screen.getByRole('button', { name: 'Subjects & Bio-Resources' })
    expect(screen.getByRole('link', { name: 'Investigations' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Projects' })).not.toBeInTheDocument()
    expect(researchSection).toHaveAttribute('aria-expanded', 'true')
    expect(subjectsSection).toHaveAttribute('aria-expanded', 'false')

    await user.click(researchSection)
    expect(researchSection).toHaveAttribute('aria-expanded', 'false')

    await user.click(screen.getByRole('link', { name: 'Go to samples' }))

    expect(await screen.findByText('Samples page')).toBeInTheDocument()
    expect(researchSection).toHaveAttribute('aria-expanded', 'false')
    expect(subjectsSection).toHaveAttribute('aria-expanded', 'true')
  })
})
