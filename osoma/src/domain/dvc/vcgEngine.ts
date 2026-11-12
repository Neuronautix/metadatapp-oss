import { computeSimilarityScore, type AnimalRecord, type FilterCriteria, filterAnimals } from './metadataMatcher.ts'
import { buildSinusoidCurve, computeCircadianStats, meanCircadianParams, type CircadianModelParams, type SinusoidPoint } from './circadianModel.ts'

export type VcgMatch = {
  animal: AnimalRecord
  similarityScore: number
}

export type VcgGroup = {
  targetAnimals: AnimalRecord[]
  matches: VcgMatch[]
  meanParams: CircadianModelParams
  vcgCurve: SinusoidPoint[]
  stats: ReturnType<typeof computeCircadianStats>
}

const MAX_VCG_MATCHES = 50

/** Find VCG candidates for a set of target animals from a pool, above a similarity threshold */
export const buildVcgGroup = (
  targets: AnimalRecord[],
  pool: AnimalRecord[],
  minSimilarity = 70
): VcgGroup => {
  const targetIds = new Set(targets.map((a) => a.animalId))

  // For each target, find candidates from the pool that are not targets themselves
  const allMatches: VcgMatch[] = []
  const seen = new Set<string>()

  for (const target of targets) {
    for (const candidate of pool) {
      if (targetIds.has(candidate.animalId)) continue
      if (seen.has(candidate.animalId)) continue
      const score = computeSimilarityScore(target, candidate)
      if (score >= minSimilarity) {
        allMatches.push({ animal: candidate, similarityScore: score })
        seen.add(candidate.animalId)
      }
    }
  }

  allMatches.sort((a, b) => b.similarityScore - a.similarityScore)
  const top = allMatches.slice(0, Math.min(MAX_VCG_MATCHES, allMatches.length))

  const vcgParams = top.map((m) => m.animal.circadianParameters)
  const effectiveParams = vcgParams.length ? vcgParams : targets.map((t) => t.circadianParameters)
  const meanParams = meanCircadianParams(effectiveParams)
  const vcgCurve = buildSinusoidCurve(meanParams)
  const stats = computeCircadianStats(effectiveParams)

  return {
    targetAnimals: targets,
    matches: top,
    meanParams,
    vcgCurve,
    stats,
  }
}

/** Compute VCG for filtered animals vs all others in the pool */
export const computeVcgForFilter = (
  allAnimals: AnimalRecord[],
  criteria: FilterCriteria,
  minSimilarity = 70
): VcgGroup => {
  const targets = filterAnimals(allAnimals, criteria)
  const pool = allAnimals
  return buildVcgGroup(targets, pool, minSimilarity)
}
