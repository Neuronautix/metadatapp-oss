import type { AIBehaviorOutput } from '@/domain/dvc/dvc.types.ts'
import type { TimelineSelection } from '../ActivityTimelineChart.tsx'

const toTime = (value: string) => new Date(value).getTime()

const labelColors: Record<AIBehaviorOutput['label'], string> = {
  sleep: '#bbf7d0',
  active: '#bae6fd',
  anomaly: '#fecaca',
  circadian: '#fef3c7',
}

export function AILayer({
  aiOutput,
  start,
  end,
  onSelect,
}: {
  aiOutput: AIBehaviorOutput
  start: number
  end: number
  onSelect: (value: TimelineSelection) => void
}) {
  const startX = ((toTime(aiOutput.windowStart) - start) / Math.max(1, end - start)) * 860
  const endX = ((toTime(aiOutput.windowEnd) - start) / Math.max(1, end - start)) * 860

  return (
    <g>
      <rect
        x={startX}
        y={0}
        width={Math.max(1, endX - startX)}
        height={260}
        fill={labelColors[aiOutput.label]}
        opacity={0.25}
        onClick={() => onSelect({ type: 'ai', ai: aiOutput })}
      />
      <text x={startX + 12} y={24} fontSize="10" fill="#0f172a">
        AI: {aiOutput.label}
      </text>
    </g>
  )
}
