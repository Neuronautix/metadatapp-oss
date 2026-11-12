import type { EnvironmentRecord } from '@/domain/dvc/dvc.types.ts'
import type { DvcThresholds } from '../ControlTower.tsx'

const toTime = (value: string) => new Date(value).getTime()

const buildPath = (values: number[], ts: string[], min: number, max: number, start: number, end: number, height: number) => {
  const range = Math.max(1, max - min)
  return ts
    .map((timestamp, index) => {
      const x = ((toTime(timestamp) - start) / Math.max(1, end - start)) * 860
      const value = values[index] ?? min
      const y = height - ((value - min) / range) * height
      return `${index === 0 ? 'M' : 'L'} ${x.toFixed(2)} ${y.toFixed(2)}`
    })
    .join(' ')
}

export function EnvironmentLayer({
  environment,
  thresholds,
  start,
  end,
  height,
}: {
  environment: EnvironmentRecord
  thresholds: DvcThresholds
  start: number
  end: number
  height: number
}) {
  const tempPath = buildPath(environment.temp, environment.ts, 18, 30, start, end, height)
  const humidityPath = buildPath(environment.humidity, environment.ts, 30, 80, start, end, height)

  const tempMinY = height - ((thresholds.tempRange.min - 18) / 12) * height
  const tempMaxY = height - ((thresholds.tempRange.max - 18) / 12) * height
  const humidityMinY = height - ((thresholds.humidityRange.min - 30) / 50) * height
  const humidityMaxY = height - ((thresholds.humidityRange.max - 30) / 50) * height

  return (
    <g>
      <rect
        x={0}
        y={Math.min(tempMinY, tempMaxY)}
        width={860}
        height={Math.abs(tempMaxY - tempMinY)}
        fill="#fde68a"
        opacity={0.2}
      />
      <rect
        x={0}
        y={Math.min(humidityMinY, humidityMaxY)}
        width={860}
        height={Math.abs(humidityMaxY - humidityMinY)}
        fill="#bfdbfe"
        opacity={0.15}
      />
      <path d={tempPath} fill="none" stroke="#f97316" strokeWidth={1.4} opacity={0.8} />
      <path d={humidityPath} fill="none" stroke="#0ea5e9" strokeWidth={1.2} opacity={0.7} />
    </g>
  )
}
