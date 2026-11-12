import { useMemo, useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import type { CoiNode } from '@/domain/atmp/atmp.types.ts'
import { COIDrawer } from './COIDrawer.tsx'

const stageLabels: Record<CoiNode['stage'], string> = {
  startingMaterial: 'Starting material',
  activeSubstance: 'Active substance',
  preclinicalModel: 'Preclinical model',
}

const stageOrder: Record<CoiNode['stage'], number> = {
  startingMaterial: 0,
  activeSubstance: 1,
  preclinicalModel: 2,
}

export function COITimeline({ entries }: { entries: CoiNode[] }) {
  const [selected, setSelected] = useState<CoiNode | null>(null)
  const ordered = useMemo(
    () => [...entries].sort((a, b) => stageOrder[a.stage] - stageOrder[b.stage]),
    [entries]
  )

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Chain of Identity</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-3">
          {ordered.map((entry) => (
            <div key={entry.batchId} className="rounded-2xl border border-line bg-surface-2 p-4">
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">{stageLabels[entry.stage]}</p>
              <p className="mt-2 text-sm font-semibold text-slate-800">{entry.label}</p>
              <p className="text-sm text-slate-600">{entry.batchId}</p>
              <p className="mt-2 text-xs text-slate-500">{entry.site}</p>
              <Button variant="outline" size="sm" className="mt-3" onClick={() => setSelected(entry)}>
                View trail
              </Button>
            </div>
          ))}
        </CardContent>
      </Card>
      <COIDrawer entry={selected} open={Boolean(selected)} onClose={() => setSelected(null)} />
    </>
  )
}
