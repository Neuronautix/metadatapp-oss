import type { Animal } from './animal.types.ts'

export const statusBadgeMap: Record<
  NonNullable<Animal['badgeStatus']>,
  { label: string; variant: 'success' | 'warning' | 'critical' }
> = {
  ok: { label: 'OK', variant: 'success' },
  warning: { label: 'WARNING', variant: 'warning' },
  critical: { label: 'CRITICAL', variant: 'critical' },
}

export const cageColorClasses: Record<NonNullable<Animal['cageColor']>, string> = {
  emerald: 'bg-emerald-500',
  sky: 'bg-sky-500',
  amber: 'bg-amber-500',
  rose: 'bg-rose-500',
  violet: 'bg-violet-500',
  slate: 'bg-slate-500',
}

export const getCageColorClass = (color?: Animal['cageColor']) =>
  cageColorClasses[color ?? 'slate']
