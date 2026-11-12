import * as React from 'react'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { cn } from '@/lib/utils.ts'
import { useTheme } from '@/theme/useTheme.ts'
import { clamp, formatHsl, hexToHsl, hslToHex, type HslColor, type ThemeTokens } from '@/theme/theme.tokens.ts'

const FONT_OPTIONS = [
  'IBM Plex Sans',
  'Source Sans 3',
  'Manrope',
  'Space Grotesk',
  'Sora',
  'IBM Plex Mono',
  'JetBrains Mono',
]

const COLOR_LABELS: Array<{
  key: ColorTarget
  label: string
  description: string
}> = [
    { key: 'primary', label: 'Primary', description: 'Brand lead. Buttons, highlights, key links.' },
    { key: 'secondary', label: 'Secondary', description: 'Structure anchor. Panels, nav, contrast.' },
    { key: 'accent', label: 'Accent', description: 'Moments of energy. Callouts and focus states.' },
    { key: 'success', label: 'Success', description: 'Positive outcomes and stable states.' },
    { key: 'warning', label: 'Warning', description: 'Elevated attention without panic.' },
    { key: 'error', label: 'Error', description: 'Critical signals and urgent actions.' },
    { key: 'info', label: 'Info', description: 'Neutral notices and informative states.' },
  ]

const DATA_VIZ_PRESETS: Record<
  string,
  {
    scheme: string[]
    gridVisibility: number
    axisContrast: number
    curveSmoothness: number
    markerSize: number
    animationIntensity: number
  }
> = {
  scientific: {
    scheme: ['#1f2937', '#2563eb', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444'],
    gridVisibility: 72,
    axisContrast: 75,
    curveSmoothness: 20,
    markerSize: 3,
    animationIntensity: 20,
  },
  'live-ops': {
    scheme: ['#0ea5e9', '#22c55e', '#f97316', '#facc15', '#6366f1', '#ec4899'],
    gridVisibility: 45,
    axisContrast: 55,
    curveSmoothness: 55,
    markerSize: 4,
    animationIntensity: 60,
  },
  executive: {
    scheme: ['#0f766e', '#14b8a6', '#0ea5e9', '#6366f1', '#f97316', '#ef4444'],
    gridVisibility: 55,
    axisContrast: 60,
    curveSmoothness: 40,
    markerSize: 4,
    animationIntensity: 40,
  },
  'dark-lab': {
    scheme: ['#22d3ee', '#38bdf8', '#0ea5e9', '#6366f1', '#f97316', '#f43f5e'],
    gridVisibility: 30,
    axisContrast: 52,
    curveSmoothness: 65,
    markerSize: 4,
    animationIntensity: 35,
  },
}

type ColorTarget =
  | 'primary'
  | 'secondary'
  | 'accent'
  | 'success'
  | 'warning'
  | 'error'
  | 'info'

export function AppearanceStudio() {
  const {
    tokens,
    presets,
    activePresetId,
    applyPreset,
    updateTokens,
    undo,
    reset,
    canUndo,
    exportTokens,
    importTokens,
    savePreset,
    duplicatePreset,
    removePreset,
  } = useTheme()
  const { toast } = useToast()
  const [importText, setImportText] = React.useState('')
  const [presetName, setPresetName] = React.useState('')

  const exportText = React.useMemo(() => exportTokens(), [exportTokens])

  const updateColor = React.useCallback(
    (target: ColorTarget, color: HslColor) => {
      updateTokens((current) => {
        if (target === 'success' || target === 'warning' || target === 'error' || target === 'info') {
          return {
            ...current,
            colors: {
              ...current.colors,
              semantic: {
                ...current.colors.semantic,
                [target]: color,
              },
            },
          }
        }

        return {
          ...current,
          colors: {
            ...current.colors,
            [target]: color,
          },
        }
      })
    },
    [updateTokens]
  )

  const updateNeutral = (key: 'warmth' | 'density' | 'elevation', value: number) => {
    updateTokens((current) => ({
      ...current,
      colors: {
        ...current.colors,
        neutrals: {
          ...current.colors.neutrals,
          [key]: value,
        },
      },
    }))
  }

  const updateAdvanced = (key: 'hueShift' | 'saturationCurve' | 'contrastBoost', value: number | boolean) => {
    updateTokens((current) => ({
      ...current,
      advanced: {
        ...current.advanced,
        [key]: value,
      },
    }))
  }

  const updateSurface = (
    key: 'cardOpacity' | 'borderVsShadow' | 'shadowSoftness' | 'radiusScale',
    value: number
  ) => {
    updateTokens((current) => ({
      ...current,
      surfaces: {
        ...current.surfaces,
        [key]: value,
      },
    }))
  }

  const updateShadowTint = (value: HslColor) => {
    updateTokens((current) => ({
      ...current,
      surfaces: {
        ...current.surfaces,
        shadowTint: value,
      },
    }))
  }

  const updateBackground = <K extends keyof ThemeTokens['background']>(
    key: K,
    value: ThemeTokens['background'][K]
  ) => {
    updateTokens((current) => ({
      ...current,
      background: {
        ...current.background,
        [key]: value,
      },
    }))
  }

  const updateTypography = <K extends keyof ThemeTokens['typography']>(
    key: K,
    value: ThemeTokens['typography'][K]
  ) => {
    updateTokens((current) => ({
      ...current,
      typography: {
        ...current.typography,
        [key]: value,
      },
    }))
  }

  const updateDensity = <K extends keyof ThemeTokens['density']>(
    key: K,
    value: ThemeTokens['density'][K]
  ) => {
    updateTokens((current) => ({
      ...current,
      density: {
        ...current.density,
        [key]: value,
      },
    }))
  }

  const updateDataViz = <K extends keyof ThemeTokens['dataViz']>(
    key: K,
    value: ThemeTokens['dataViz'][K]
  ) => {
    updateTokens((current) => ({
      ...current,
      dataViz: {
        ...current.dataViz,
        [key]: value,
      },
    }))
  }

  const setDataVizScheme = (index: number, value: string) => {
    updateTokens((current) => {
      const scheme = [...current.dataViz.scheme]
      scheme[index] = value
      return {
        ...current,
        dataViz: {
          ...current.dataViz,
          scheme,
        },
      }
    })
  }

  const toggleLock = (target: ColorTarget) => {
    updateTokens((current) => ({
      ...current,
      palette: {
        ...current.palette,
        locks: {
          ...current.palette.locks,
          [target]: !current.palette.locks[target],
        },
      },
    }))
  }

  const generatePalette = () => {
    updateTokens((current) => {
      const { locks } = current.palette
      const primary = current.colors.primary
      const secondary = locks.secondary
        ? current.colors.secondary
        : shiftColor(primary, 24, -6, -8)
      const accent = locks.accent ? current.colors.accent : shiftColor(primary, -28, 6, 6)
      const success = locks.success ? current.colors.semantic.success : { h: 150, s: 58, l: 36 }
      const warning = locks.warning ? current.colors.semantic.warning : { h: 34, s: 88, l: 48 }
      const error = locks.error ? current.colors.semantic.error : { h: 354, s: 72, l: 50 }
      const info = locks.info ? current.colors.semantic.info : { h: 210, s: 66, l: 48 }

      return {
        ...current,
        colors: {
          ...current.colors,
          primary: locks.primary ? current.colors.primary : primary,
          secondary,
          accent,
          semantic: {
            success,
            warning,
            error,
            info,
          },
        },
      }
    })
  }

  const handleImport = () => {
    if (!importText.trim()) {
      toast({ title: 'Paste a palette first.', variant: 'error' })
      return
    }

    const result = applyPaletteImport(importText, updateTokens)
    if (!result.ok) {
      toast({ title: 'Import failed', description: result.error, variant: 'error' })
      return
    }

    toast({ title: 'Palette imported', description: result.message, variant: 'success' })
    setImportText('')
  }

  const handleCopyExport = async () => {
    try {
      await navigator.clipboard.writeText(exportText)
      toast({ title: 'Preset JSON copied to clipboard', variant: 'success' })
    } catch {
      toast({ title: 'Clipboard unavailable', description: 'Select the JSON and copy manually.' })
    }
  }

  const handleSavePreset = () => {
    if (!presetName.trim()) {
      toast({ title: 'Name required', description: 'Give the preset a name before saving.' })
      return
    }
    savePreset(presetName.trim())
    toast({ title: 'Preset saved', description: `"${presetName.trim()}" is ready.` })
    setPresetName('')
  }

  return (
    <div className="space-y-10">
      <div className="flex flex-wrap items-center justify-end gap-2">
        <Button variant="outline" onClick={undo} disabled={!canUndo}>
          Undo
        </Button>
        <Button variant="outline" onClick={reset}>
          Reset
        </Button>
      </div>

      <Card className="border-dashed">
        <CardHeader>
          <CardTitle>Demo Moment</CardTitle>
          <CardDescription>Hit every beat. Show authorship, not theming.</CardDescription>
        </CardHeader>
        <CardContent className="grid gap-2 text-sm text-muted md:grid-cols-2">
          <p>1) Open Appearance Studio</p>
          <p>2) Change primary color → whole app adapts</p>
          <p>3) Switch density → tables transform</p>
          <p>4) Change chart style → data feels different</p>
          <p>5) Apply “Dark Lab” preset → silence</p>
          <p>6) Export preset JSON</p>
          <p>7) Re-import → instant restore</p>
        </CardContent>
      </Card>

      <div className="grid gap-8 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div className="space-y-8">
          <Card>
            <CardHeader>
              <CardTitle>Brand & Color System</CardTitle>
              <CardDescription>Primary control with HSL precision and semantic safety.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid gap-4 lg:grid-cols-2">
                {COLOR_LABELS.map((item) => (
                  <ColorEditor
                    key={item.key}
                    label={item.label}
                    description={item.description}
                    color={getColor(tokens, item.key)}
                    locked={tokens.palette.locks[item.key]}
                    onToggleLock={() => toggleLock(item.key)}
                    onChange={(value) => updateColor(item.key, value)}
                  />
                ))}
              </div>

              <div className="grid gap-6 lg:grid-cols-3">
                <RangeControl
                  label="Hue shift"
                  value={tokens.advanced.hueShift}
                  min={-45}
                  max={45}
                  step={1}
                  onChange={(value) => updateAdvanced('hueShift', value)}
                  suffix="°"
                />
                <RangeControl
                  label="Saturation curve"
                  value={tokens.advanced.saturationCurve}
                  min={0.6}
                  max={1.4}
                  step={0.01}
                  onChange={(value) => updateAdvanced('saturationCurve', value)}
                />
                <ToggleRow
                  label="Contrast boost"
                  description="Auto-guardrails for WCAG-friendly contrast."
                  checked={tokens.advanced.contrastBoost}
                  onToggle={(value) => updateAdvanced('contrastBoost', value)}
                />
              </div>

              <div className="grid gap-4 rounded-[var(--radius-2xl)] border border-line bg-surface-2 p-4">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">
                  Live Preview
                </p>
                <div className="flex flex-wrap items-center gap-3">
                  <Button>Primary Action</Button>
                  <Button variant="secondary">Secondary</Button>
                  <Badge variant="success">Success</Badge>
                  <Badge variant="warning">Warning</Badge>
                  <Badge variant="outline">Neutral</Badge>
                  <a className="text-sm font-semibold" href="#preview">
                    Live link
                  </a>
                  <div className="rounded-full border border-line bg-surface px-3 py-1.5 text-xs text-muted ring-2 ring-focus">
                    Focus ring
                  </div>
                </div>
                <div className="grid gap-3 md:grid-cols-2">
                  <Input placeholder="Focus me" />
                  <div className="rounded-[var(--radius-xl)] border border-line bg-surface px-4 py-3 text-xs text-muted">
                    Focus rings are tuned to the accent color for visibility.
                  </div>
                </div>
                <ChartPreview smoothness={tokens.dataViz.curveSmoothness} markerSize={tokens.dataViz.markerSize} />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Palette Engine (Nerd Mode)</CardTitle>
              <CardDescription>Generate, lock, or import structured palettes.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="flex flex-wrap items-center gap-3">
                <Button onClick={generatePalette}>Generate from primary</Button>
                <Button
                  variant="outline"
                  onClick={() =>
                    updateTokens((current) => ({
                      ...current,
                      palette: { ...current.palette, locks: Object.fromEntries(Object.keys(current.palette.locks).map((key) => [key, false])) },
                    }))
                  }
                >
                  Unlock all
                </Button>
              </div>

              <div className="grid gap-4 md:grid-cols-3">
                <RangeControl
                  label="Neutral warmth"
                  value={tokens.colors.neutrals.warmth}
                  min={0}
                  max={100}
                  step={1}
                  onChange={(value) => updateNeutral('warmth', value)}
                />
                <RangeControl
                  label="Gray density"
                  value={tokens.colors.neutrals.density}
                  min={0}
                  max={100}
                  step={1}
                  onChange={(value) => updateNeutral('density', value)}
                />
                <RangeControl
                  label="Elevation contrast"
                  value={tokens.colors.neutrals.elevation}
                  min={0}
                  max={100}
                  step={1}
                  onChange={(value) => updateNeutral('elevation', value)}
                />
              </div>

              <div className="grid gap-4 rounded-[var(--radius-2xl)] border border-line bg-surface-2 p-4">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">
                  Surface Preview
                </p>
                <div className="grid gap-4 md:grid-cols-[140px_minmax(0,1fr)]">
                  <div className="flex flex-col gap-2 rounded-[var(--radius-xl)] border border-line bg-surface p-3 text-xs text-muted">
                    <span className="font-semibold text-ink">Sidebar</span>
                    <span>Navigation</span>
                    <span>Filters</span>
                    <span>Switches</span>
                  </div>
                  <div className="grid gap-3">
                    <div className="rounded-[var(--radius-xl)] border border-line bg-surface p-4">
                      <p className="text-sm font-semibold text-ink">Card surface</p>
                      <p className="mt-1 text-xs text-muted">Neutral balance, depth, and line weight.</p>
                    </div>
                    <div className="rounded-[var(--radius-xl)] border border-line bg-surface-2 p-4">
                      <p className="text-sm font-semibold text-ink">Modal layer</p>
                      <p className="mt-1 text-xs text-muted">Elevation contrast drives separation.</p>
                    </div>
                  </div>
                </div>
              </div>

              <div className="grid gap-3">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">Import palette</p>
                <Textarea
                  value={importText}
                  onChange={(event) => setImportText(event.target.value)}
                  placeholder="Paste JSON, Tailwind colors, or Radix scale here."
                />
                <div className="flex flex-wrap items-center gap-2">
                  <Button onClick={handleImport}>Import palette</Button>
                  <span className="text-xs text-muted">Accepts JSON, Tailwind, Radix-style scales.</span>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Background & Surfaces</CardTitle>
              <CardDescription>Craft atmosphere and physical depth.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid gap-4 md:grid-cols-3">
                <Segmented
                  label="App background"
                  options={['flat', 'gradient', 'noise'] as const}
                  value={tokens.background.style}
                  onChange={(value) => updateBackground('style', value)}
                />
                <RangeControl
                  label="Card opacity"
                  value={Math.round(tokens.surfaces.cardOpacity * 100)}
                  min={85}
                  max={100}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateSurface('cardOpacity', value / 100)}
                />
                <RangeControl
                  label="Border vs shadow"
                  value={Math.round(tokens.surfaces.borderVsShadow * 100)}
                  min={0}
                  max={100}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateSurface('borderVsShadow', value / 100)}
                />
              </div>
              <div className="grid gap-4 md:grid-cols-3">
                <RangeControl
                  label="Shadow softness"
                  value={Math.round(tokens.surfaces.shadowSoftness * 100)}
                  min={0}
                  max={100}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateSurface('shadowSoftness', value / 100)}
                />
                <RangeControl
                  label="Radius scale"
                  value={Math.round(tokens.surfaces.radiusScale * 100)}
                  min={70}
                  max={140}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateSurface('radiusScale', value / 100)}
                />
                <ColorEditor
                  compact
                  label="Shadow tint"
                  description="Shadow hue and cast."
                  color={tokens.surfaces.shadowTint}
                  locked={false}
                  onToggleLock={() => undefined}
                  onChange={updateShadowTint}
                />
              </div>
              <div className="grid gap-4 md:grid-cols-3">
                <RangeControl
                  label="Gradient intensity"
                  value={tokens.background.gradientIntensity}
                  min={0}
                  max={100}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateBackground('gradientIntensity', value)}
                />
                <RangeControl
                  label="Noise intensity"
                  value={tokens.background.noiseIntensity}
                  min={0}
                  max={100}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateBackground('noiseIntensity', value)}
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Typography (For Perfectionists)</CardTitle>
              <CardDescription>Dial in tone, tempo, and readability.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid gap-4 md:grid-cols-3">
                <SelectControl
                  label="Body"
                  value={tokens.typography.body}
                  options={FONT_OPTIONS}
                  onChange={(value) => updateTypography('body', value)}
                />
                <SelectControl
                  label="Heading"
                  value={tokens.typography.heading}
                  options={FONT_OPTIONS}
                  onChange={(value) => updateTypography('heading', value)}
                />
                <SelectControl
                  label="Mono"
                  value={tokens.typography.mono}
                  options={FONT_OPTIONS}
                  onChange={(value) => updateTypography('mono', value)}
                />
              </div>
              <div className="grid gap-4 md:grid-cols-3">
                <RangeControl
                  label="Base size"
                  value={tokens.typography.baseSize}
                  min={13}
                  max={18}
                  step={1}
                  suffix="px"
                  onChange={(value) => updateTypography('baseSize', value)}
                />
                <RangeControl
                  label="Line height"
                  value={tokens.typography.lineHeight}
                  min={1.2}
                  max={1.8}
                  step={0.05}
                  onChange={(value) => updateTypography('lineHeight', value)}
                />
                <RangeControl
                  label="Letter spacing"
                  value={tokens.typography.letterSpacing}
                  min={-0.5}
                  max={1}
                  step={0.1}
                  suffix="px"
                  onChange={(value) => updateTypography('letterSpacing', value)}
                />
              </div>
              <Segmented
                label="Numeric style"
                options={['tabular', 'proportional'] as const}
                value={tokens.typography.numeric}
                onChange={(value) => updateTypography('numeric', value)}
              />

              <div className="grid gap-3 rounded-[var(--radius-2xl)] border border-line bg-surface-2 p-4">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">
                  Typography Preview
                </p>
                <div>
                  <h3 className="font-display text-2xl text-ink">Dashboard Title</h3>
                  <p className="text-sm text-muted">
                    Body copy explaining a dense data view. Numbers align: 12,345 → 12,346.
                  </p>
                </div>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Metric</TableHead>
                      <TableHead>Value</TableHead>
                      <TableHead>Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow>
                      <TableCell>Baseline</TableCell>
                      <TableCell>12,450</TableCell>
                      <TableCell>
                        <Badge variant="success">Stable</Badge>
                      </TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell>Variance</TableCell>
                      <TableCell>+2.4%</TableCell>
                      <TableCell>
                        <Badge variant="warning">Watch</Badge>
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
                <div className="rounded-[var(--radius-xl)] border border-line bg-surface p-4">
                  <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">Timeline</p>
                  <div className="mt-3 space-y-3 border-l border-line pl-4 text-xs text-muted">
                    <div>
                      <p className="font-semibold text-ink">08:24</p>
                      <p>Baseline captured, sensors calibrated.</p>
                    </div>
                    <div>
                      <p className="font-semibold text-ink">11:02</p>
                      <p>Variance flagged, review assigned.</p>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Density & Rhythm</CardTitle>
              <CardDescription>Same app, different personality.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <Segmented
                label="UI density"
                options={['compact', 'comfortable', 'spacious'] as const}
                value={tokens.density.mode}
                onChange={(value) => updateDensity('mode', value)}
              />
              <div className="grid gap-4 md:grid-cols-3">
                <RangeControl
                  label="Spacing scale"
                  value={tokens.density.spacingScale}
                  min={0.8}
                  max={1.4}
                  step={0.05}
                  onChange={(value) => updateDensity('spacingScale', value)}
                />
                <RangeControl
                  label="Table row height"
                  value={tokens.density.tableRowHeight}
                  min={36}
                  max={56}
                  step={1}
                  suffix="px"
                  onChange={(value) => updateDensity('tableRowHeight', value)}
                />
                <RangeControl
                  label="Card padding"
                  value={tokens.density.cardPadding}
                  min={16}
                  max={32}
                  step={1}
                  suffix="px"
                  onChange={(value) => updateDensity('cardPadding', value)}
                />
              </div>
              <div className="rounded-[var(--radius-2xl)] border border-line bg-surface-2 p-4">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Sample</TableHead>
                      <TableHead>Owner</TableHead>
                      <TableHead>Latency</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow>
                      <TableCell>ST-45A</TableCell>
                      <TableCell>Dr. Q. Lin</TableCell>
                      <TableCell>2.4s</TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell>ST-46B</TableCell>
                      <TableCell>Ops Team</TableCell>
                      <TableCell>3.1s</TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Data Visualization Style</CardTitle>
              <CardDescription>Expressive, controlled, and chart-native.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid gap-4 md:grid-cols-3">
                <RangeControl
                  label="Grid visibility"
                  value={tokens.dataViz.gridVisibility}
                  min={0}
                  max={100}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateDataViz('gridVisibility', value)}
                />
                <RangeControl
                  label="Axis contrast"
                  value={tokens.dataViz.axisContrast}
                  min={0}
                  max={100}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateDataViz('axisContrast', value)}
                />
                <RangeControl
                  label="Curve smoothness"
                  value={tokens.dataViz.curveSmoothness}
                  min={0}
                  max={100}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateDataViz('curveSmoothness', value)}
                />
              </div>
              <div className="grid gap-4 md:grid-cols-3">
                <RangeControl
                  label="Marker size"
                  value={tokens.dataViz.markerSize}
                  min={2}
                  max={8}
                  step={1}
                  suffix="px"
                  onChange={(value) => updateDataViz('markerSize', value)}
                />
                <RangeControl
                  label="Animation intensity"
                  value={tokens.dataViz.animationIntensity}
                  min={0}
                  max={100}
                  step={1}
                  suffix="%"
                  onChange={(value) => updateDataViz('animationIntensity', value)}
                />
                <Segmented
                  label="Preset"
                  options={['scientific', 'live-ops', 'executive', 'dark-lab'] as const}
                  value={tokens.dataViz.preset}
                  onChange={(value) =>
                    updateTokens((current) => ({
                      ...current,
                      dataViz: {
                        ...current.dataViz,
                        ...(DATA_VIZ_PRESETS[value] ?? {}),
                        preset: value,
                      },
                    }))
                  }
                />
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div className="rounded-[var(--radius-2xl)] border border-line bg-surface-2 p-4">
                  <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">
                    Chart colors
                  </p>
                  <div className="mt-3 grid gap-2">
                    {tokens.dataViz.scheme.slice(0, 6).map((color, index) => (
                      <div key={`${color}-${index}`} className="flex items-center gap-3">
                        <span
                          className="h-6 w-6 rounded-full border border-line"
                          style={{ backgroundColor: color }}
                        />
                        <Input
                          value={color}
                          onChange={(event) => setDataVizScheme(index, event.target.value)}
                        />
                      </div>
                    ))}
                  </div>
                </div>
                <ChartPreview
                  detailed
                  smoothness={tokens.dataViz.curveSmoothness}
                  markerSize={tokens.dataViz.markerSize}
                />
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Presets & Moods</CardTitle>
              <CardDescription>Curated moods plus your own saved versions.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid gap-4 md:grid-cols-2">
                {presets.map((preset) => (
                  <PresetCard
                    key={preset.id}
                    preset={preset}
                    active={activePresetId === preset.id}
                    onApply={() => applyPreset(preset.id)}
                    onDuplicate={() => duplicatePreset(preset.id)}
                    onRemove={() => removePreset(preset.id)}
                    removable={preset.id.startsWith('custom-')}
                  />
                ))}
              </div>
              <div className="flex flex-wrap items-center gap-2">
                <Input
                  value={presetName}
                  onChange={(event) => setPresetName(event.target.value)}
                  placeholder="Name your preset"
                  className="max-w-[220px]"
                />
                <Button onClick={handleSavePreset}>Save custom preset</Button>
              </div>
              <div className="grid gap-3">
                <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">
                  Export / Import
                </p>
                <Textarea value={exportText} readOnly />
                <div className="flex flex-wrap items-center gap-2">
                  <Button onClick={handleCopyExport}>Copy JSON</Button>
                  <Button variant="outline" onClick={() => importTokens(exportText)}>
                    Load from export
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Control Room Status</CardTitle>
              <CardDescription>Immediate feedback for your visual system.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
              <div className="flex items-center justify-between">
                <span>Active preset</span>
                <Badge variant="outline">{tokens.meta.name}</Badge>
              </div>
              <div className="flex items-center justify-between">
                <span>Mode</span>
                <Badge variant="outline">{tokens.mode}</Badge>
              </div>
              <div className="flex items-center justify-between">
                <span>Density</span>
                <Badge variant="outline">{tokens.density.mode}</Badge>
              </div>
              <div className="flex items-center justify-between">
                <span>Background</span>
                <Badge variant="outline">{tokens.background.style}</Badge>
              </div>
              <div className="rounded-[var(--radius-xl)] border border-line bg-surface-2 p-3 text-xs text-muted">
                Changes persist to localStorage and user profile automatically.
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Surface Snapshot</CardTitle>
              <CardDescription>Cards, modals, and dense data views.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="rounded-[var(--radius-xl)] border border-line bg-surface p-4 shadow-subtle">
                <p className="text-sm font-semibold text-ink">Modal preview</p>
                <p className="mt-1 text-xs text-muted">Shadow softness + border dominance.</p>
                <div className="mt-3 flex items-center gap-2">
                  <Button size="sm">Confirm</Button>
                  <Button size="sm" variant="outline">
                    Cancel
                  </Button>
                </div>
              </div>
              <div className="rounded-[var(--radius-xl)] border border-line bg-surface-2 p-4">
                <p className="text-sm font-semibold text-ink">Sidebar preview</p>
                <p className="mt-1 text-xs text-muted">Navigation density + background style.</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}

type ColorEditorProps = {
  label: string
  description: string
  color: HslColor
  locked: boolean
  compact?: boolean
  onToggleLock: () => void
  onChange: (value: HslColor) => void
}

function ColorEditor({ label, description, color, locked, compact, onToggleLock, onChange }: ColorEditorProps) {
  const hex = hslToHex(color)
  const [hexValue, setHexValue] = React.useState(hex)

  React.useEffect(() => {
    setHexValue(hex)
  }, [hex])

  const handleHexChange = (value: string) => {
    setHexValue(value)
    const next = hexToHsl(value)
    if (next) {
      onChange(next)
    }
  }

  return (
    <div
      className={cn(
        'rounded-[var(--radius-xl)] border border-line bg-surface p-4',
        compact && 'p-3'
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="flex items-center gap-3">
          <span
            className="h-10 w-10 rounded-[var(--radius-lg)] border border-line"
            style={{ backgroundColor: formatHsl(color) }}
          />
          <div>
            <p className="text-sm font-semibold text-ink">{label}</p>
            <p className="text-xs text-muted">{description}</p>
          </div>
        </div>
        {compact ? null : (
          <Button size="sm" variant={locked ? 'secondary' : 'outline'} onClick={onToggleLock}>
            {locked ? 'Locked' : 'Lock'}
          </Button>
        )}
      </div>
      <div className="mt-3 grid gap-3">
        <Input value={hexValue} onChange={(event) => handleHexChange(event.target.value)} />
        <div className="grid grid-cols-3 gap-2">
          <SliderField
            label="H"
            value={color.h}
            min={0}
            max={360}
            step={1}
            onChange={(value) => onChange({ ...color, h: value })}
          />
          <SliderField
            label="S"
            value={color.s}
            min={0}
            max={100}
            step={1}
            onChange={(value) => onChange({ ...color, s: value })}
          />
          <SliderField
            label="L"
            value={color.l}
            min={0}
            max={100}
            step={1}
            onChange={(value) => onChange({ ...color, l: value })}
          />
        </div>
      </div>
    </div>
  )
}

function SliderField({
  label,
  value,
  min,
  max,
  step,
  onChange,
}: {
  label: string
  value: number
  min: number
  max: number
  step: number
  onChange: (value: number) => void
}) {
  return (
    <label className="flex flex-col gap-1 text-xs text-muted">
      <span className="flex items-center justify-between text-[11px] uppercase tracking-[0.2em]">
        {label}
        <span className="text-ink">{Math.round(value)}</span>
      </span>
      <input
        type="range"
        min={min}
        max={max}
        step={step}
        value={value}
        onChange={(event) => onChange(Number(event.target.value))}
      />
    </label>
  )
}

function RangeControl({
  label,
  value,
  min,
  max,
  step,
  onChange,
  suffix,
}: {
  label: string
  value: number
  min: number
  max: number
  step: number
  onChange: (value: number) => void
  suffix?: string
}) {
  return (
    <label className="flex flex-col gap-2 rounded-[var(--radius-xl)] border border-line bg-surface p-3 text-xs text-muted">
      <span className="flex items-center justify-between text-[11px] uppercase tracking-[0.2em]">
        {label}
        <span className="text-ink">
          {typeof value === 'number' ? value : Number(value)}
          {suffix}
        </span>
      </span>
      <input
        type="range"
        min={min}
        max={max}
        step={step}
        value={value}
        onChange={(event) => onChange(Number(event.target.value))}
      />
    </label>
  )
}

function Segmented<T extends string>({
  label,
  options,
  value,
  onChange,
}: {
  label: string
  options: readonly T[]
  value: T
  onChange: (value: T) => void
}) {
  return (
    <div className="space-y-2">
      <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted">{label}</p>
      <div className="flex flex-wrap gap-2">
        {options.map((option) => (
          <Button
            key={option}
            size="sm"
            variant={value === option ? 'secondary' : 'outline'}
            onClick={() => onChange(option)}
          >
            {option}
          </Button>
        ))}
      </div>
    </div>
  )
}

function SelectControl<T extends string>({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: T
  options: readonly T[]
  onChange: (value: T) => void
}) {
  return (
    <label className="flex flex-col gap-2 rounded-[var(--radius-xl)] border border-line bg-surface p-3 text-xs text-muted">
      <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted">{label}</span>
      <select
        className="rounded-[var(--radius-xl)] border border-line bg-surface px-3 py-2 text-sm text-ink"
        value={value}
        onChange={(event) => onChange(event.target.value as T)}
      >
        {options.map((option) => (
          <option key={option} value={option}>
            {option}
          </option>
        ))}
      </select>
    </label>
  )
}

function ToggleRow({
  label,
  description,
  checked,
  onToggle,
}: {
  label: string
  description: string
  checked: boolean
  onToggle: (value: boolean) => void
}) {
  return (
    <div className="flex items-center justify-between rounded-[var(--radius-xl)] border border-line bg-surface p-3 text-xs">
      <div>
        <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted">{label}</p>
        <p className="text-xs text-muted">{description}</p>
      </div>
      <button
        className={cn(
          'rounded-full border border-line px-3 py-1 text-xs font-semibold',
          checked ? 'bg-secondary text-on-secondary' : 'bg-surface text-muted'
        )}
        onClick={() => onToggle(!checked)}
      >
        {checked ? 'On' : 'Off'}
      </button>
    </div>
  )
}

function ChartPreview({
  detailed,
  smoothness,
  markerSize,
}: {
  detailed?: boolean
  smoothness: number
  markerSize: number
}) {
  const useSmooth = smoothness >= 50
  const mainPath = useSmooth
    ? 'M0 85 C40 70, 80 40, 120 55 C160 72, 200 38, 260 30'
    : 'M0 90 L40 70 L80 50 L120 60 L160 50 L200 40 L260 30'
  const secondaryPath = useSmooth
    ? 'M0 95 C50 80, 90 70, 120 75 C150 80, 200 70, 260 60'
    : 'M0 98 L50 85 L90 78 L120 80 L150 76 L200 70 L260 60'
  return (
    <div className={cn('rounded-[var(--radius-2xl)] border border-line bg-surface p-4', detailed && 'p-5')}>
      <div className="flex items-center justify-between text-xs text-muted">
        <span className="font-semibold uppercase tracking-[0.2em]">Trend preview</span>
        <span>72h</span>
      </div>
      <svg viewBox="0 0 260 120" className="mt-3 h-[120px] w-full">
        <g
          stroke="rgb(var(--chart-grid))"
          strokeOpacity="var(--chart-grid-opacity)"
          strokeDasharray="4 4"
        >
          <line x1="0" y1="20" x2="260" y2="20" />
          <line x1="0" y1="60" x2="260" y2="60" />
          <line x1="0" y1="100" x2="260" y2="100" />
        </g>
        <g stroke="rgb(var(--chart-axis))" strokeWidth="1" strokeOpacity="var(--chart-axis-opacity)">
          <line x1="0" y1="100" x2="260" y2="100" />
          <line x1="0" y1="10" x2="0" y2="100" />
        </g>
        <path
          d={mainPath}
          fill="none"
          style={{ stroke: 'var(--chart-1)' }}
          strokeWidth="3"
          strokeLinecap="round"
          className="chart-animate"
        />
        <path
          d={secondaryPath}
          fill="none"
          style={{ stroke: 'var(--chart-3)' }}
          strokeWidth="2.5"
          strokeLinecap="round"
        />
        {[0, 60, 120, 180, 240].map((x) => (
          <circle key={x} cx={x} cy={60} r={markerSize} style={{ fill: 'var(--chart-2)' }} />
        ))}
      </svg>
    </div>
  )
}

function PresetCard({
  preset,
  active,
  onApply,
  onDuplicate,
  onRemove,
  removable,
}: {
  preset: { id: string; name: string; description: string; tokens: { colors: { primary: HslColor } } }
  active: boolean
  onApply: () => void
  onDuplicate: () => void
  onRemove: () => void
  removable: boolean
}) {
  return (
    <div className="rounded-[var(--radius-xl)] border border-line bg-surface p-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <span
            className="h-8 w-8 rounded-[var(--radius-lg)] border border-line"
            style={{ backgroundColor: formatHsl(preset.tokens.colors.primary) }}
          />
          <div>
            <p className="text-sm font-semibold text-ink">{preset.name}</p>
            <p className="text-xs text-muted">{preset.description}</p>
          </div>
        </div>
        {active ? <Badge variant="success">Active</Badge> : null}
      </div>
      <div className="mt-3 flex flex-wrap gap-2">
        <Button size="sm" onClick={onApply}>
          Activate
        </Button>
        <Button size="sm" variant="outline" onClick={onDuplicate}>
          Duplicate
        </Button>
        {removable ? (
          <Button size="sm" variant="ghost" onClick={onRemove}>
            Remove
          </Button>
        ) : null}
      </div>
    </div>
  )
}

function getColor(tokens: ThemeTokens, target: ColorTarget) {
  if (target === 'success' || target === 'warning' || target === 'error' || target === 'info') {
    return tokens.colors.semantic[target]
  }
  return tokens.colors[target] as HslColor
}

function shiftColor(color: HslColor, hueShift: number, saturationShift: number, lightShift: number) {
  return {
    h: (color.h + hueShift + 360) % 360,
    s: clamp(color.s + saturationShift, 6, 92),
    l: clamp(color.l + lightShift, 8, 88),
  }
}

function applyPaletteImport(
  raw: string,
  update: (recipe: (tokens: ThemeTokens) => ThemeTokens) => void
): { ok: boolean; error?: string; message?: string } {
  try {
    const parsed = JSON.parse(raw) as Record<string, unknown>

    update((current: ThemeTokens) => {
      const next = { ...current }
      const palette = (parsed.colors ?? parsed) as Record<string, unknown>
      const setHex = (key: ColorTarget, value: string) => {
        const hsl = hexToHsl(value)
        if (!hsl) return
        if (key === 'success' || key === 'warning' || key === 'error' || key === 'info') {
          next.colors = {
            ...next.colors,
            semantic: { ...next.colors.semantic, [key]: hsl },
          }
        } else {
          next.colors = { ...next.colors, [key]: hsl }
        }
      }

        ; (['primary', 'secondary', 'accent', 'success', 'warning', 'error', 'info'] as ColorTarget[]).forEach(
          (key) => {
            const value = palette[key]
            if (typeof value === 'string') {
              setHex(key, value)
            }
            if (typeof value === 'object' && value !== null && 'DEFAULT' in value && typeof value.DEFAULT === 'string') {
              setHex(key, value.DEFAULT)
            }
          }
        )

      const chart = palette.chart ?? palette.scheme
      if (Array.isArray(chart) && chart.every((item) => typeof item === 'string')) {
        next.dataViz = { ...next.dataViz, scheme: chart }
      }

      const neutral = palette.slate ?? palette.gray ?? palette.neutral
      const radixScale = Object.keys(palette)
        .filter((key) => /^(slate|gray|neutral)\\d+$/.test(key))
        .map((key) => palette[key])
      if (neutral || radixScale.length) {
        const values = Array.isArray(neutral) ? neutral : neutral ? Object.values(neutral) : radixScale
        const hsls = values
          .map((value) => (typeof value === 'string' ? hexToHsl(value) : null))
          .filter(Boolean) as HslColor[]

        if (hsls.length > 3) {
          const avgHue =
            hsls.reduce((sum, color) => sum + color.h, 0) / Math.max(1, hsls.length)
          const minL = Math.min(...hsls.map((color) => color.l))
          const maxL = Math.max(...hsls.map((color) => color.l))
          const warmth = clamp(((210 - avgHue) / 180) * 100, 0, 100)
          const density = clamp(((maxL - minL) / 80) * 100, 0, 100)
          next.colors = {
            ...next.colors,
            neutrals: {
              ...next.colors.neutrals,
              warmth,
              density,
            },
          }
        }
      }

      return next
    })

    return { ok: true, message: 'Palette applied to brand + neutrals.' }
  } catch (error) {
    return {
      ok: false,
      error: error instanceof Error ? error.message : 'Invalid JSON payload.',
    }
  }
}
