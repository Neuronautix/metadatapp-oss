import { describe, expect, it } from 'vitest'
import { getReadableIriLabel, getReadableResourceId, getReadableResourceLabel, getRouteResourceId } from './display.ts'

describe('metadatapp display helpers', () => {
  it('prefers embedded readable labels over IRIs', () => {
    expect(
      getReadableResourceLabel({
        '@id': '/laboratories/7b3c',
        name: 'Neuro Imaging Lab',
      })
    ).toBe('Neuro Imaging Lab')
  })

  it('formats raw IRIs into a readable resource label', () => {
    expect(getReadableIriLabel('/animal_facilities/fac-12')).toBe('Animal Facilities · fac-12')
    expect(getReadableResourceLabel('/animal_facilities/fac-12')).toBe('Animal Facilities · fac-12')
  })

  it('extracts a compact readable identifier from IRI-like values', () => {
    expect(getReadableResourceId('/subjects/SUB-9')).toBe('SUB-9')
    expect(getReadableResourceId({ '@id': '/accounts/tenant-a' })).toBe('tenant-a')
  })

  it('builds route-safe ids for metadata detail paths', () => {
    expect(getRouteResourceId('/subjects/SUB-9')).toBe('SUB-9')
    expect(getRouteResourceId('/subjects/A%2FB')).toBe('A%2FB')
    expect(getRouteResourceId('alpha/beta')).toBe('alpha%2Fbeta')
  })

  it('keeps ordinary URLs intact', () => {
    expect(getReadableResourceLabel('https://demo.elabftw.com')).toBe('https://demo.elabftw.com')
  })

  it('renders arrays as readable joined labels', () => {
    expect(
      getReadableResourceLabel([
        { name: 'Subject A', '@id': '/subjects/A' },
        '/subjects/B',
      ])
    ).toBe('Subject A, Subjects · B')
  })

  it('does not throw on malformed percent-encoded ids', () => {
    expect(getReadableResourceId('/subjects/bad%value')).toBe('bad%value')
    expect(getReadableIriLabel('/animal_facilities/bad%value')).toBe('Animal Facilities · bad%value')
  })
})
