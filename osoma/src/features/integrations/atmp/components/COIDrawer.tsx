import { X } from 'lucide-react'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import type { CoiNode } from '@/domain/atmp/atmp.types.ts'

export function COIDrawer({ entry, open, onClose }: { entry: CoiNode | null; open: boolean; onClose: () => void }) {
  if (!open || !entry) return null

  return (
    <div className="fixed inset-0 z-40 flex justify-end bg-slate-900/40">
      <div className="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-line px-6 py-4">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">COI Batch</p>
            <h2 className="text-lg font-semibold text-slate-800">{entry.batchId}</h2>
          </div>
          <Button variant="ghost" size="icon" onClick={onClose}>
            <X className="h-4 w-4" />
          </Button>
        </div>
        <div className="flex-1 space-y-6 overflow-y-auto px-6 py-4 text-sm text-slate-600">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Stage</p>
            <p className="font-semibold text-slate-800">{entry.label}</p>
            <p>{entry.material}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Site + Date</p>
            <p className="font-semibold text-slate-800">{entry.site}</p>
            <p>{entry.date}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Lab trail</p>
            <div className="mt-2 flex flex-wrap gap-2">
              {entry.labTrail.map((lab) => (
                <Badge key={lab} variant="outline">
                  {lab}
                </Badge>
              ))}
            </div>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Animal groups</p>
            <div className="mt-2 flex flex-wrap gap-2">
              {entry.animalGroups.map((group) => (
                <Badge key={group} variant="default">
                  {group}
                </Badge>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
