import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

import { validateContextUsage } from '../src/validators/contextUsage.ts'

const here = dirname(fileURLToPath(import.meta.url))
const fixturesDir = resolve(here, '../src/fixtures')

async function loadFixture(name: string): Promise<unknown> {
  const buf = await readFile(resolve(fixturesDir, name), 'utf8')
  return JSON.parse(buf)
}

describe('validateContextUsage', () => {
  it('reports full coverage on the example fixture', async () => {
    const doc = await loadFixture('fair2-experiment.example.jsonld')
    const result = validateContextUsage(doc)
    assert.equal(result.summary.coverageRatio, 1, 'coverage should be 1 when every declared vocab is used')
    assert.deepEqual(result.summary.unusedVocabs, [])
    assert.equal(
      result.issues.filter((i) => i.level === 'error').length,
      0,
      'no errors expected on the example fixture',
    )
    assert.equal(result.valid, true)
  })

  it('flags unused scientific vocabularies on the broken fixture', async () => {
    const doc = await loadFixture('fair2-experiment.broken.jsonld')
    const result = validateContextUsage(doc)
    assert.ok(
      result.summary.unusedVocabs.length > 0,
      'broken fixture should have at least one unused vocab',
    )
    assert.ok(
      result.summary.coverageRatio < 1,
      'coverage ratio should be below 1 on the broken fixture',
    )
    // The broken fixture declares qudt/unit/obi/ncbitaxon/mgi/pato/prov but
    // never uses them. At least one of these should fire FAIR_011 (sci theatre).
    const sciIssues = result.issues.filter((i) => i.code === 'FAIR_011')
    assert.ok(
      sciIssues.length > 0,
      `expected FAIR_011 issues on broken fixture, got ${JSON.stringify(result.issues)}`,
    )
    assert.equal(result.valid, false)
  })

  it('handles a malformed input gracefully', () => {
    const result = validateContextUsage(42)
    assert.equal(result.valid, false)
    assert.equal(result.issues[0]?.code, 'FAIR_001')
  })
})
