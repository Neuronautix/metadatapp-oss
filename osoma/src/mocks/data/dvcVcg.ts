import type { Dataset, VcgCandidate, VcgSelection } from '@/domain/dvc/dvc.types.ts'
import type { VcgMatchMode } from '@/domain/dvc/dvc.enums.ts'

const weighting = {
  strain: 0.2,
  sex: 0.2,
  ageWeeks: 0.15,
  sanitaryStatus: 0.15,
  environment: 0.1,
  bedding: 0.1,
  facility: 0.05,
  device: 0.05,
}

const scoreCandidate = (target: Dataset, candidate: Dataset, mode: VcgMatchMode) => {
  const matches = {
    strain: target.strain === candidate.strain,
    sex: target.sex === candidate.sex,
    ageWeeks: Math.abs(target.ageWeeks - candidate.ageWeeks) <= 2,
    sanitaryStatus: target.sanitaryStatus === candidate.sanitaryStatus,
    environment: target.environment === candidate.environment,
    bedding: target.beddingMaterial === candidate.beddingMaterial,
    facility: target.facility === candidate.facility,
    device: target.device === candidate.device,
  }

  const baseScore =
    (matches.strain ? weighting.strain : 0) +
    (matches.sex ? weighting.sex : 0) +
    (matches.ageWeeks ? weighting.ageWeeks : 0) +
    (matches.sanitaryStatus ? weighting.sanitaryStatus : 0) +
    (matches.environment ? weighting.environment : 0) +
    (matches.bedding ? weighting.bedding : 0) +
    (matches.facility ? weighting.facility : 0) +
    (matches.device ? weighting.device : 0)

  const adjustment = mode === 'Sensitivity Set' ? 0.08 : mode === 'Weighted Similarity' ? 0.04 : 0
  const score = Math.min(0.98, baseScore + adjustment)

  const confounders: string[] = []
  if (!matches.environment) confounders.push('Environment mismatch')
  if (!matches.bedding) confounders.push('Bedding mismatch')
  if (!matches.sanitaryStatus) confounders.push('Sanitary mismatch')
  if (target.pipelineVersion !== candidate.pipelineVersion) confounders.push('Pipeline mismatch')

  return { score, confounders }
}

export const buildVcgSelection = (datasets: Dataset[], targetDatasetId: string, mode: VcgMatchMode) => {
  const target = datasets.find((dataset) => dataset.id === targetDatasetId) ?? datasets[0]
  const candidates = datasets
    .filter((dataset) => dataset.id !== target.id)
    .map((candidate): VcgCandidate => {
      const { score, confounders } = scoreCandidate(target, candidate, mode)
      return {
        datasetId: candidate.id,
        pid: candidate.pid,
        name: candidate.name,
        similarityScore: Number((score * 100).toFixed(1)),
        matchNotes: [
          candidate.strain === target.strain ? 'Matched strain' : 'Strain variance',
          candidate.sex === target.sex ? 'Matched sex' : 'Sex variance',
          Math.abs(candidate.ageWeeks - target.ageWeeks) <= 2 ? 'Age aligned' : 'Age delta',
        ],
        confounders,
        metadata: {
          strain: candidate.strain,
          sex: candidate.sex,
          ageWeeks: candidate.ageWeeks,
          sanitaryStatus: candidate.sanitaryStatus,
          environment: candidate.environment,
          beddingBatch: candidate.beddingBatch,
          beddingMaterial: candidate.beddingMaterial,
          facility: candidate.facility,
          room: candidate.room,
          device: candidate.device,
          pipelineVersion: candidate.pipelineVersion,
        },
      }
    })
    .sort((a, b) => b.similarityScore - a.similarityScore)
    .slice(0, 4)

  return {
    id: `VCG-${target.id}`,
    targetDatasetId: target.id,
    mode,
    generatedAt: new Date().toISOString(),
    candidates,
    explanation:
      'Similarity weighted on strain, sex, age, sanitary status, environment, bedding, facility, and device. Confounders highlight metadata drift.',
    weighting,
  } satisfies VcgSelection
}
