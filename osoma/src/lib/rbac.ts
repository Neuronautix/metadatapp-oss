export type Role = 'VIEWER' | 'EDITOR' | 'ADMIN'
export type OrganizationRole = 'viewer' | 'editor' | 'admin'

export const ROLE_STORAGE_KEY = 'metadatapp_role'

export const roleOptions: Role[] = ['VIEWER', 'EDITOR', 'ADMIN']

export const canEdit = (role: Role) => role !== 'VIEWER'

export const isAdmin = (role: Role) => role === 'ADMIN'

export const normalizeOrganizationRole = (role: unknown): OrganizationRole | undefined => {
  if (Array.isArray(role)) {
    return role.map(normalizeOrganizationRole).find((normalized): normalized is OrganizationRole => Boolean(normalized))
  }

  if (role === 'viewer' || role === 'editor' || role === 'admin') {
    return role
  }

  return undefined
}

export const toUiRole = (organizationRole: OrganizationRole): Role => {
  switch (organizationRole) {
    case 'admin':
      return 'ADMIN'
    case 'editor':
      return 'EDITOR'
    case 'viewer':
    default:
      return 'VIEWER'
  }
}

export const getOrganizationRights = (organizationRole: OrganizationRole) => ({
  canRead: true,
  canWrite: organizationRole !== 'viewer',
  canAdmin: organizationRole === 'admin',
})
