import { RouterProvider } from 'react-router-dom'
import { router } from './router.tsx'
import { AppProviders } from './providers.tsx'
import { Toaster } from '@/components/ui/toaster.tsx'
import { ErrorBoundary } from '@/components/ErrorBoundary.tsx'
import { DevModeToggle } from '@/components/dev/DevModeToggle.tsx'

export function App() {
  return (
    <AppProviders>
      <ErrorBoundary>
        <RouterProvider router={router} />
      </ErrorBoundary>
      <Toaster />
      <DevModeToggle />
    </AppProviders>
  )
}
