import { describe, expect, it } from 'vitest'
import { getOrganizationRights, normalizeOrganizationRole, toUiRole } from './rbac.ts'

describe('rbac organization role helpers', () => {
  it('normalizes supported organization roles', () => {
    expect(normalizeOrganizationRole('viewer')).toBe('viewer')
    expect(normalizeOrganizationRole('editor')).toBe('editor')
    expect(normalizeOrganizationRole('admin')).toBe('admin')
    expect(normalizeOrganizationRole(['admin'])).toBe('admin')
    expect(normalizeOrganizationRole(['owner', 'editor'])).toBe('editor')
    expect(normalizeOrganizationRole('ADMIN')).toBeUndefined()
    expect(normalizeOrganizationRole('')).toBeUndefined()
    expect(normalizeOrganizationRole('owner')).toBeUndefined()
    expect(normalizeOrganizationRole(null)).toBeUndefined()
    expect(normalizeOrganizationRole(undefined)).toBeUndefined()
  })

  it('maps organization roles to UI roles', () => {
    expect(toUiRole('viewer')).toBe('VIEWER')
    expect(toUiRole('editor')).toBe('EDITOR')
    expect(toUiRole('admin')).toBe('ADMIN')
  })

  it('derives organization rights', () => {
    expect(getOrganizationRights('viewer')).toEqual({
      canRead: true,
      canWrite: false,
      canAdmin: false,
    })
    expect(getOrganizationRights('editor')).toEqual({
      canRead: true,
      canWrite: true,
      canAdmin: false,
    })
    expect(getOrganizationRights('admin')).toEqual({
      canRead: true,
      canWrite: true,
      canAdmin: true,
    })
  })
})
