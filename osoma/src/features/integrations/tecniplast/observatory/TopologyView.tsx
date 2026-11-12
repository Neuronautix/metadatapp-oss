import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import type { TopologyNode } from './observatory.types.ts'
import { ObservatoryLegend } from './ObservatoryLegend.tsx'
import { statusRingClasses } from './observatory.tokens.ts'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'

const statusPill: Record<TopologyNode['status'], string> = {
  ok: 'bg-emerald-100 text-emerald-700',
  watch: 'bg-amber-100 text-amber-700',
  risk: 'bg-sky-100 text-sky-700',
}

export function TopologyView({ root, isLoading }: { root?: TopologyNode; isLoading?: boolean }) {
  const [expandedRoom, setExpandedRoom] = useState<string | null>(null)
  const rooms = root?.children ?? []
  const maxImpact = useMemo(
    () => Math.max(1, ...rooms.map((room) => room.impact)),
    [rooms]
  )

  return (
    <Card>
      <CardHeader>
        <CardTitle>Topology view</CardTitle>
        <CardDescription>Facility → Rooms → Racks → Cages</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {isLoading ? (
          <Skeleton className="h-64 w-full" />
        ) : root ? (
          <div className="space-y-4">
            <div className="flex items-center justify-between rounded-2xl border border-line bg-slate-50/70 px-4 py-3">
              <div>
                <div className="text-xs uppercase tracking-[0.2em] text-slate-400">Facility</div>
                <div className="text-lg font-semibold text-slate-800">{root.label}</div>
              </div>
              <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusPill[root.status]}`}>
                {root.status}
              </span>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
              {rooms.map((room) => {
                const bubbleSize = 18 + (room.impact / maxImpact) * 18
                return (
                  <div
                    key={room.id}
                    className="rounded-2xl border border-line bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-subtle"
                  >
                    <div className="flex items-center justify-between">
                      <div className="space-y-1">
                        <div className="flex items-center gap-2">
                          <span className={`h-2.5 w-2.5 rounded-full ${room.status === 'risk' ? 'bg-sky-500' : room.status === 'watch' ? 'bg-amber-500' : 'bg-emerald-500'}`} />
                          <span className="text-sm font-semibold text-slate-700">{room.label}</span>
                        </div>
                        <p className="text-xs text-slate-500">Impact score {room.impact}</p>
                      </div>
                      <div
                        className={`grid place-items-center rounded-full ring-4 ${statusRingClasses[room.status]}`}
                        style={{ width: `${bubbleSize}px`, height: `${bubbleSize}px` }}
                      >
                        <span className="text-[10px] font-semibold text-slate-500">{room.impact}</span>
                      </div>
                    </div>
                    <div className="mt-3 flex items-center gap-2">
                      {room.href ? (
                        <Link to={room.href} className="text-xs font-semibold text-slate-600 hover:text-slate-900">
                          Open room
                        </Link>
                      ) : null}
                      <button
                        type="button"
                        className="text-xs text-slate-400 hover:text-slate-600"
                        onClick={() => setExpandedRoom((prev) => (prev === room.id ? null : room.id))}
                      >
                        {expandedRoom === room.id ? 'Collapse racks' : 'Reveal racks'}
                      </button>
                    </div>

                    {expandedRoom === room.id ? (
                      <div className="mt-4 space-y-3">
                        {(room.children ?? []).map((rack) => (
                          <div key={rack.id} className="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                            <div className="flex items-center justify-between text-xs text-slate-500">
                              <span className="font-semibold text-slate-700">{rack.label}</span>
                              <span>{rack.children?.length ?? 0} cages</span>
                            </div>
                            <div className="mt-2 flex flex-wrap gap-2">
                              {(rack.children ?? []).slice(0, 10).map((cage) =>
                                cage.href ? (
                                  <Link
                                    key={cage.id}
                                    to={cage.href}
                                    className={`h-3 w-3 rounded-full ${
                                      cage.status === 'watch' ? 'bg-amber-400' : 'bg-emerald-400'
                                    }`}
                                    title={`${cage.label} • ${cage.status}`}
                                  />
                                ) : (
                                  <span
                                    key={cage.id}
                                    className={`h-3 w-3 rounded-full ${
                                      cage.status === 'watch' ? 'bg-amber-400' : 'bg-emerald-400'
                                    }`}
                                    title={`${cage.label} • ${cage.status}`}
                                  />
                                )
                              )}
                              {(rack.children ?? []).length > 10 ? (
                                <span className="text-[10px] text-slate-400">
                                  +{(rack.children ?? []).length - 10} more
                                </span>
                              ) : null}
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : null}
                  </div>
                )
              })}
            </div>

            <ObservatoryLegend
              items={[
                { label: 'Stable', color: 'bg-emerald-400' },
                { label: 'Watch', color: 'bg-amber-400' },
                { label: 'Hot zone', color: 'bg-sky-500' },
              ]}
            />
          </div>
        ) : null}
      </CardContent>
    </Card>
  )
}
