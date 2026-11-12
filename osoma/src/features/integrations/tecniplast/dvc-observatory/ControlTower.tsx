import { useState } from 'react'
import { ChevronDown, ChevronUp, SlidersHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { CompareModePanel } from './CompareModePanel.tsx'

export type DvcOverlayToggles = {
  environment: boolean
  events: boolean
  welfare: boolean
  treatments: boolean
  ai: boolean
}

export type DvcThresholds = {
  tempRange: { min: number; max: number }
  humidityRange: { min: number; max: number }
  activityDropPct: number
  missingDataPct: number
}

export function ControlTower({
  rangeHours,
  onRangeHours,
  resolutionMinutes,
  onResolutionMinutes,
  overlays,
  onOverlays,
  thresholds,
  onThresholds,
  compareMode,
  onCompareMode,
  customRange,
  onCustomRange,
}: {
  rangeHours: number
  onRangeHours: (value: number) => void
  resolutionMinutes: number
  onResolutionMinutes: (value: number) => void
  overlays: DvcOverlayToggles
  onOverlays: (value: DvcOverlayToggles) => void
  thresholds: DvcThresholds
  onThresholds: (value: DvcThresholds) => void
  compareMode: { mode: 'none' | 'cage' | 'vcg'; cageId: string }
  onCompareMode: (value: { mode: 'none' | 'cage' | 'vcg'; cageId: string }) => void
  customRange: { from: string; to: string }
  onCustomRange: (value: { from: string; to: string }) => void
}) {
  const [collapsed, setCollapsed] = useState(false)
  const welfareEnabled = useFeatureFlag('dvc.studyal.welfare.enabled')
  const aiEnabled = useFeatureFlag('dvc.aiInsights.enabled')

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle className="flex items-center gap-2">
          <SlidersHorizontal className="h-4 w-4 text-slate-500" />
          Control Tower
        </CardTitle>
        <Button variant="ghost" size="sm" onClick={() => setCollapsed((prev) => !prev)}>
          {collapsed ? (
            <>
              Expand
              <ChevronDown className="h-4 w-4" />
            </>
          ) : (
            <>
              Collapse
              <ChevronUp className="h-4 w-4" />
            </>
          )}
        </Button>
      </CardHeader>
      {collapsed ? null : (
        <CardContent className="space-y-6 text-sm text-slate-600">
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Time range</p>
            <div className="mt-2 flex flex-wrap gap-2">
              {[24, 168, 720].map((value) => (
                <Button
                  key={value}
                  size="sm"
                  variant={rangeHours === value ? 'default' : 'outline'}
                  onClick={() => onRangeHours(value)}
                >
                  {value === 24 ? '24h' : value === 168 ? '7d' : '30d'}
                </Button>
              ))}
              <Button
                size="sm"
                variant={rangeHours === 0 ? 'default' : 'outline'}
                onClick={() => onRangeHours(0)}
              >
                Custom
              </Button>
            </div>
            {rangeHours === 0 ? (
              <div className="mt-3 grid gap-3 md:grid-cols-2">
                <div>
                  <label className="text-xs text-slate-500">From</label>
                  <input
                    type="datetime-local"
                    value={customRange.from}
                    onChange={(event) => onCustomRange({ ...customRange, from: event.target.value })}
                    className="mt-1 h-9 w-full rounded-lg border border-line px-2 text-xs"
                  />
                </div>
                <div>
                  <label className="text-xs text-slate-500">To</label>
                  <input
                    type="datetime-local"
                    value={customRange.to}
                    onChange={(event) => onCustomRange({ ...customRange, to: event.target.value })}
                    className="mt-1 h-9 w-full rounded-lg border border-line px-2 text-xs"
                  />
                </div>
              </div>
            ) : null}
          </div>

          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Resolution</p>
            <select
              className="mt-2 h-10 w-full rounded-xl border border-line bg-white px-3 text-sm"
              value={resolutionMinutes}
              onChange={(event) => onResolutionMinutes(Number(event.target.value))}
            >
              <option value={1}>1m</option>
              <option value={5}>5m</option>
              <option value={15}>15m</option>
              <option value={60}>1h</option>
            </select>
          </div>

          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Overlays</p>
            <div className="mt-2 space-y-2">
              {([
                { key: 'environment', label: 'Environment' },
                { key: 'events', label: 'Cage events' },
                { key: 'welfare', label: 'Welfare alarms', gated: welfareEnabled },
                { key: 'treatments', label: 'Treatments / sampling' },
                { key: 'ai', label: 'AI behavioral outputs', gated: aiEnabled },
              ] as const).map((item) => {
                if (item.key === 'welfare' && !item.gated) return null
                if (item.key === 'ai' && !item.gated) return null
                return (
                  <label key={item.key} className="flex items-center justify-between gap-2">
                    <span>{item.label}</span>
                    <input
                      type="checkbox"
                      checked={overlays[item.key]}
                      onChange={() => onOverlays({ ...overlays, [item.key]: !overlays[item.key] })}
                    />
                  </label>
                )
              })}
            </div>
          </div>

          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Threshold editor</p>
            <div className="mt-3 grid gap-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs text-slate-500">Temp min</label>
                  <input
                    type="number"
                    className="mt-1 h-9 w-full rounded-lg border border-line px-2"
                    value={thresholds.tempRange.min}
                    onChange={(event) =>
                      onThresholds({
                        ...thresholds,
                        tempRange: { ...thresholds.tempRange, min: Number(event.target.value) },
                      })
                    }
                  />
                </div>
                <div>
                  <label className="text-xs text-slate-500">Temp max</label>
                  <input
                    type="number"
                    className="mt-1 h-9 w-full rounded-lg border border-line px-2"
                    value={thresholds.tempRange.max}
                    onChange={(event) =>
                      onThresholds({
                        ...thresholds,
                        tempRange: { ...thresholds.tempRange, max: Number(event.target.value) },
                      })
                    }
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs text-slate-500">Humidity min</label>
                  <input
                    type="number"
                    className="mt-1 h-9 w-full rounded-lg border border-line px-2"
                    value={thresholds.humidityRange.min}
                    onChange={(event) =>
                      onThresholds({
                        ...thresholds,
                        humidityRange: { ...thresholds.humidityRange, min: Number(event.target.value) },
                      })
                    }
                  />
                </div>
                <div>
                  <label className="text-xs text-slate-500">Humidity max</label>
                  <input
                    type="number"
                    className="mt-1 h-9 w-full rounded-lg border border-line px-2"
                    value={thresholds.humidityRange.max}
                    onChange={(event) =>
                      onThresholds({
                        ...thresholds,
                        humidityRange: { ...thresholds.humidityRange, max: Number(event.target.value) },
                      })
                    }
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-xs text-slate-500">Activity drop %</label>
                  <input
                    type="number"
                    className="mt-1 h-9 w-full rounded-lg border border-line px-2"
                    value={thresholds.activityDropPct}
                    onChange={(event) =>
                      onThresholds({
                        ...thresholds,
                        activityDropPct: Number(event.target.value),
                      })
                    }
                  />
                </div>
                <div>
                  <label className="text-xs text-slate-500">Missing data %</label>
                  <input
                    type="number"
                    className="mt-1 h-9 w-full rounded-lg border border-line px-2"
                    value={thresholds.missingDataPct}
                    onChange={(event) =>
                      onThresholds({
                        ...thresholds,
                        missingDataPct: Number(event.target.value),
                      })
                    }
                  />
                </div>
              </div>
            </div>
          </div>

          <CompareModePanel compareMode={compareMode} onCompareMode={onCompareMode} />
        </CardContent>
      )}
    </Card>
  )
}
