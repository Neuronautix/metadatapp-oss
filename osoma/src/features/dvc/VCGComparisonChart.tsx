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
import { buildSinusoidCurve, meanCircadianParams, type CircadianModelParams } from '@/domain/dvc/circadianModel.ts'
import type { VcgGroup } from '@/domain/dvc/vcgEngine.ts'

const EMPTY_CIRCADIAN_PARAMS: CircadianModelParams = { amplitude: 0, phase: 0, baseline: 0, noise: 0 }

type Props = {
  vcgGroup: VcgGroup
  height?: number
}

export function VCGComparisonChart({ vcgGroup, height = 300 }: Props) {
  const targetParams = vcgGroup.targetAnimals.map((a) => a.circadianParameters)
  const targetMean = meanCircadianParams(
    targetParams.length ? targetParams : [EMPTY_CIRCADIAN_PARAMS]
  )
  const targetCurve = buildSinusoidCurve(targetMean)

  const data = Array.from({ length: 24 }, (_, hour) => ({
    hour,
    target: Math.round(targetCurve[hour].value * 10) / 10,
    targetUpper: Math.round(targetCurve[hour].upper * 10) / 10,
    targetLower: Math.round(targetCurve[hour].lower * 10) / 10,
    vcg: Math.round(vcgGroup.vcgCurve[hour].value * 10) / 10,
    vcgUpper: Math.round(vcgGroup.vcgCurve[hour].upper * 10) / 10,
    vcgLower: Math.round(vcgGroup.vcgCurve[hour].lower * 10) / 10,
  }))

  const seriesLabels: Record<string, string> = {
    target: 'Target (mean)',
    vcg: 'VCG (mean)',
    targetUpper: 'Target +noise',
    targetLower: 'Target -noise',
    vcgUpper: 'VCG +noise',
    vcgLower: 'VCG -noise',
  }

  return (
    <ResponsiveContainer width="100%" height={height}>
      <AreaChart data={data} margin={{ top: 4, right: 12, bottom: 4, left: 0 }}>
        <defs>
          <linearGradient id="gradTarget" x1="0" y1="0" x2="0" y2="1">
            <stop offset="5%" stopColor="#6366f1" stopOpacity={0.35} />
            <stop offset="95%" stopColor="#6366f1" stopOpacity={0.0} />
          </linearGradient>
          <linearGradient id="gradVcg" x1="0" y1="0" x2="0" y2="1">
            <stop offset="5%" stopColor="#22c55e" stopOpacity={0.35} />
            <stop offset="95%" stopColor="#22c55e" stopOpacity={0.0} />
          </linearGradient>
        </defs>
        <CartesianGrid strokeDasharray="3 3" stroke="rgba(0,0,0,0.08)" />
        <XAxis dataKey="hour" tickFormatter={(h: number) => `${h}h`} tick={{ fontSize: 11 }} interval={3} />
        <YAxis tick={{ fontSize: 11 }} />
        <Tooltip
          labelFormatter={(l: unknown) => `Hour ${l}:00`}
          formatter={(value: unknown, name: unknown) => {
            const v = typeof value === 'number' ? value.toFixed(1) : String(value)
            return [v, seriesLabels[String(name)] ?? String(name)]
          }}
        />
        <Area type="monotone" dataKey="targetUpper" stroke="transparent" fill="url(#gradTarget)" dot={false} isAnimationActive={false} legendType="none" />
        <Area type="monotone" dataKey="target" stroke="#6366f1" strokeWidth={2.5} fill="url(#gradTarget)" dot={false} isAnimationActive={false} name="target" />
        <Area type="monotone" dataKey="targetLower" stroke="transparent" fill="transparent" dot={false} isAnimationActive={false} legendType="none" />
        <Area type="monotone" dataKey="vcgUpper" stroke="transparent" fill="url(#gradVcg)" dot={false} isAnimationActive={false} legendType="none" />
        <Area type="monotone" dataKey="vcg" stroke="#22c55e" strokeWidth={2.5} fill="url(#gradVcg)" dot={false} isAnimationActive={false} name="vcg" />
        <Area type="monotone" dataKey="vcgLower" stroke="transparent" fill="transparent" dot={false} isAnimationActive={false} legendType="none" />
        <Legend
          formatter={(value: string) => {
            const legendLabels: Record<string, string> = { target: 'Target cohort', vcg: 'Virtual Control Group' }
            return legendLabels[value] ?? value
          }}
        />
      </AreaChart>
    </ResponsiveContainer>
  )
}
