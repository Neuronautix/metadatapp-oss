import { describe, expect, it } from 'vitest'
import { getResourceEntry } from './registry.ts'
import { getSchemaFields } from './schema.ts'

const getField = (schemaRef: string | undefined, key: string) => {
  const field = getSchemaFields(schemaRef).find((candidate) => candidate.key === key)
  expect(field).toBeDefined()
  return field
}

describe('metadatapp schema parsing', () => {
  it('builds Investigation fields from generated schemas', () => {
    const entry = getResourceEntry('projects')

    expect(entry?.label).toBe('Investigations')
    expect(entry?.listSchemaRef).toBe('Project.jsonld-project.read_read.nested_enum.read')
    expect(getField(entry?.listSchemaRef, '@id')?.readOnly).toBe(false)
    expect(getField(entry?.listSchemaRef, 'id')?.readOnly).toBe(true)
    expect(getField(entry?.createSchemaRef, 'name')?.required).toBe(true)
    expect(getField(entry?.listSchemaRef, 'laboratories')?.itemRef).toBe(
      'Laboratory.jsonld-project.read_read.nested_enum.read'
    )
  })

  it('builds Study fields from generated schemas', () => {
    const entry = getResourceEntry('experiments')

    expect(entry?.label).toBe('Studies')
    expect(entry?.listSchemaRef).toBe('Experiment.jsonld-experiment.collection_enum.read')
    expect(getField(entry?.createSchemaRef, 'name')?.required).toBe(true)
    expect(getField(entry?.createSchemaRef, 'startAt')?.required).toBe(true)
    expect(getField(entry?.createSchemaRef, 'elabftwMetadata')?.nullable).toBe(true)
    expect(getField(entry?.createSchemaRef, 'records')?.readOnly).toBe(true)
  })

  it('builds Assay fields from generated schemas', () => {
    const entry = getResourceEntry('procedures')

    expect(entry?.label).toBe('Assays')
    expect(entry?.createSchemaRef).toBe('Procedure-procedure.write')
    expect(getField(entry?.createSchemaRef, 'experiment')?.kind).toBe('iri')
    expect(getField(entry?.createSchemaRef, 'subjects')?.itemKind).toBe('iri')
    expect(getField(entry?.createSchemaRef, 'state')?.enumValues).toContain('in_progress')
  })

  it('builds subject and cage fields from generated schemas', () => {
    const subject = getResourceEntry('subjects')
    const cage = getResourceEntry('cages')

    expect(getField(subject?.createSchemaRef, 'procedures')?.itemKind).toBe('iri')
    expect(getField(subject?.createSchemaRef, 'isPregnant')?.nullable).toBe(true)
    expect(getField(cage?.createSchemaRef, 'account')?.writeOnly).toBe(true)
    expect(getField(cage?.listSchemaRef, 'type')?.kind).toBe('object')
  })

  it('builds measurement and connected app fields from generated schemas', () => {
    const measurement = getResourceEntry('weight_measurements')
    const connectedApp = getResourceEntry('connected_apps')

    expect(getField(measurement?.createSchemaRef, 'measuredAt')?.required).toBe(true)
    expect(getField(measurement?.createSchemaRef, 'account')?.writeOnly).toBe(true)
    expect(getField(connectedApp?.createSchemaRef, 'code')?.required).toBe(true)
    expect(getField(connectedApp?.listSchemaRef, 'lastSyncAt')?.format).toBe('date-time')
  })
})
