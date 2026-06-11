import * as React from 'react'
import {
  clamp,
  formatHsl,
  hexToHsl,
  hslToHex,
  hslToRgb,
  normalizeThemeTokens,
  type HslColor,
  type ThemeTokens,
} from './theme.tokens.ts'
import { DEFAULT_PRESET_ID, themePresets, type ThemePreset } from './theme.presets.ts'

const THEME_STORAGE_KEY = 'onesquad.appearance.theme'
const PRESET_STORAGE_KEY = 'onesquad.appearance.presets'
const PROFILE_STORAGE_KEY = 'onesquad.user.profile'

const NEUTRAL_KEYS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as const

type ThemeContextValue = {
  tokens: ThemeTokens
  presets: ThemePreset[]
  activePresetId: string | null
  canUndo: boolean
  setTokens: (tokens: ThemeTokens) => void
  updateTokens: (recipe: (tokens: ThemeTokens) => ThemeTokens) => void
  applyPreset: (presetId: string) => void
  reset: () => void
  undo: () => void
  exportTokens: () => string
  importTokens: (raw: string) => { ok: boolean; error?: string }
  savePreset: (name: string) => void
  duplicatePreset: (presetId: string, name?: string) => void
  removePreset: (presetId: string) => void
}

const ThemeContext = React.createContext<ThemeContextValue | undefined>(undefined)

type ThemeState = {
  tokens: ThemeTokens
  history: ThemeTokens[]
  historyIndex: number
  activePresetId: string | null
  customPresets: ThemePreset[]
}

type ThemeAction =
  | { type: 'SET_TOKENS'; tokens: ThemeTokens; activePresetId?: string | null }
  | { type: 'UPDATE_TOKENS'; recipe: (tokens: ThemeTokens) => ThemeTokens; activePresetId?: string | null }
  | { type: 'UNDO' }
  | { type: 'RESET' }
  | { type: 'SAVE_PRESET'; preset: ThemePreset }
  | { type: 'REMOVE_PRESET'; presetId: string }

const getDefaultPreset = () =>
  themePresets.find((preset) => preset.id === DEFAULT_PRESET_ID) ?? themePresets[0]

const buildNeutralScale = (warmth: number, density: number, mode: ThemeTokens['mode']) => {
  const hue = 210 - (clamp(warmth, 0, 100) / 100) * 180
  const saturationBase = 4 + (clamp(warmth, 0, 100) / 100) * 6
  const densityFactor = 0.7 + (clamp(density, 0, 100) / 100) * 0.8

  // Keep token names aligned with Tailwind expectations:
  // 50/100 stay close to white, while 400/500/600 remain readable for text.
  const baseLight = [98, 96, 92, 86, 66, 54, 42, 32, 24, 16, 10]
  const baseDark = [97, 94, 88, 80, 66, 54, 43, 33, 24, 16, 10]
  const base = mode === 'dark' ? baseDark : baseLight

  return base.map((l) => {
    const adjusted = clamp(50 + (l - 50) * densityFactor, 4, 98)
    const saturation = mode === 'dark' ? saturationBase * 0.7 : saturationBase
    return hslToRgb({ h: hue, s: saturation, l: clamp(adjusted, 4, 98) })
  })
}

const mixRgb = (a: { r: number; g: number; b: number }, b: { r: number; g: number; b: number }, t: number) => ({
  r: Math.round(a.r + (b.r - a.r) * t),
  g: Math.round(a.g + (b.g - a.g) * t),
  b: Math.round(a.b + (b.b - a.b) * t),
})

const toVar = (rgb: { r: number; g: number; b: number }) => `${rgb.r} ${rgb.g} ${rgb.b}`

const rgbaString = (rgb: { r: number; g: number; b: number }, alpha: number) =>
  `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`

const shiftColor = (color: HslColor, hueShift: number, saturationCurve: number): HslColor => ({
  h: (color.h + hueShift + 360) % 360,
  s: clamp(color.s * saturationCurve, 6, 96),
  l: clamp(color.l, 6, 92),
})

const contrastRatio = (a: { r: number; g: number; b: number }, b: { r: number; g: number; b: number }) => {
  const luminance = (rgb: { r: number; g: number; b: number }) => {
    const channel = (value: number) => {
      const v = value / 255
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)
    }
    return 0.2126 * channel(rgb.r) + 0.7152 * channel(rgb.g) + 0.0722 * channel(rgb.b)
  }

  const l1 = luminance(a)
  const l2 = luminance(b)
  const brightest = Math.max(l1, l2)
  const darkest = Math.min(l1, l2)
  return (brightest + 0.05) / (darkest + 0.05)
}

const pickReadable = (rgb: { r: number; g: number; b: number }) => {
  const white = { r: 255, g: 255, b: 255 }
  const black = { r: 15, g: 23, b: 42 }
  const whiteRatio = contrastRatio(rgb, white)
  const blackRatio = contrastRatio(rgb, black)
  return whiteRatio >= blackRatio ? white : black
}

const deriveTheme = (tokens: ThemeTokens) => {
  const hueShift = tokens.advanced.hueShift
  const saturationCurve = tokens.advanced.saturationCurve

  const primary = shiftColor(tokens.colors.primary, hueShift, saturationCurve)
  const secondary = shiftColor(tokens.colors.secondary, hueShift, saturationCurve)
  const accent = shiftColor(tokens.colors.accent, hueShift, saturationCurve)
  const success = shiftColor(tokens.colors.semantic.success, hueShift, saturationCurve)
  const warning = shiftColor(tokens.colors.semantic.warning, hueShift, saturationCurve)
  const error = shiftColor(tokens.colors.semantic.error, hueShift, saturationCurve)
  const info = shiftColor(tokens.colors.semantic.info, hueShift, saturationCurve)

  const neutrals = buildNeutralScale(tokens.colors.neutrals.warmth, tokens.colors.neutrals.density, tokens.mode)

  const neutralMap: Record<number, { r: number; g: number; b: number }> = {}
  NEUTRAL_KEYS.forEach((key, index) => {
    neutralMap[key] = neutrals[index]
  })

  const bg = tokens.mode === 'dark' ? neutralMap[950] : neutralMap[50]
  const ink = tokens.mode === 'dark' ? neutralMap[50] : neutralMap[900]
  const muted = tokens.mode === 'dark' ? neutralMap[500] : neutralMap[600]

  const elevationBoost = tokens.colors.neutrals.elevation / 100
  const surfaceTarget = tokens.mode === 'dark' ? neutralMap[900] : neutralMap[100]
  const surfaceBlend = clamp(tokens.surfaces.cardOpacity * (0.75 + elevationBoost * 0.4), 0.7, 1)
  const surface = mixRgb(bg, surfaceTarget, surfaceBlend)
  const surface2 = mixRgb(bg, surfaceTarget, clamp(surfaceBlend - 0.08, 0.6, 1))
  const surface3 = mixRgb(bg, surfaceTarget, clamp(surfaceBlend - 0.16, 0.5, 0.95))

  const lineStrengthBase = tokens.mode === 'dark' ? 0.22 : 0.12
  const lineStrength = lineStrengthBase + (tokens.advanced.contrastBoost ? 0.08 : 0)
  const line = mixRgb(bg, ink, clamp(lineStrength + tokens.surfaces.borderVsShadow * 0.08, 0.08, 0.3))

  const shadowTint = shiftColor(tokens.surfaces.shadowTint, hueShift, saturationCurve)
  const shadowRgb = hslToRgb(shadowTint)
  const shadowIntensity = 0.16 + (1 - tokens.surfaces.borderVsShadow) * 0.18
  const shadowSoftness = tokens.surfaces.shadowSoftness
  const shadowBlur = 18 + shadowSoftness * 30
  const shadowSpread = -12 - shadowSoftness * 10
  const shadowOffset = 8 + shadowSoftness * 10

  const shadowSubtle = `0 ${Math.round(shadowOffset)}px ${Math.round(
    shadowBlur
  )}px ${Math.round(shadowSpread)}px ${rgbaString(shadowRgb, shadowIntensity)}`
  const shadowPanel = `0 ${Math.round(shadowOffset + 6)}px ${Math.round(
    shadowBlur + 24
  )}px ${Math.round(shadowSpread - 8)}px ${rgbaString(shadowRgb, shadowIntensity + 0.12)}`

  const chartScheme = tokens.dataViz.scheme.length
    ? tokens.dataViz.scheme
    : ['#0f766e', '#14b8a6', '#0ea5e9', '#6366f1', '#f97316', '#ef4444']

  const primaryRgb = hslToRgb(primary)
  const secondaryRgb = hslToRgb(secondary)
  const accentRgb = hslToRgb(accent)
  const successRgb = hslToRgb(success)
  const warningRgb = hslToRgb(warning)
  const errorRgb = hslToRgb(error)
  const infoRgb = hslToRgb(info)

  const onBrand = pickReadable(primaryRgb)
  const onSecondary = pickReadable(secondaryRgb)
  const onAccent = pickReadable(accentRgb)

  const gradientAlpha = (tokens.background.gradientIntensity / 100) * 0.22
  const glowPrimary = `radial-gradient(circle at 15% 20%, ${rgbaString(primaryRgb, gradientAlpha)}, transparent 55%)`
  const glowAccent = `radial-gradient(circle at 80% 10%, ${rgbaString(accentRgb, gradientAlpha * 0.8)}, transparent 50%)`
  const glowInfo = `radial-gradient(circle at 40% 80%, ${rgbaString(infoRgb, gradientAlpha * 0.5)}, transparent 60%)`

  const noiseAlpha = (tokens.background.noiseIntensity / 100) * 0.18
  const noiseBase = tokens.mode === 'dark' ? '255, 255, 255' : '0, 0, 0'
  const noiseLayer = `repeating-linear-gradient(0deg, rgba(${noiseBase}, ${noiseAlpha}) 0 1px, transparent 1px 2px), repeating-linear-gradient(90deg, rgba(${noiseBase}, ${noiseAlpha}) 0 1px, transparent 1px 2px)`

  let backgroundImage = 'none'
  if (tokens.background.style === 'gradient') {
    backgroundImage = `${glowPrimary}, ${glowAccent}, ${glowInfo}`
  }
  if (tokens.background.style === 'noise') {
    backgroundImage = `${glowPrimary}, ${glowAccent}, ${glowInfo}, ${noiseLayer}`
  }

  const densityModeMultiplier =
    tokens.density.mode === 'compact' ? 0.85 : tokens.density.mode === 'spacious' ? 1.15 : 1
  const spacingScale = clamp(tokens.density.spacingScale * densityModeMultiplier, 0.75, 1.5)

  const cardPadding = Math.round(tokens.density.cardPadding * spacingScale)
  const tableRowHeight = Math.round(tokens.density.tableRowHeight * spacingScale)
  const tableHeaderHeight = Math.max(34, Math.round(tableRowHeight * 0.9))

  const controlHeight = Math.round(40 * spacingScale)
  const controlHeightSm = Math.round(32 * spacingScale)
  const controlHeightLg = Math.round(44 * spacingScale)
  const controlPaddingX = Math.round(16 * spacingScale)
  const controlPaddingY = Math.round(10 * spacingScale)
  const pagePadding = Math.round(24 * spacingScale)
  const pagePaddingY = Math.round(32 * spacingScale)
  const pagePaddingLg = Math.round(40 * spacingScale)

  const radiusScale = tokens.surfaces.radiusScale
  const radiusSm = Math.round(8 * radiusScale)
  const radiusMd = Math.round(12 * radiusScale)
  const radiusLg = Math.round(16 * radiusScale)
  const radiusXl = Math.round(20 * radiusScale)
  const radius2xl = Math.round(24 * radiusScale)

  return {
    backgroundImage,
    bg,
    ink,
    muted,
    primaryRgb,
    secondaryRgb,
    accentRgb,
    successRgb,
    warningRgb,
    errorRgb,
    infoRgb,
    onBrand,
    onSecondary,
    onAccent,
    surface,
    surface2,
    surface3,
    line,
    shadowSubtle,
    shadowPanel,
    chartScheme,
    neutralMap,
    spacing: {
      cardPadding,
      tableRowHeight,
      tableHeaderHeight,
      controlHeight,
      controlHeightSm,
      controlHeightLg,
      controlPaddingX,
      controlPaddingY,
      pagePadding,
      pagePaddingY,
      pagePaddingLg,
      spacingScale,
      radiusSm,
      radiusMd,
      radiusLg,
      radiusXl,
      radius2xl,
    },
  }
}

const applyThemeToDocument = (tokens: ThemeTokens) => {
  if (typeof document === 'undefined') return

  const root = document.documentElement
  const body = document.body
  const derived = deriveTheme(tokens)

  root.style.setProperty('--bg', toVar(derived.bg))
  root.style.setProperty('--ink', toVar(derived.ink))
  root.style.setProperty('--muted', toVar(derived.muted))
  root.style.setProperty('--brand', toVar(derived.primaryRgb))
  root.style.setProperty('--secondary', toVar(derived.secondaryRgb))
  root.style.setProperty('--accent', toVar(derived.accentRgb))
  root.style.setProperty('--success', toVar(derived.successRgb))
  root.style.setProperty('--warning', toVar(derived.warningRgb))
  root.style.setProperty('--error', toVar(derived.errorRgb))
  root.style.setProperty('--info', toVar(derived.infoRgb))
  root.style.setProperty('--on-brand', toVar(derived.onBrand))
  root.style.setProperty('--on-secondary', toVar(derived.onSecondary))
  root.style.setProperty('--on-accent', toVar(derived.onAccent))
  root.style.setProperty('--surface', toVar(derived.surface))
  root.style.setProperty('--surface-2', toVar(derived.surface2))
  root.style.setProperty('--surface-3', toVar(derived.surface3))
  root.style.setProperty('--line', toVar(derived.line))
  root.style.setProperty('--focus', toVar(derived.accentRgb))
  root.style.setProperty('--link', toVar(derived.accentRgb))
  root.style.setProperty('--shadow-subtle', derived.shadowSubtle)
  root.style.setProperty('--shadow-panel', derived.shadowPanel)
  root.style.setProperty('--font-body', `'${tokens.typography.body}', ui-sans-serif, system-ui`)
  root.style.setProperty('--font-heading', `'${tokens.typography.heading}', ui-sans-serif, system-ui`)
  root.style.setProperty('--font-mono', `'${tokens.typography.mono}', ui-monospace, SFMono-Regular`)
  root.style.setProperty('--font-size-base', `${tokens.typography.baseSize}px`)
  root.style.setProperty('--line-height-base', `${tokens.typography.lineHeight}`)
  root.style.setProperty('--letter-spacing', `${tokens.typography.letterSpacing}px`)
  root.style.setProperty('--numeric-variant',
    tokens.typography.numeric === 'tabular' ? 'tabular-nums' : 'normal')
  root.style.setProperty('--card-padding', `${derived.spacing.cardPadding}px`)
  root.style.setProperty('--table-row-height', `${derived.spacing.tableRowHeight}px`)
  root.style.setProperty('--table-header-height', `${derived.spacing.tableHeaderHeight}px`)
  root.style.setProperty('--control-height', `${derived.spacing.controlHeight}px`)
  root.style.setProperty('--control-height-sm', `${derived.spacing.controlHeightSm}px`)
  root.style.setProperty('--control-height-lg', `${derived.spacing.controlHeightLg}px`)
  root.style.setProperty('--control-padding-x', `${derived.spacing.controlPaddingX}px`)
  root.style.setProperty('--control-padding-y', `${derived.spacing.controlPaddingY}px`)
  root.style.setProperty('--spacing-scale', `${derived.spacing.spacingScale}`)
  root.style.setProperty('--page-padding', `${derived.spacing.pagePadding}px`)
  root.style.setProperty('--page-padding-y', `${derived.spacing.pagePaddingY}px`)
  root.style.setProperty('--page-padding-lg', `${derived.spacing.pagePaddingLg}px`)
  root.style.setProperty('--radius-sm', `${derived.spacing.radiusSm}px`)
  root.style.setProperty('--radius-md', `${derived.spacing.radiusMd}px`)
  root.style.setProperty('--radius-lg', `${derived.spacing.radiusLg}px`)
  root.style.setProperty('--radius-xl', `${derived.spacing.radiusXl}px`)
  root.style.setProperty('--radius-2xl', `${derived.spacing.radius2xl}px`)
  root.style.setProperty('--radius-full', `999px`)

  NEUTRAL_KEYS.forEach((key) => {
    root.style.setProperty(`--neutral-${key}`, toVar(derived.neutralMap[key]))
  })

  derived.chartScheme.forEach((color, index) => {
    root.style.setProperty(`--chart-${index + 1}`, color)
  })

  root.style.setProperty('--chart-grid', toVar(derived.line))
  root.style.setProperty('--chart-axis', toVar(derived.muted))
  root.style.setProperty('--chart-smoothness', `${tokens.dataViz.curveSmoothness}`)
  root.style.setProperty('--chart-marker', `${tokens.dataViz.markerSize}`)
  root.style.setProperty('--chart-animate', `${tokens.dataViz.animationIntensity}`)
  root.style.setProperty('--chart-grid-opacity', `${tokens.dataViz.gridVisibility / 100}`)
  root.style.setProperty('--chart-axis-opacity', `${tokens.dataViz.axisContrast / 100}`)
  const animDuration = clamp(10 - tokens.dataViz.animationIntensity * 0.08, 2, 10)
  const animOpacity = clamp(0.6 + tokens.dataViz.animationIntensity / 250, 0.6, 1)
  root.style.setProperty('--chart-anim-duration', `${animDuration}s`)
  root.style.setProperty('--chart-anim-opacity', `${animOpacity}`)
  root.style.setProperty('--ambient-blob', tokens.background.style === 'flat' ? '0' : '1')
  root.style.setProperty('--app-background-image', derived.backgroundImage)

  body.style.backgroundColor = `rgb(${toVar(derived.bg)})`
  body.style.backgroundImage = derived.backgroundImage
  body.style.backgroundAttachment = 'fixed'
  body.style.backgroundSize = 'cover'
  body.style.fontVariantNumeric = tokens.typography.numeric === 'tabular' ? 'tabular-nums' : 'normal'
  root.style.colorScheme = tokens.mode
}

const loadStoredTheme = () => {
  if (typeof window === 'undefined') {
    return getDefaultPreset().tokens
  }

  const stored = window.localStorage.getItem(THEME_STORAGE_KEY)
  if (!stored) {
    return getDefaultPreset().tokens
  }

  try {
    const parsed = JSON.parse(stored) as ThemeTokens
    return normalizeThemeTokens(parsed)
  } catch {
    return getDefaultPreset().tokens
  }
}

const loadStoredPresets = (): ThemePreset[] => {
  if (typeof window === 'undefined') {
    return []
  }

  const stored = window.localStorage.getItem(PRESET_STORAGE_KEY)
  if (!stored) {
    return []
  }

  try {
    const parsed = JSON.parse(stored) as ThemePreset[]
    return parsed.map((preset) => ({
      ...preset,
      tokens: normalizeThemeTokens(preset.tokens),
    }))
  } catch {
    return []
  }
}

const saveProfileTheme = (tokens: ThemeTokens) => {
  if (typeof window === 'undefined') return

  try {
    const stored = window.localStorage.getItem(PROFILE_STORAGE_KEY)
    const profile = stored ? JSON.parse(stored) : {}
    window.localStorage.setItem(
      PROFILE_STORAGE_KEY,
      JSON.stringify({ ...profile, appearanceTheme: tokens })
    )
  } catch {
    // Ignore storage errors in demo.
  }
}

const themeReducer = (state: ThemeState, action: ThemeAction): ThemeState => {
  switch (action.type) {
    case 'SET_TOKENS': {
      return {
        ...state,
        tokens: normalizeThemeTokens(action.tokens),
        activePresetId: action.activePresetId ?? null,
      }
    }
    case 'UPDATE_TOKENS': {
      const nextTokens = normalizeThemeTokens(action.recipe(state.tokens))
      if (JSON.stringify(nextTokens) === JSON.stringify(state.tokens)) {
        return state
      }
      const nextHistory = [...state.history.slice(0, state.historyIndex + 1), state.tokens]
      return {
        ...state,
        tokens: nextTokens,
        history: nextHistory,
        historyIndex: nextHistory.length - 1,
        activePresetId: action.activePresetId ?? null,
      }
    }
    case 'UNDO': {
      if (state.historyIndex < 0) {
        return state
      }
      const previousTokens = state.history[state.historyIndex]
      return {
        ...state,
        tokens: previousTokens,
        historyIndex: state.historyIndex - 1,
        activePresetId: null,
      }
    }
    case 'RESET': {
      const preset = getDefaultPreset()
      const nextHistory = [...state.history.slice(0, state.historyIndex + 1), state.tokens]
      return {
        ...state,
        tokens: preset.tokens,
        history: nextHistory,
        historyIndex: nextHistory.length - 1,
        activePresetId: preset.id,
      }
    }
    case 'SAVE_PRESET': {
      return { ...state, customPresets: [...state.customPresets, action.preset] }
    }
    case 'REMOVE_PRESET': {
      return {
        ...state,
        customPresets: state.customPresets.filter((preset) => preset.id !== action.presetId),
      }
    }
    default:
      return state
  }
}

export function ThemeProvider({ children }: { children: React.ReactNode }) {
  const [state, dispatch] = React.useReducer(themeReducer, undefined, () => ({
    tokens: normalizeThemeTokens(loadStoredTheme()),
    history: [],
    historyIndex: -1,
    activePresetId: DEFAULT_PRESET_ID,
    customPresets: loadStoredPresets(),
  }))

  const presets = React.useMemo(
    () => [...themePresets, ...state.customPresets],
    [state.customPresets]
  )

  React.useEffect(() => {
    applyThemeToDocument(state.tokens)

    if (typeof window !== 'undefined') {
      window.localStorage.setItem(THEME_STORAGE_KEY, JSON.stringify(state.tokens))
      window.localStorage.setItem(PRESET_STORAGE_KEY, JSON.stringify(state.customPresets))
      saveProfileTheme(state.tokens)
    }
  }, [state.tokens, state.customPresets])

  const setTokens = React.useCallback(
    (tokens: ThemeTokens) => dispatch({ type: 'SET_TOKENS', tokens }),
    []
  )

  const updateTokens = React.useCallback(
    (recipe: (tokens: ThemeTokens) => ThemeTokens) => dispatch({ type: 'UPDATE_TOKENS', recipe }),
    []
  )

  const applyPreset = React.useCallback(
    (presetId: string) => {
      const preset = presets.find((candidate) => candidate.id === presetId)
      if (!preset) return
      dispatch({
        type: 'UPDATE_TOKENS',
        recipe: () => preset.tokens,
        activePresetId: preset.id,
      })
    },
    [presets]
  )

  const reset = React.useCallback(() => dispatch({ type: 'RESET' }), [])
  const undo = React.useCallback(() => dispatch({ type: 'UNDO' }), [])

  const savePreset = React.useCallback(
    (name: string) => {
      const id = `custom-${name.toLowerCase().replace(/\s+/g, '-')}-${Date.now()}`
      const preset: ThemePreset = {
        id,
        name,
        description: `Custom preset created ${new Date().toLocaleDateString()}.`,
        tokens: state.tokens,
      }
      dispatch({ type: 'SAVE_PRESET', preset })
    },
    [state.tokens]
  )

  const duplicatePreset = React.useCallback(
    (presetId: string, name?: string) => {
      const preset = presets.find((candidate) => candidate.id === presetId)
      if (!preset) return
      const id = `custom-${preset.name.toLowerCase().replace(/\s+/g, '-')}-${Date.now()}`
      const duplicate: ThemePreset = {
        ...preset,
        id,
        name: name ?? `${preset.name} Copy`,
      }
      dispatch({ type: 'SAVE_PRESET', preset: duplicate })
    },
    [presets]
  )

  const removePreset = React.useCallback((presetId: string) => {
    dispatch({ type: 'REMOVE_PRESET', presetId })
  }, [])

  const exportTokens = React.useCallback(() => JSON.stringify(state.tokens, null, 2), [state.tokens])

  const importTokens = React.useCallback((raw: string) => {
    try {
      const parsed = JSON.parse(raw) as ThemeTokens
      dispatch({
        type: 'UPDATE_TOKENS',
        recipe: () => normalizeThemeTokens(parsed),
        activePresetId: null,
      })
      return { ok: true }
    } catch (error) {
      return { ok: false, error: error instanceof Error ? error.message : 'Invalid JSON' }
    }
  }, [])

  const value: ThemeContextValue = {
    tokens: state.tokens,
    presets,
    activePresetId: state.activePresetId,
    canUndo: state.historyIndex >= 0,
    setTokens,
    updateTokens,
    applyPreset,
    reset,
    undo,
    exportTokens,
    importTokens,
    savePreset,
    duplicatePreset,
    removePreset,
  }

  return React.createElement(ThemeContext.Provider, { value }, children)
}

export function useTheme() {
  const context = React.useContext(ThemeContext)
  if (!context) {
    throw new Error('useTheme must be used within ThemeProvider')
  }
  return context
}

export const themeColorUtils = {
  formatHsl,
  hslToHex,
  hexToHsl,
}
