import { useEffect } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { Sidebar } from './Sidebar.tsx'
import { Topbar } from './Topbar.tsx'

export function AppLayout() {
  const { pathname } = useLocation()

  useEffect(() => {
    window.scrollTo({ top: 0, behavior: 'instant' })
  }, [pathname])

  return (
    <div className="min-h-screen">
      <div className="relative">
        <div className="pointer-events-none absolute inset-0">
          <div
            className="absolute left-[8%] top-8 h-40 w-40 rounded-full bg-accent/25 blur-3xl"
            style={{ opacity: 'var(--ambient-blob)' }}
          />
          <div
            className="absolute right-[10%] top-24 h-52 w-52 rounded-full bg-info/20 blur-3xl"
            style={{ opacity: 'var(--ambient-blob)' }}
          />
        </div>
        <div className="relative flex min-h-screen">
          <Sidebar />
          <div className="flex min-h-screen flex-1 flex-col">
            <Topbar />
            <main className="flex-1 px-[var(--page-padding)] py-[var(--page-padding-y)] lg:px-[var(--page-padding-lg)]">
              <Outlet />
            </main>
          </div>
        </div>
      </div>
    </div>
  )
}
