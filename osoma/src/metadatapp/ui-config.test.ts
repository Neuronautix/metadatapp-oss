import { describe, expect, it } from 'vitest'
import { getResourceEntry } from './registry.ts'
import { getFormFieldsForEntry, getResourceDisplayLabel } from './ui-config.ts'

describe('getFormFieldsForEntry', () => {
  it('keeps the configured ElabFTW study sync field even when missing from generated schema helpers', () => {
    const entry = getResourceEntry('experiments')

    expect(entry).toBeDefined()

    const fields = getFormFieldsForEntry(entry, entry?.updateSchemaRef ?? entry?.itemSchemaRef)
    const elaftwField = fields.find((field) => field.key === 'elaftwExternalId')

    expect(elaftwField).toBeDefined()
    expect(elaftwField?.label).toBe('ElabFTW Sync ID')
    expect(elaftwField?.readOnly).toBe(true)
  })

  it('does not create editable fallback fields that are absent from the write schema', () => {
    const entry = getResourceEntry('experiments')

    expect(entry).toBeDefined()

    const fields = getFormFieldsForEntry(entry, entry?.createSchemaRef)

    expect(fields.find((field) => field.key === 'assays')).toBeUndefined()
    expect(fields.find((field) => field.key === 'elaftwExternalId')?.readOnly).toBe(true)
  })

  it('maps generic MAPP resource labels to governed terminology', () => {
    expect(getResourceDisplayLabel(getResourceEntry('projects'))).toBe('Investigations')
    expect(getResourceDisplayLabel(getResourceEntry('experiments'))).toBe('Studies')
    expect(getResourceDisplayLabel(getResourceEntry('procedures'))).toBe('Assays')
    expect(getResourceDisplayLabel(getResourceEntry('projects'), 'singular')).toBe('Investigation')
    expect(getResourceDisplayLabel(getResourceEntry('experiments'), 'singular')).toBe('Study')
    expect(getResourceDisplayLabel(getResourceEntry('procedures'), 'singular')).toBe('Assay')
  })

  it('maps governed field labels without changing backend field keys', () => {
    const investigationEntry = getResourceEntry('projects')
    const studyEntry = getResourceEntry('experiments')
    const assayEntry = getResourceEntry('procedures')

    const investigationFields = getFormFieldsForEntry(
      investigationEntry,
      investigationEntry?.updateSchemaRef ?? investigationEntry?.itemSchemaRef
    )
    const studyFields = getFormFieldsForEntry(studyEntry, studyEntry?.updateSchemaRef ?? studyEntry?.itemSchemaRef)
    const assayFields = getFormFieldsForEntry(assayEntry, assayEntry?.updateSchemaRef ?? assayEntry?.itemSchemaRef)

    expect(investigationFields.find((field) => field.key === 'name')?.label).toBe('Investigation Name')
    expect(studyFields.find((field) => field.key === 'name')?.label).toBe('Study Name')
    expect(assayFields.find((field) => field.key === 'name')?.label).toBe('Assay Name')
    expect(assayFields.find((field) => field.key === 'experiment')?.label).toBe('Study')
  })
})
