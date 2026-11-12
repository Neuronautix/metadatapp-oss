import React from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ToastProvider } from '@/components/ui/toast.tsx'
import { RoleProvider } from './role-context.tsx'
import { ThemeProvider } from '@/theme/useTheme.ts'
import { FeatureFlagProvider } from '@/feature-flags/FeatureFlagProvider.tsx'
import { AuthProvider } from './auth-context.tsx'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 10_000,
      retry: 1,
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: 0,
    },
  },
})

export function AppProviders({ children }: { children: React.ReactNode }) {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <RoleProvider>
          <FeatureFlagProvider>
            <ThemeProvider>
              <ToastProvider>{children}</ToastProvider>
            </ThemeProvider>
          </FeatureFlagProvider>
        </RoleProvider>
      </AuthProvider>
    </QueryClientProvider>
  )
}
