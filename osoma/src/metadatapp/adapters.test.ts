import { describe, expect, it } from 'vitest'
import { mapMetaDataset, mapMetaInvestigation, mapMetaStudy } from './adapters.ts'

describe('metadatapp adapters', () => {
  it('computes FAIR scores for mapped investigations, studies, and datasets', () => {
    const investigation = mapMetaInvestigation({
      id: 'inv-1',
      name: 'Neuro screen',
      description: 'Assessing optogenetics outcomes.',
      startAt: '2026-03-01T10:00:00Z',
      user: '/users/user-1',
    })

    const study = mapMetaStudy({
      id: 'study-1',
      name: 'Patch clamp run',
      investigation: '/experiments/inv-1',
      assay: '/api/nam/datasets/assay-1',
      startAt: '2026-03-01T10:00:00Z',
      endAt: '2026-03-02T10:00:00Z',
      assays: ['/api/nam/datasets/assay-1'],
      records: ['/records/1'],
    })

    const dataset = mapMetaDataset({
      id: 'dataset-1',
      title: 'Sequencing bundle',
      investigation: '/experiments/inv-1',
      format: 'FASTQ',
      sizeGb: 120,
      status: 'published',
      doi: '10.1234/example',
      createdAt: '2026-03-01T10:00:00Z',
      updatedAt: '2026-03-03T10:00:00Z',
    })

    expect(investigation.fairScore.total).toBeGreaterThan(0)
    expect(study.fairScore.total).toBeGreaterThan(0)
    expect(dataset.fairScore.total).toBeGreaterThan(0)
  })
})
