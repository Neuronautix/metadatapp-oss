import React from 'react'
import type { Role } from '@/lib/rbac.ts'
import { ROLE_STORAGE_KEY, roleOptions, toUiRole } from '@/lib/rbac.ts'
import { useAuth } from '@/app/auth-context.tsx'

const DEFAULT_ROLE: Role = 'EDITOR'

type RoleContextValue = {
  role: Role
  setRole: (role: Role) => void
  isDerivedFromAuth: boolean
}

const RoleContext = React.createContext<RoleContextValue | undefined>(undefined)

const isRole = (value: string): value is Role => roleOptions.includes(value as Role)

const getInitialRole = (): Role => {
  if (typeof window === 'undefined') {
    return DEFAULT_ROLE
  }

  const storedRole = window.localStorage.getItem(ROLE_STORAGE_KEY)
  return storedRole && isRole(storedRole) ? storedRole : DEFAULT_ROLE
}

export function RoleProvider({ children }: { children: React.ReactNode }) {
  const { session } = useAuth()
  const [localRole, setLocalRole] = React.useState<Role>(getInitialRole)
  const organizationRole = session?.user?.organizationRole
  const isDerivedFromAuth = Boolean(organizationRole)
  const role = organizationRole ? toUiRole(organizationRole) : localRole

  const setRole = React.useCallback(
    (nextRole: Role) => {
      if (isDerivedFromAuth) {
        return
      }

      setLocalRole(nextRole)

      if (typeof window !== 'undefined') {
        window.localStorage.setItem(ROLE_STORAGE_KEY, nextRole)
      }
    },
    [isDerivedFromAuth],
  )

  return <RoleContext.Provider value={{ role, setRole, isDerivedFromAuth }}>{children}</RoleContext.Provider>
}

export function useRole() {
  const context = React.useContext(RoleContext)
  if (!context) {
    throw new Error('useRole must be used within RoleProvider')
  }
  return context
}
