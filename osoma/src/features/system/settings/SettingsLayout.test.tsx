import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { SettingsLayout } from './SettingsLayout.tsx'

const useRoleMock = vi.fn()

vi.mock('@/app/role-context.tsx', () => ({
  useRole: () => useRoleMock(),
}))

function renderLayout() {
  return render(
    <MemoryRouter initialEntries={['/settings/profile']} future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <Routes>
        <Route path="/settings" element={<SettingsLayout />}>
          <Route path="profile" element={<div>Profile page</div>} />
        </Route>
      </Routes>
    </MemoryRouter>,
  )
}

describe('SettingsLayout', () => {
  beforeEach(() => {
    useRoleMock.mockReset()
  })

  it('hides admin-only settings links for non-admin users', () => {
    useRoleMock.mockReturnValue({ role: 'EDITOR' })

    renderLayout()

    expect(screen.queryByRole('link', { name: 'API Keys' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'AI Providers' })).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Profile' })).toBeInTheDocument()
  })

  it('shows admin-only settings links for admins', () => {
    useRoleMock.mockReturnValue({ role: 'ADMIN' })

    renderLayout()

    expect(screen.getByRole('link', { name: 'API Keys' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'AI Providers' })).toBeInTheDocument()
  })
})
