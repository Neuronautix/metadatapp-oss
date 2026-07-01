import { describe, it } from 'node:test'
import assert from 'node:assert/strict'

import { validateCedarSchemaCompatibility } from '../src/validators/cedarSchemaCompatibility.ts'

describe('validateCedarSchemaCompatibility', () => {
  it('builds a CEDAR template skeleton from compact JSON-LD', () => {
    const doc = {
      '@context': { '@vocab': 'https://schema.org/' },
      '@type': 'Dataset',
      name: 'Study Alpha',
      description: 'Pilot metadata export',
      startAt: '2026-05-10',
      repositoryLink: 'https://example.org/repo',
      measurements: {
        value: 42,
        unit: 'g',
      },
    }

    const result = validateCedarSchemaCompatibility(doc)

    assert.equal(result.valid, true)
    assert.ok(result.cedarTemplate)
    assert.equal(result.cedarTemplate?.type, 'template')
    assert.equal(result.cedarTemplate?.name, 'Study Alpha')

    const keys = (result.cedarTemplate?.children ?? []).map((c) => c.key)
    assert.ok(keys.includes('description'))
    assert.ok(keys.includes('startAt'))
    assert.ok(keys.includes('repositoryLink'))
    assert.ok(keys.includes('measurements'))
  })

  it('supports @graph payloads', () => {
    const result = validateCedarSchemaCompatibility({
      '@graph': [
        {
          'schema:name': 'Graph-based study',
          protocol: 'open-field',
        },
        {
          repositoryLink: 'https://example.org/repo',
        },
      ],
    })

    assert.equal(result.valid, true)
    assert.equal(result.summary.metadatappFieldCount, 3)
    assert.equal(result.cedarTemplate?.name, 'Graph-based study')
    assert.equal(
      (result.cedarTemplate?.children ?? []).some((child) => child.key === 'repositoryLink'),
      true,
    )
  })

  it('reports partial top-level coverage for nested elements', () => {
    const result = validateCedarSchemaCompatibility({
      name: 'Nested study',
      measurements: {
        value: 42,
      },
    })

    assert.equal(result.summary.metadatappFieldCount, 2)
    assert.equal(result.summary.mappedFieldCount, 1)
    assert.equal(result.summary.coverageRatio, 0.5)
    assert.equal(result.issues.some((issue) => issue.code === 'CEDAR_003' && issue.path === '$'), true)
  })

  it('uses @graph warning paths for empty graph inputs', () => {
    const result = validateCedarSchemaCompatibility({
      '@graph': [{}],
    })

    assert.equal(result.summary.metadatappFieldCount, 0)
    assert.equal(result.summary.coverageRatio, 0)
    assert.equal(result.issues.some((issue) => issue.code === 'CEDAR_002' && issue.path === '@graph'), true)
  })

  it('returns an error for invalid input', () => {
    const result = validateCedarSchemaCompatibility(null)

    assert.equal(result.valid, false)
    assert.equal(result.issues[0]?.code, 'CEDAR_001')
  })
})
