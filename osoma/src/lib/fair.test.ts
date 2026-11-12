import { describe, expect, it } from 'vitest'
import { computeFairCriteria, computeFairScore } from './fair.ts'

describe('computeFairCriteria', () => {
  it('builds a complete FAIR assessment for a study', () => {
    const criteria = computeFairCriteria({
      id: 'study-1',
      name: 'Optogenetics batch 7',
      investigationId: 'inv-1',
      investigationName: 'Neuro screen',
      assayId: 'assay-1',
      assayName: 'Patch clamp',
      status: 'completed',
      startedAt: '2026-03-01T10:00:00Z',
      completedAt: '2026-03-02T10:00:00Z',
      runCount: 3,
      sampleCount: 12,
      dataVolumeGb: 24,
      qcStatus: 'pass',
      isSigned: true,
      fairScore: { total: 0, findable: 0, accessible: 0, interoperable: 0, reusable: 0 },
      identifiers: [{ type: 'internal', value: 'study-1' }],
      provenance: [{ id: 'prov-1', at: '2026-03-02T12:00:00Z', actor: 'alice', action: 'signed' }],
      createdAt: '2026-03-01T10:00:00Z',
      updatedAt: '2026-03-02T12:00:00Z',
    })

    expect(computeFairScore(criteria)).toEqual({
      total: 100,
      findable: 100,
      accessible: 100,
      interoperable: 100,
      reusable: 100,
    })
  })

  it('builds FAIR assessments for datasets and investigations', () => {
    const datasetCriteria = computeFairCriteria({
      id: 'dataset-1',
      title: 'Sequencing bundle',
      investigationId: 'inv-1',
      investigationName: 'Neuro screen',
      format: 'FASTQ',
      sizeGb: 120,
      status: 'published',
      doi: '10.1234/example',
      fairScore: { total: 0, findable: 0, accessible: 0, interoperable: 0, reusable: 0 },
      createdAt: '2026-03-01T10:00:00Z',
      updatedAt: '2026-03-03T10:00:00Z',
    })

    const investigationCriteria = computeFairCriteria({
      id: 'inv-1',
      name: 'Neuro screen',
      description: 'Assessing optogenetics outcomes.',
      species: 'Mouse',
      startDate: '2026-03-01T10:00:00Z',
      assignedOperators: [{ id: 'user-1' }],
      fairScore: { total: 0, findable: 0, accessible: 0, interoperable: 0, reusable: 0 },
      createdAt: '2026-03-01T10:00:00Z',
      updatedAt: '2026-03-03T10:00:00Z',
      tags: ['screening'],
    })

    expect(computeFairScore(datasetCriteria).total).toBe(100)
    expect(computeFairScore(investigationCriteria).total).toBe(100)
  })

  it('keeps incomplete records below a perfect score', () => {
    const criteria = computeFairCriteria({
      id: 'dataset-2',
      title: 'Draft export',
      investigationId: '',
      investigationName: '',
      format: 'Unknown',
      sizeGb: 0,
      status: 'draft',
      fairScore: { total: 0, findable: 0, accessible: 0, interoperable: 0, reusable: 0 },
      createdAt: '2026-03-01T10:00:00Z',
      updatedAt: '2026-03-01T10:00:00Z',
    })

    expect(computeFairScore(criteria).total).toBeLessThan(100)
    expect(criteria.findable.some((criterion) => criterion.pass === false)).toBe(true)
  })
})
