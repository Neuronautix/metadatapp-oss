import { describe, expect, it } from 'vitest'
import { getDefaultAccessAreas, normalizeAccessAreas, summarizeAccessAreas } from './user-access.ts'

describe('user access helpers', () => {
  it('provides broad defaults for admin profiles', () => {
    expect(getDefaultAccessAreas('admin')).toContain('administration')
    expect(getDefaultAccessAreas('admin')).toContain('operations')
  })

  it('normalizes duplicates and falls back to access-level defaults', () => {
    expect(normalizeAccessAreas(['subjects', 'subjects'], 'viewer')).toEqual(['subjects'])
    expect(normalizeAccessAreas(undefined, 'editor')).toEqual(getDefaultAccessAreas('editor'))
  })

  it('summarizes full and partial workspace access', () => {
    expect(summarizeAccessAreas(getDefaultAccessAreas('admin'), 'admin')).toBe('Full workspace')
    expect(summarizeAccessAreas(['subjects', 'metadata'], 'editor')).toBe('Subjects, Metadata admin')
  })
})
