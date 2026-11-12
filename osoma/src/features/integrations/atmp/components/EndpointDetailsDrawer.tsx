import { X } from 'lucide-react'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import type { EndpointMatrixEntry } from '@/domain/atmp/atmp.types.ts'

const statusVariant = (status: EndpointMatrixEntry['status']) => {
  if (status === 'Complete') return 'success'
  if (status === 'In Progress') return 'warning'
  if (status === 'Blocked') return 'critical'
  return 'outline'
}

export function EndpointDetailsDrawer({
  entry,
  open,
  onClose,
}: {
  entry: EndpointMatrixEntry | null
  open: boolean
  onClose: () => void
}) {
  if (!open || !entry) return null

  return (
    <div className="fixed inset-0 z-40 flex justify-end bg-slate-900/40">
      <div className="flex h-full w-full max-w-md flex-col bg-white shadow-xl">
        <div className="flex items-center justify-between border-b border-line px-6 py-4">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Endpoint</p>
            <h2 className="text-lg font-semibold text-slate-800">{entry.name}</h2>
          </div>
          <Button variant="ghost" size="icon" onClick={onClose}>
            <X className="h-4 w-4" />
          </Button>
        </div>
        <div className="flex-1 space-y-6 overflow-y-auto px-6 py-4 text-sm text-slate-600">
          <div className="flex items-center gap-2">
            <Badge variant={statusVariant(entry.status)}>{entry.status}</Badge>
            <Badge variant="outline">{entry.category}</Badge>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Timepoint</p>
            <p className="font-semibold text-slate-800">{entry.timepoint}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Owner</p>
            <p className="font-semibold text-slate-800">{entry.owner}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Summary</p>
            <p>{entry.summary}</p>
          </div>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Details</p>
            <ul className="mt-2 list-disc space-y-2 pl-4">
              {entry.details.map((detail) => (
                <li key={detail}>{detail}</li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </div>
  )
}
