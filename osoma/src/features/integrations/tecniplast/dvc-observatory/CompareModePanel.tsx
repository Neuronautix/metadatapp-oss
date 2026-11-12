import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'

export function CompareModePanel({
  compareMode,
  onCompareMode,
}: {
  compareMode: { mode: 'none' | 'cage' | 'vcg'; cageId: string }
  onCompareMode: (value: { mode: 'none' | 'cage' | 'vcg'; cageId: string }) => void
}) {
  return (
    <div>
      <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Compare mode</p>
      <div className="mt-2 flex flex-wrap gap-2">
        <Button
          size="sm"
          variant={compareMode.mode === 'none' ? 'default' : 'outline'}
          onClick={() => onCompareMode({ mode: 'none', cageId: '' })}
        >
          None
        </Button>
        <Button
          size="sm"
          variant={compareMode.mode === 'cage' ? 'default' : 'outline'}
          onClick={() => onCompareMode({ mode: 'cage', cageId: compareMode.cageId })}
        >
          Cage overlay
        </Button>
        <Button
          size="sm"
          variant={compareMode.mode === 'vcg' ? 'default' : 'outline'}
          onClick={() => onCompareMode({ mode: 'vcg', cageId: 'CG-RK-101-A-01' })}
        >
          VCG overlay
        </Button>
      </div>

      {compareMode.mode === 'cage' ? (
        <div className="mt-3">
          <label className="text-xs text-slate-500">Compare cage ID</label>
          <input
            value={compareMode.cageId}
            onChange={(event) => onCompareMode({ mode: 'cage', cageId: event.target.value })}
            className="mt-1 h-9 w-full rounded-lg border border-line px-2 text-sm"
            placeholder="CG-RK-101-B-03"
          />
        </div>
      ) : null}

      {compareMode.mode !== 'none' ? (
        <div className="mt-3 rounded-xl border border-line bg-surface-2 p-3 text-xs text-slate-500">
          <div className="flex items-center justify-between">
            <span>Overlay active</span>
            <Badge variant="outline">{compareMode.mode.toUpperCase()}</Badge>
          </div>
          <p className="mt-2">
            {compareMode.mode === 'vcg'
              ? 'Overlaying a virtual control cage baseline.'
              : 'Overlaying activity from a comparison cage.'}
          </p>
        </div>
      ) : null}
    </div>
  )
}
