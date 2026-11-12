export type CircadianModelParams = {
  amplitude: number
  phase: number
  baseline: number
  noise: number
}

export type SinusoidPoint = {
  hour: number
  value: number
  lower: number
  upper: number
}

/** Compute the deterministic sinusoid value at a given hour (0–23) */
export const computeSinusoidValue = (hour: number, params: CircadianModelParams): number => {
  return params.baseline + params.amplitude * Math.sin((2 * Math.PI * hour) / 24 + params.phase)
}

/** Generate 24 hourly points for the sinusoid model with noise envelope */
export const buildSinusoidCurve = (params: CircadianModelParams): SinusoidPoint[] => {
  return Array.from({ length: 24 }, (_, hour) => {
    const value = computeSinusoidValue(hour, params)
    return {
      hour,
      value,
      lower: value - params.noise,
      upper: value + params.noise,
    }
  })
}

/** Compute mean parameters from a list of animals */
export const meanCircadianParams = (paramsList: CircadianModelParams[]): CircadianModelParams => {
  if (!paramsList.length) return { amplitude: 0, phase: 0, baseline: 0, noise: 0 }
  const n = paramsList.length
  return {
    amplitude: paramsList.reduce((s, p) => s + p.amplitude, 0) / n,
    phase: paramsList.reduce((s, p) => s + p.phase, 0) / n,
    baseline: paramsList.reduce((s, p) => s + p.baseline, 0) / n,
    noise: paramsList.reduce((s, p) => s + p.noise, 0) / n,
  }
}

/** Statistics for a set of circadian parameter sets */
export type CircadianStats = {
  count: number
  meanAmplitude: number
  stdAmplitude: number
  meanPhase: number
  stdPhase: number
  meanBaseline: number
}

export const computeCircadianStats = (paramsList: CircadianModelParams[]): CircadianStats => {
  if (!paramsList.length) {
    return { count: 0, meanAmplitude: 0, stdAmplitude: 0, meanPhase: 0, stdPhase: 0, meanBaseline: 0 }
  }
  const n = paramsList.length
  const meanAmp = paramsList.reduce((s, p) => s + p.amplitude, 0) / n
  const meanPh = paramsList.reduce((s, p) => s + p.phase, 0) / n
  const meanBase = paramsList.reduce((s, p) => s + p.baseline, 0) / n
  const stdAmp = Math.sqrt(paramsList.reduce((s, p) => s + (p.amplitude - meanAmp) ** 2, 0) / n)
  const stdPh = Math.sqrt(paramsList.reduce((s, p) => s + (p.phase - meanPh) ** 2, 0) / n)
  return { count: n, meanAmplitude: meanAmp, stdAmplitude: stdAmp, meanPhase: meanPh, stdPhase: stdPh, meanBaseline: meanBase }
}
