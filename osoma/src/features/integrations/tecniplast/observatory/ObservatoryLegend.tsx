export type LegendItem = { label: string; color: string; note?: string }

export function ObservatoryLegend({ items }: { items: LegendItem[] }) {
  return (
    <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500">
      {items.map((item) => (
        <div key={item.label} className="flex items-center gap-2">
          <span className={`h-2.5 w-2.5 rounded-full ${item.color}`} />
          <span className="uppercase tracking-[0.2em] text-[10px] text-slate-400">{item.label}</span>
          {item.note ? <span className="text-slate-400">{item.note}</span> : null}
        </div>
      ))}
    </div>
  )
}
