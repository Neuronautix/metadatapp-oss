import { describe, it } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

import { checkOntologyDepth } from '../src/validators/ontologyDepth.ts'

const here = dirname(fileURLToPath(import.meta.url))
const fixturesDir = resolve(here, '../src/fixtures')

async function loadFixture(name: string): Promise<unknown> {
  const buf = await readFile(resolve(fixturesDir, name), 'utf8')
  return JSON.parse(buf)
}

describe('checkOntologyDepth', () => {
  it('passes every check on the example fixture', async () => {
    const doc = await loadFixture('fair2-experiment.example.jsonld')
    const result = checkOntologyDepth(doc)
    const failed = result.verdicts.filter((v) => !v.passed)
    assert.equal(failed.length, 0, `expected 0 failed checks, got ${JSON.stringify(failed)}`)
  })

  it('fails procedure typing and unit checks on the broken fixture', async () => {
    const doc = await loadFixture('fair2-experiment.broken.jsonld')
    const result = checkOntologyDepth(doc)

    const procedure = result.verdicts.find((v) => v.check === 'procedure_typing')
    assert.ok(procedure, 'procedure_typing verdict must be present')
    assert.equal(procedure!.passed, false, 'procedure typing should fail (sc:Thing fallback)')

    const units = result.verdicts.find((v) => v.check === 'qudt_units')
    assert.ok(units, 'qudt_units verdict must be present')
    assert.equal(units!.passed, false, 'units should fail (no qudt:hasUnit)')

    const genotype = result.verdicts.find((v) => v.check === 'genotype_iri')
    assert.ok(genotype, 'genotype_iri verdict must be present')
    assert.equal(genotype!.passed, false, 'genotype should fail (free string)')
  })
})
