import { useMemo } from 'react'
import {
  Area,
  AreaChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'
import type { CircadianModelParams } from '@/domain/dvc/circadianModel.ts'
import { buildSinusoidCurve } from '@/domain/dvc/circadianModel.ts'

type Props = {
  params: CircadianModelParams[]
  labels?: string[]
  showIndividual?: boolean
  height?: number
}

const COLORS = ['#6366f1', '#22c55e', '#f59e0b', '#ec4899', '#14b8a6', '#f97316', '#8b5cf6', '#06b6d4']

export function CircadianChart({ params, labels, showIndividual = true, height = 280 }: Props) {
  const curves = params.map((p) => buildSinusoidCurve(p))

  // Build merged data: [{hour, animal_0, animal_1, ...}]
  const data = Array.from({ length: 24 }, (_, hour) => {
    const row: Record<string, number> = { hour }
    curves.forEach((curve, i) => {
      row[`animal_${i}`] = Math.round(curve[hour].value * 10) / 10
    })
    return row
  })

  // Pre-compute label lookup to avoid string parsing on every render
  const labelByKey = useMemo(() => {
    const map = new Map<string, string>()
    params.forEach((_, i) => {
      map.set(`animal_${i}`, labels?.[i] ?? `Animal ${i + 1}`)
    })
    return map
  }, [params, labels])

  return (
    <ResponsiveContainer width="100%" height={height}>
      <AreaChart data={data} margin={{ top: 4, right: 12, bottom: 4, left: 0 }}>
        <defs>
          {params.map((_, i) => (
            <linearGradient key={i} id={`grad_${i}`} x1="0" y1="0" x2="0" y2="1">
              <stop offset="5%" stopColor={COLORS[i % COLORS.length]} stopOpacity={0.3} />
              <stop offset="95%" stopColor={COLORS[i % COLORS.length]} stopOpacity={0.0} />
            </linearGradient>
          ))}
        </defs>
        <CartesianGrid strokeDasharray="3 3" stroke="rgba(0,0,0,0.08)" />
        <XAxis
          dataKey="hour"
          tickFormatter={(h: number) => `${h}h`}
          tick={{ fontSize: 11 }}
          interval={3}
        />
        <YAxis tick={{ fontSize: 11 }} />
        <Tooltip
          formatter={(value: unknown, name: unknown) => {
            const label = labelByKey.get(String(name)) ?? String(name)
            return [typeof value === 'number' ? value.toFixed(1) : String(value), label]
          }}
          labelFormatter={(l: unknown) => `Hour ${l}:00`}
        />
        {showIndividual &&
          params.map((_, i) => (
            <Area
              key={i}
              type="monotone"
              dataKey={`animal_${i}`}
              stroke={COLORS[i % COLORS.length]}
              strokeWidth={1.5}
              fill={`url(#grad_${i})`}
              dot={false}
              isAnimationActive={false}
            />
          ))}
        <Legend formatter={(value: string) => labelByKey.get(value) ?? value} />
      </AreaChart>
    </ResponsiveContainer>
  )
}
