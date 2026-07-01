import type { PulseRange, TrendDirection } from './observatory.types.ts'

export const pulseRangeConfig: Record<
  PulseRange,
  { label: string; minutes: number; bins: number; tickEvery: number }
> = {
  '24h': { label: '24h', minutes: 24 * 60, bins: 48, tickEvery: 6 },
  '7d': { label: '7d', minutes: 7 * 24 * 60, bins: 56, tickEvery: 7 },
  '30d': { label: '30d', minutes: 30 * 24 * 60, bins: 60, tickEvery: 10 },
}

export const trendClasses: Record<TrendDirection, string> = {
  up: 'text-amber-600',
  down: 'text-emerald-600',
  flat: 'text-slate-400',
}

export const metricToneClasses: Record<'ok' | 'watch' | 'risk', string> = {
  ok: 'border-emerald-100 bg-emerald-50/60 text-emerald-700',
  watch: 'border-amber-100 bg-amber-50/70 text-amber-700',
  risk: 'border-sky-100 bg-sky-50/70 text-sky-700',
}

export const laneAccentClasses: Record<string, string> = {
  ocean: 'from-sky-200/60 via-sky-100/30 to-transparent',
  amber: 'from-amber-200/60 via-amber-100/30 to-transparent',
  violet: 'from-indigo-200/60 via-indigo-100/30 to-transparent',
  slate: 'from-slate-200/60 via-slate-100/30 to-transparent',
}

export const laneDotColors: Record<string, string> = {
  ocean: 'bg-sky-500',
  amber: 'bg-amber-500',
  violet: 'bg-indigo-500',
  slate: 'bg-slate-500',
}

export const statusRingClasses: Record<'ok' | 'watch' | 'risk', string> = {
  ok: 'ring-emerald-200/80',
  watch: 'ring-amber-200/80',
  risk: 'ring-sky-200/80',
}

export const insightToneClasses: Record<'signal' | 'warning' | 'neutral', string> = {
  signal: 'border-sky-100 bg-sky-50/70 text-sky-700',
  warning: 'border-amber-100 bg-amber-50/70 text-amber-700',
  neutral: 'border-slate-200 bg-white text-slate-700',
}
