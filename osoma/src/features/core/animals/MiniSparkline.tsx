export function MiniSparkline({ values, stroke = '#0f172a' }: { values: number[]; stroke?: string }) {
  if (!values.length) {
    return <div className="h-8 w-24 rounded-full bg-slate-100" />
  }

  const width = 120
  const height = 36
  const padding = 4
  const min = Math.min(...values)
  const max = Math.max(...values)
  const range = max - min || 1
  const step = (width - padding * 2) / Math.max(values.length - 1, 1)

  const points = values.map((value, index) => {
    const x = padding + step * index
    const y = height - padding - ((value - min) / range) * (height - padding * 2)
    return `${x},${y}`
  })

  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} aria-hidden="true">
      <polyline
        fill="none"
        stroke={stroke}
        strokeWidth="2"
        strokeLinejoin="round"
        strokeLinecap="round"
        points={points.join(' ')}
      />
    </svg>
  )
}
