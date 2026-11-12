import type { WelfareAlarm } from '@/domain/dvc/dvc.types.ts'
import type { TimelineSelection } from '../ActivityTimelineChart.tsx'

const toTime = (value: string) => new Date(value).getTime()

const alarmColors: Record<WelfareAlarm['severity'], string> = {
  info: '#bae6fd',
  warning: '#fcd34d',
  critical: '#fecaca',
}

export function WelfareLayer({
  alarms,
  start,
  end,
  onSelect,
}: {
  alarms: WelfareAlarm[]
  start: number
  end: number
  onSelect: (value: TimelineSelection) => void
}) {
  return (
    <g>
      {alarms.map((alarm) => {
        const startX = ((toTime(alarm.startTs) - start) / Math.max(1, end - start)) * 860
        const endX = ((toTime(alarm.endTs ?? alarm.startTs) - start) / Math.max(1, end - start)) * 860
        return (
          <rect
            key={alarm.id}
            x={startX}
            y={0}
            width={Math.max(1, endX - startX)}
            height={260}
            fill={alarmColors[alarm.severity]}
            opacity={0.35}
            onClick={() => onSelect({ type: 'alarm', alarm })}
          />
        )
      })}
    </g>
  )
}
