export type HslColor = {
  h: number
  s: number
  l: number
}

export type ThemeMode = 'light' | 'dark'
export type BackgroundStyle = 'flat' | 'gradient' | 'noise'
export type DensityMode = 'compact' | 'comfortable' | 'spacious'
export type NumericStyle = 'tabular' | 'proportional'

export type ThemeTokens = {
  meta: {
    name: string
    description?: string
    version?: number
  }
  mode: ThemeMode
  colors: {
    primary: HslColor
    secondary: HslColor
    accent: HslColor
    semantic: {
      success: HslColor
      warning: HslColor
      error: HslColor
      info: HslColor
    }
    neutrals: {
      warmth: number
      density: number
      elevation: number
    }
  }
  background: {
    style: BackgroundStyle
    gradientIntensity: number
    noiseIntensity: number
  }
  surfaces: {
    cardOpacity: number
    borderVsShadow: number
    shadowSoftness: number
    shadowTint: HslColor
    radiusScale: number
  }
  typography: {
    body: string
    heading: string
    mono: string
    baseSize: number
    lineHeight: number
    letterSpacing: number
    numeric: NumericStyle
  }
  density: {
    mode: DensityMode
    spacingScale: number
    tableRowHeight: number
    cardPadding: number
  }
  dataViz: {
    scheme: string[]
    gridVisibility: number
    axisContrast: number
    curveSmoothness: number
    markerSize: number
    animationIntensity: number
    preset: 'scientific' | 'live-ops' | 'executive' | 'dark-lab'
  }
  palette: {
    locks: Record<string, boolean>
  }
  advanced: {
    hueShift: number
    saturationCurve: number
    contrastBoost: boolean
  }
}

export const defaultThemeTokens: ThemeTokens = {
  meta: {
    name: 'Enterprise Serious',
    description: 'Structured, confident, enterprise-ready.',
    version: 1,
  },
  mode: 'light',
  colors: {
    primary: { h: 189, s: 56, l: 32 },
    secondary: { h: 219, s: 28, l: 18 },
    accent: { h: 163, s: 68, l: 36 },
    semantic: {
      success: { h: 150, s: 58, l: 34 },
      warning: { h: 34, s: 88, l: 47 },
      error: { h: 354, s: 72, l: 48 },
      info: { h: 210, s: 66, l: 46 },
    },
    neutrals: {
      warmth: 32,
      density: 52,
      elevation: 56,
    },
  },
  background: {
    style: 'gradient',
    gradientIntensity: 58,
    noiseIntensity: 12,
  },
  surfaces: {
    cardOpacity: 0.96,
    borderVsShadow: 0.52,
    shadowSoftness: 0.5,
    shadowTint: { h: 222, s: 18, l: 18 },
    radiusScale: 1,
  },
  typography: {
    body: 'IBM Plex Sans',
    heading: 'Space Grotesk',
    mono: 'JetBrains Mono',
    baseSize: 15,
    lineHeight: 1.5,
    letterSpacing: 0,
    numeric: 'tabular',
  },
  density: {
    mode: 'comfortable',
    spacingScale: 1,
    tableRowHeight: 44,
    cardPadding: 24,
  },
  dataViz: {
    scheme: ['#2b8c8a', '#0f766e', '#38bdf8', '#6366f1', '#f97316', '#ef4444'],
    gridVisibility: 55,
    axisContrast: 62,
    curveSmoothness: 40,
    markerSize: 4,
    animationIntensity: 45,
    preset: 'executive',
  },
  palette: {
    locks: {
      primary: false,
      secondary: false,
      accent: false,
      success: false,
      warning: false,
      error: false,
      info: false,
    },
  },
  advanced: {
    hueShift: 0,
    saturationCurve: 1,
    contrastBoost: false,
  },
}

export function clamp(value: number, min: number, max: number) {
  return Math.min(max, Math.max(min, value))
}

export function formatHsl(color: HslColor) {
  return `hsl(${Math.round(color.h)} ${Math.round(color.s)}% ${Math.round(color.l)}%)`
}

export function hslToRgb(color: HslColor) {
  const h = clamp(color.h, 0, 360) / 360
  const s = clamp(color.s, 0, 100) / 100
  const l = clamp(color.l, 0, 100) / 100

  if (s === 0) {
    const gray = Math.round(l * 255)
    return { r: gray, g: gray, b: gray }
  }

  const hue2rgb = (p: number, q: number, t: number) => {
    let tt = t
    if (tt < 0) tt += 1
    if (tt > 1) tt -= 1
    if (tt < 1 / 6) return p + (q - p) * 6 * tt
    if (tt < 1 / 2) return q
    if (tt < 2 / 3) return p + (q - p) * (2 / 3 - tt) * 6
    return p
  }

  const q = l < 0.5 ? l * (1 + s) : l + s - l * s
  const p = 2 * l - q

  const r = Math.round(hue2rgb(p, q, h + 1 / 3) * 255)
  const g = Math.round(hue2rgb(p, q, h) * 255)
  const b = Math.round(hue2rgb(p, q, h - 1 / 3) * 255)

  return { r, g, b }
}

export function rgbToHex(rgb: { r: number; g: number; b: number }) {
  const toHex = (value: number) => clamp(Math.round(value), 0, 255).toString(16).padStart(2, '0')
  return `#${toHex(rgb.r)}${toHex(rgb.g)}${toHex(rgb.b)}`
}

export function hslToHex(color: HslColor) {
  return rgbToHex(hslToRgb(color))
}

export function hexToHsl(hex: string): HslColor | null {
  const normalized = hex.replace('#', '').trim()
  if (![3, 6].includes(normalized.length)) {
    return null
  }

  const value =
    normalized.length === 3
      ? normalized
          .split('')
          .map((char) => char + char)
          .join('')
      : normalized

  const int = Number.parseInt(value, 16)
  if (Number.isNaN(int)) {
    return null
  }

  const r = (int >> 16) & 255
  const g = (int >> 8) & 255
  const b = int & 255

  const rNorm = r / 255
  const gNorm = g / 255
  const bNorm = b / 255

  const max = Math.max(rNorm, gNorm, bNorm)
  const min = Math.min(rNorm, gNorm, bNorm)
  let h = 0
  let s = 0
  const l = (max + min) / 2

  if (max !== min) {
    const d = max - min
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min)

    switch (max) {
      case rNorm:
        h = (gNorm - bNorm) / d + (gNorm < bNorm ? 6 : 0)
        break
      case gNorm:
        h = (bNorm - rNorm) / d + 2
        break
      default:
        h = (rNorm - gNorm) / d + 4
        break
    }

    h /= 6
  }

  return {
    h: Math.round(h * 360),
    s: Math.round(s * 100),
    l: Math.round(l * 100),
  }
}

export function normalizeThemeTokens(partial?: Partial<ThemeTokens>): ThemeTokens {
  if (!partial) {
    return structuredClone(defaultThemeTokens)
  }

  const merged: ThemeTokens = {
    ...defaultThemeTokens,
    ...partial,
    meta: { ...defaultThemeTokens.meta, ...partial.meta },
    colors: {
      ...defaultThemeTokens.colors,
      ...partial.colors,
      semantic: {
        ...defaultThemeTokens.colors.semantic,
        ...partial.colors?.semantic,
      },
      neutrals: {
        ...defaultThemeTokens.colors.neutrals,
        ...partial.colors?.neutrals,
      },
    },
    background: {
      ...defaultThemeTokens.background,
      ...partial.background,
    },
    surfaces: {
      ...defaultThemeTokens.surfaces,
      ...partial.surfaces,
    },
    typography: {
      ...defaultThemeTokens.typography,
      ...partial.typography,
    },
    density: {
      ...defaultThemeTokens.density,
      ...partial.density,
    },
    dataViz: {
      ...defaultThemeTokens.dataViz,
      ...partial.dataViz,
    },
    palette: {
      ...defaultThemeTokens.palette,
      ...partial.palette,
      locks: {
        ...defaultThemeTokens.palette.locks,
        ...partial.palette?.locks,
      },
    },
    advanced: {
      ...defaultThemeTokens.advanced,
      ...partial.advanced,
    },
  }

  merged.colors.neutrals.warmth = clamp(merged.colors.neutrals.warmth, 0, 100)
  merged.colors.neutrals.density = clamp(merged.colors.neutrals.density, 0, 100)
  merged.colors.neutrals.elevation = clamp(merged.colors.neutrals.elevation, 0, 100)
  merged.background.gradientIntensity = clamp(merged.background.gradientIntensity, 0, 100)
  merged.background.noiseIntensity = clamp(merged.background.noiseIntensity, 0, 100)
  merged.surfaces.cardOpacity = clamp(merged.surfaces.cardOpacity, 0.75, 1)
  merged.surfaces.borderVsShadow = clamp(merged.surfaces.borderVsShadow, 0, 1)
  merged.surfaces.shadowSoftness = clamp(merged.surfaces.shadowSoftness, 0, 1)
  merged.surfaces.radiusScale = clamp(merged.surfaces.radiusScale, 0.7, 1.4)
  merged.typography.baseSize = clamp(merged.typography.baseSize, 13, 18)
  merged.typography.lineHeight = clamp(merged.typography.lineHeight, 1.2, 1.8)
  merged.typography.letterSpacing = clamp(merged.typography.letterSpacing, -0.5, 1)
  merged.density.spacingScale = clamp(merged.density.spacingScale, 0.8, 1.4)
  merged.density.tableRowHeight = clamp(merged.density.tableRowHeight, 36, 56)
  merged.density.cardPadding = clamp(merged.density.cardPadding, 16, 32)
  merged.dataViz.gridVisibility = clamp(merged.dataViz.gridVisibility, 0, 100)
  merged.dataViz.axisContrast = clamp(merged.dataViz.axisContrast, 0, 100)
  merged.dataViz.curveSmoothness = clamp(merged.dataViz.curveSmoothness, 0, 100)
  merged.dataViz.markerSize = clamp(merged.dataViz.markerSize, 2, 8)
  merged.dataViz.animationIntensity = clamp(merged.dataViz.animationIntensity, 0, 100)
  merged.advanced.hueShift = clamp(merged.advanced.hueShift, -90, 90)
  merged.advanced.saturationCurve = clamp(merged.advanced.saturationCurve, 0.6, 1.4)

  return merged
}
