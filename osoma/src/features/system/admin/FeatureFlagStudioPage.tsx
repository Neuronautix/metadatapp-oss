import { useMemo, useState } from 'react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table.tsx'
import { useFeatureFlagContext } from '@/feature-flags/FeatureFlagProvider.tsx'
import type { FeatureFlagCategory } from '@/feature-flags/featureFlag.types.ts'
import type { FeatureFlagPresetKey } from '@/feature-flags/presets.ts'
import { cn } from '@/lib/utils.ts'

type CategoryFilter = FeatureFlagCategory | 'all'

export function FeatureFlagStudioPage() {
  const {
    flags,
    presets,
    activePreset,
    setFlag,
    applyPreset,
    resetToDefaults,
    setCategoryFlags,
    tone,
    isInitialized,
  } = useFeatureFlagContext()
  const [searchQuery, setSearchQuery] = useState('')
  const [categoryFilter, setCategoryFilter] = useState<CategoryFilter>('all')

  const flagList = useMemo(() => Object.values(flags), [flags])

  const categories = useMemo(() => {
    const unique = Array.from(new Set(flagList.map((flag) => flag.category)))
    return ['all', ...unique] as CategoryFilter[]
  }, [flagList])

  const filteredFlags = useMemo(() => {
    const keyword = searchQuery.trim().toLowerCase()
    return flagList.filter((flag) => {
      const matchesCategory = categoryFilter === 'all' || flag.category === categoryFilter
      const matchesSearch =
        !keyword ||
        flag.label.toLowerCase().includes(keyword) ||
        flag.key.toLowerCase().includes(keyword) ||
        flag.description.toLowerCase().includes(keyword)
      return matchesCategory && matchesSearch
    })
  }, [categoryFilter, flagList, searchQuery])

  const dropdownValue = activePreset ?? 'custom'

  return (
    <div className="space-y-6">
      <PageHeader
        title="Feature Flag Studio"
        subtitle="Control studies, audit trails, and narrative tone without shipping code."
      />

      <Card className="space-y-4">
        <CardHeader className="flex flex-wrap items-center justify-between gap-4">
          <CardTitle>Preset tone</CardTitle>
          <div className="flex flex-wrap items-center gap-3">
            <span className="text-xs uppercase tracking-[0.3em] text-slate-400">Tone:</span>
            <Badge variant="outline" className="text-[10px]">
              {tone}
            </Badge>
            <select
              className="h-10 rounded-full border border-line bg-white px-3 text-sm text-slate-600"
              value={dropdownValue}
              onChange={(event) => {
                const value = event.target.value
                if (value === 'custom') {
                  return
                }
                applyPreset(value as FeatureFlagPresetKey)
              }}
            >
              {activePreset ? null : (
                <option value="custom" disabled>
                  Custom configuration
                </option>
              )}
              {Object.values(presets).map((preset) => (
                <option key={preset.key} value={preset.key}>
                  {preset.label}
                </option>
              ))}
            </select>
            <Button variant="ghost" size="sm" onClick={() => resetToDefaults()}>
              Reset to defaults
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-3 text-sm text-slate-500">
          <p className="text-xs uppercase tracking-[0.3em] text-slate-400">
            {isInitialized ? 'Flags synced with remote overrides' : 'Initializing flags...'}
          </p>
          <div className="flex flex-wrap gap-2 text-xs uppercase tracking-[0.3em]">
            {Object.values(presets).map((preset) => (
              <Badge key={preset.key} variant={preset.key === activePreset ? 'default' : 'outline'}>
                {preset.label}
              </Badge>
            ))}
          </div>
        </CardContent>
      </Card>

      <Card className="space-y-4">
        <div className="flex flex-wrap items-center gap-3">
          <Input
            placeholder="Search flags"
            value={searchQuery}
            onChange={(event) => setSearchQuery(event.target.value)}
          />
          <select
            className="h-10 rounded-full border border-line bg-white px-3 text-sm text-slate-600"
            value={categoryFilter}
            onChange={(event) => setCategoryFilter(event.target.value as CategoryFilter)}
          >
            {categories.map((option) => (
              <option key={option} value={option}>
                {option === 'all' ? 'All categories' : option}
              </option>
            ))}
          </select>

          {categoryFilter !== 'all' && (
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                className="h-10 rounded-full border-emerald-200 text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700"
                onClick={() => setCategoryFlags(categoryFilter, true)}
              >
                Activate All
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="h-10 rounded-full border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-600"
                onClick={() => setCategoryFlags(categoryFilter, false)}
              >
                Deactivate All
              </Button>
            </div>
          )}
        </div>

        <div className="overflow-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Key</TableHead>
                <TableHead>Category</TableHead>
                <TableHead>Enabled</TableHead>
                <TableHead>Environments</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredFlags.map((flag) => (
                <TableRow key={flag.key}>
                  <TableCell>
                    <div className="flex flex-col gap-1">
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-slate-900">{flag.label}</span>
                        {flag.category === 'studyal' ? (
                          <Badge variant="warning" className="text-[10px] uppercase">
                            Studyal
                          </Badge>
                        ) : null}
                        {flag.environments?.includes('prod') ? (
                          <Badge variant="outline" className="text-[10px] uppercase">
                            Prod impact
                          </Badge>
                        ) : null}
                      </div>
                      <p className="text-xs text-slate-500">{flag.description}</p>
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs text-slate-500">{flag.key}</span>
                  </TableCell>
                  <TableCell>
                    <span className="text-xs uppercase tracking-[0.3em] text-slate-400">
                      {flag.category}
                    </span>
                  </TableCell>
                  <TableCell>
                    <button
                      type="button"
                      onClick={() => setFlag(flag.key, !flag.enabled)}
                      className={cn(
                        'inline-flex h-9 items-center justify-center rounded-full px-4 text-xs font-semibold transition',
                        flag.enabled ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500'
                      )}
                    >
                      {flag.enabled ? 'On' : 'Off'}
                    </button>
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-wrap gap-2">
                      {(flag.environments ?? []).map((environment) => (
                        <Badge key={environment} variant="outline" className="text-[10px] uppercase">
                          {environment}
                        </Badge>
                      ))}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </Card>
    </div>
  )
}
