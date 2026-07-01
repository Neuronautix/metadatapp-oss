import { defaultThemeTokens, type ThemeTokens } from './theme.tokens.ts'

export type ThemePreset = {
  id: string
  name: string
  description: string
  tokens: ThemeTokens
}

const base = defaultThemeTokens

const withTokens = (override: Partial<ThemeTokens>): ThemeTokens => ({
  ...base,
  ...override,
  meta: { ...base.meta, ...override.meta },
  colors: {
    ...base.colors,
    ...override.colors,
    semantic: {
      ...base.colors.semantic,
      ...override.colors?.semantic,
    },
    neutrals: {
      ...base.colors.neutrals,
      ...override.colors?.neutrals,
    },
  },
  background: {
    ...base.background,
    ...override.background,
  },
  surfaces: {
    ...base.surfaces,
    ...override.surfaces,
  },
  typography: {
    ...base.typography,
    ...override.typography,
  },
  density: {
    ...base.density,
    ...override.density,
  },
  dataViz: {
    ...base.dataViz,
    ...override.dataViz,
  },
  palette: {
    ...base.palette,
    ...override.palette,
  },
  advanced: {
    ...base.advanced,
    ...override.advanced,
  },
})

export const themePresets: ThemePreset[] = [
  {
    id: 'clinical-minimal',
    name: 'Clinical Minimal',
    description: 'Cool neutrality, zero clutter, maximum legibility.',
    tokens: withTokens({
      meta: {
        name: 'Clinical Minimal',
        description: 'Cool neutrality, zero clutter, maximum legibility.',
        version: 1,
      },
      colors: {
        primary: { h: 210, s: 26, l: 24 },
        secondary: { h: 220, s: 18, l: 16 },
        accent: { h: 172, s: 32, l: 30 },
        semantic: {
          success: { h: 160, s: 46, l: 32 },
          warning: { h: 38, s: 74, l: 44 },
          error: { h: 352, s: 58, l: 46 },
          info: { h: 206, s: 52, l: 44 },
        },
        neutrals: {
          warmth: 12,
          density: 64,
          elevation: 62,
        },
      },
      background: {
        style: 'flat',
        gradientIntensity: 12,
        noiseIntensity: 4,
      },
      typography: {
        body: 'Source Sans 3',
        heading: 'Space Grotesk',
        mono: 'JetBrains Mono',
        baseSize: 15,
        lineHeight: 1.5,
        letterSpacing: 0.2,
        numeric: 'tabular',
      },
      dataViz: {
        scheme: ['#243b53', '#1d4ed8', '#0ea5e9', '#22c55e', '#f97316', '#e11d48'],
        gridVisibility: 62,
        axisContrast: 70,
        curveSmoothness: 22,
        markerSize: 3,
        animationIntensity: 30,
        preset: 'scientific',
      },
    }),
  },
  {
    id: 'dark-lab',
    name: 'Dark Lab',
    description: 'Silent, focused, luminous on graphite.',
    tokens: withTokens({
      meta: {
        name: 'Dark Lab',
        description: 'Silent, focused, luminous on graphite.',
      },
      mode: 'dark',
      colors: {
        primary: { h: 190, s: 56, l: 44 },
        secondary: { h: 210, s: 22, l: 18 },
        accent: { h: 168, s: 72, l: 44 },
        semantic: {
          success: { h: 160, s: 60, l: 42 },
          warning: { h: 28, s: 82, l: 52 },
          error: { h: 354, s: 68, l: 54 },
          info: { h: 210, s: 62, l: 50 },
        },
        neutrals: {
          warmth: 8,
          density: 68,
          elevation: 72,
        },
      },
      background: {
        style: 'noise',
        gradientIntensity: 42,
        noiseIntensity: 20,
      },
      surfaces: {
        cardOpacity: 0.92,
        borderVsShadow: 0.35,
        shadowSoftness: 0.7,
        shadowTint: { h: 210, s: 30, l: 12 },
        radiusScale: 1,
      },
      typography: {
        body: 'IBM Plex Sans',
        heading: 'Sora',
        mono: 'JetBrains Mono',
        baseSize: 15,
        lineHeight: 1.5,
        letterSpacing: 0.1,
        numeric: 'tabular',
      },
      dataViz: {
        scheme: ['#22d3ee', '#38bdf8', '#0ea5e9', '#6366f1', '#f97316', '#f43f5e'],
        gridVisibility: 35,
        axisContrast: 58,
        curveSmoothness: 60,
        markerSize: 4,
        animationIntensity: 40,
        preset: 'dark-lab',
      },
    }),
  },
  {
    id: 'open-science',
    name: 'Open Science',
    description: 'Bright, optimistic, and collaborative.',
    tokens: withTokens({
      meta: {
        name: 'Open Science',
        description: 'Bright, optimistic, and collaborative.',
      },
      colors: {
        primary: { h: 192, s: 64, l: 38 },
        secondary: { h: 230, s: 28, l: 24 },
        accent: { h: 38, s: 86, l: 52 },
        semantic: {
          success: { h: 155, s: 62, l: 36 },
          warning: { h: 40, s: 90, l: 54 },
          error: { h: 357, s: 70, l: 52 },
          info: { h: 204, s: 68, l: 50 },
        },
        neutrals: {
          warmth: 48,
          density: 50,
          elevation: 58,
        },
      },
      background: {
        style: 'gradient',
        gradientIntensity: 70,
        noiseIntensity: 8,
      },
      typography: {
        body: 'Manrope',
        heading: 'Space Grotesk',
        mono: 'JetBrains Mono',
        baseSize: 16,
        lineHeight: 1.55,
        letterSpacing: 0,
        numeric: 'tabular',
      },
      dataViz: {
        scheme: ['#0ea5e9', '#22c55e', '#f97316', '#facc15', '#6366f1', '#ec4899'],
        gridVisibility: 50,
        axisContrast: 58,
        curveSmoothness: 45,
        markerSize: 4,
        animationIntensity: 55,
        preset: 'live-ops',
      },
    }),
  },
  {
    id: 'enterprise-serious',
    name: 'Enterprise Serious',
    description: 'Structured confidence with subtle energy.',
    tokens: withTokens({
      meta: {
        name: 'Enterprise Serious',
        description: 'Structured confidence with subtle energy.',
      },
      dataViz: {
        scheme: ['#0f766e', '#14b8a6', '#0ea5e9', '#6366f1', '#f97316', '#ef4444'],
        gridVisibility: 55,
        axisContrast: 62,
        curveSmoothness: 40,
        markerSize: 4,
        animationIntensity: 45,
        preset: 'executive',
      },
    }),
  },
  {
    id: 'hacker-mode',
    name: 'Hacker Mode',
    description: 'Neon terminals, stealth instrumentation.',
    tokens: withTokens({
      meta: {
        name: 'Hacker Mode',
        description: 'Neon terminals, stealth instrumentation.',
      },
      mode: 'dark',
      colors: {
        primary: { h: 125, s: 78, l: 40 },
        secondary: { h: 210, s: 18, l: 18 },
        accent: { h: 175, s: 82, l: 42 },
        semantic: {
          success: { h: 125, s: 72, l: 38 },
          warning: { h: 42, s: 90, l: 52 },
          error: { h: 0, s: 72, l: 52 },
          info: { h: 195, s: 70, l: 48 },
        },
        neutrals: {
          warmth: 18,
          density: 72,
          elevation: 68,
        },
      },
      background: {
        style: 'noise',
        gradientIntensity: 38,
        noiseIntensity: 24,
      },
      surfaces: {
        cardOpacity: 0.9,
        borderVsShadow: 0.3,
        shadowSoftness: 0.8,
        shadowTint: { h: 140, s: 24, l: 10 },
        radiusScale: 0.9,
      },
      typography: {
        body: 'IBM Plex Mono',
        heading: 'Sora',
        mono: 'IBM Plex Mono',
        baseSize: 15,
        lineHeight: 1.55,
        letterSpacing: 0.3,
        numeric: 'tabular',
      },
      dataViz: {
        scheme: ['#22c55e', '#4ade80', '#34d399', '#22d3ee', '#f97316', '#f43f5e'],
        gridVisibility: 30,
        axisContrast: 55,
        curveSmoothness: 70,
        markerSize: 5,
        animationIntensity: 35,
        preset: 'dark-lab',
      },
    }),
  },
]

export const DEFAULT_PRESET_ID = 'enterprise-serious'
