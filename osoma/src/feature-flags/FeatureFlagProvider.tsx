import React from 'react'
import { featureFlagRegistry } from './flags.ts'
import type { FeatureFlagCategory, FeatureFlagKey, FeatureFlagRecord } from './featureFlag.types.ts'
import { featureFlagPresets, type FeatureFlagPresetKey } from './presets.ts'
import { apiFetch } from '@/lib/api.ts'

export interface FeatureFlagOverridePayload {
  key: FeatureFlagKey
  enabled: boolean
  updatedAt: string
  updatedBy: string
}

const LOCAL_STORAGE_KEY = 'stitch.flags.overrides'
const FEATURE_FLAG_OVERRIDES_PATH = '/feature-flags/overrides'
const FEATURE_FLAG_PRESETS_PATH = '/feature-flags/presets'

const readLocalOverrides = (): FeatureFlagOverridePayload[] => {
  if (typeof window === 'undefined') return []
  try {
    const raw = window.localStorage.getItem(LOCAL_STORAGE_KEY)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []
    return parsed.filter(
      (entry) => entry && typeof entry.key === 'string' && entry.key in featureFlagRegistry
    )
  } catch (error) {
    console.error('Unable to read feature flag overrides from localStorage', error)
    return []
  }
}

const serializeOverrides = (flags: FeatureFlagRecord): FeatureFlagOverridePayload[] =>
  Object.values(flags).map(({ key, enabled, updatedAt, updatedBy }) => ({
    key,
    enabled,
    updatedAt,
    updatedBy,
  }))

const persistOverrides = (flags: FeatureFlagRecord) => {
  if (typeof window === 'undefined') return
  try {
    const serialized = JSON.stringify(serializeOverrides(flags))
    window.localStorage.setItem(LOCAL_STORAGE_KEY, serialized)
  } catch (error) {
    console.error('Unable to persist feature flag overrides locally', error)
  }
}

const applyOverridesToFlags = (
  base: FeatureFlagRecord,
  overrides: FeatureFlagOverridePayload[] | undefined
): FeatureFlagRecord => {
  if (!overrides || overrides.length === 0) {
    return base
  }

  const updated = { ...base }
  overrides.forEach((override) => {
    const existing = updated[override.key]
    if (!existing) return
    updated[override.key] = {
      ...existing,
      enabled: override.enabled,
      updatedAt: override.updatedAt,
      updatedBy: override.updatedBy,
    }
  })

  return updated
}

type FeatureFlagTone = (typeof featureFlagPresets)[keyof typeof featureFlagPresets]['tone']

interface FeatureFlagContextValue {
  flags: FeatureFlagRecord
  isInitialized: boolean
  isEnabled: (key: FeatureFlagKey) => boolean
  getFlag: (key: FeatureFlagKey) => FeatureFlagRecord[FeatureFlagKey]
  setFlag: (key: FeatureFlagKey, enabled: boolean) => Promise<void>
  setCategoryFlags: (category: FeatureFlagCategory, enabled: boolean) => Promise<void>
  applyPreset: (preset: FeatureFlagPresetKey) => Promise<void>
  resetToDefaults: () => Promise<void>
  activePreset: FeatureFlagPresetKey | null
  /** True when the active preset is enforced by the user's account (server-side). Cannot be overridden by the Flag Studio. */
  isPresetEnforced: boolean
  presets: typeof featureFlagPresets
  tone: FeatureFlagTone
}

const FeatureFlagContext = React.createContext<FeatureFlagContextValue | undefined>(undefined)

export function FeatureFlagProvider({ children }: { children: React.ReactNode }) {
  const [flags, setFlags] = React.useState<FeatureFlagRecord>(() =>
    applyOverridesToFlags(featureFlagRegistry, readLocalOverrides())
  )
  const [isInitialized, setIsInitialized] = React.useState(false)
  const [activePreset, setActivePreset] = React.useState<FeatureFlagPresetKey | null>(null)
  const [isPresetEnforced, setIsPresetEnforced] = React.useState(false)
  const [tone, setTone] = React.useState<FeatureFlagTone>('professional')

  React.useEffect(() => {
    const controller = new AbortController()

    const loadOverrides = async () => {
      try {
        const payload = await apiFetch<{ overrides?: FeatureFlagOverridePayload[]; preset?: unknown; enforced?: boolean }>(
          FEATURE_FLAG_OVERRIDES_PATH,
          { signal: controller.signal }
        )
        const remoteOverrides = (payload?.overrides ?? []) as FeatureFlagOverridePayload[]
        const remotePreset = typeof payload?.preset === 'string' ? (payload.preset as FeatureFlagPresetKey) : null
        const enforced = payload?.enforced === true

        setFlags((current) => applyOverridesToFlags(current, remoteOverrides))

        if (remotePreset && remotePreset in featureFlagPresets) {
          setActivePreset(remotePreset)
          setIsPresetEnforced(enforced)
          const presetTone = featureFlagPresets[remotePreset]?.tone
          if (presetTone) setTone(presetTone)
        }
      } catch (error) {
        if (controller.signal.aborted || (error as Error).name === 'AbortError') return
        console.warn('Failed to load feature flag overrides; using compiled defaults', error)
      } finally {
        if (!controller.signal.aborted) {
          setIsInitialized(true)
        }
      }
    }

    loadOverrides()
    return () => controller.abort()
  }, [])

  React.useEffect(() => {
    persistOverrides(flags)
  }, [flags])

  const setFlag = React.useCallback(async (key: FeatureFlagKey, enabled: boolean) => {
    if (isPresetEnforced) return

    const timestamp = new Date().toISOString()
    setActivePreset(null)
    setFlags((previous) => {
      const target = previous[key]
      if (!target) return previous
      return {
        ...previous,
        [key]: {
          ...target,
          enabled,
          updatedAt: timestamp,
          updatedBy: 'Feature Flag Studio',
        },
      }
    })

    try {
      const payload = await apiFetch<{ override?: FeatureFlagOverridePayload }>(FEATURE_FLAG_OVERRIDES_PATH, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key, enabled, updatedBy: 'Feature Flag Studio' }),
      })
      const override = payload?.override as FeatureFlagOverridePayload | undefined
      if (override) {
        setFlags((previous) => applyOverridesToFlags(previous, [override]))
      }
    } catch (error) {
      console.error('Failed to save feature flag override', error)
    }
  }, [isPresetEnforced])

  const setCategoryFlags = React.useCallback(
    async (category: FeatureFlagCategory, enabled: boolean) => {
      if (isPresetEnforced) return

      const timestamp = new Date().toISOString()
      const categoryFlags = Object.values(featureFlagRegistry).filter((f) => f.category === category)
      const keys = categoryFlags.map((f) => f.key)

      setActivePreset(null)
      setFlags((previous) => {
        const next = { ...previous }
        keys.forEach((key) => {
          const target = next[key]
          if (target) {
            next[key] = {
              ...target,
              enabled,
              updatedAt: timestamp,
              updatedBy: 'Feature Flag Studio (Bulk)',
            }
          }
        })
        return next
      })

      try {
        await Promise.all(
          keys.map((key) =>
            apiFetch(FEATURE_FLAG_OVERRIDES_PATH, {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ key, enabled, updatedBy: 'Feature Flag Studio (Bulk)' }),
              responseType: 'none',
            })
          )
        )
      } catch (error) {
        console.error('Failed to save bulk feature flag overrides', error)
      }
    },
    [isPresetEnforced]
  )

  const applyPreset = React.useCallback(async (presetKey: FeatureFlagPresetKey) => {
    if (isPresetEnforced) return

    const preset = featureFlagPresets[presetKey]
    if (!preset) return

    const timestamp = new Date().toISOString()
    setFlags(() => {
      const next = Object.keys(featureFlagRegistry).reduce((acc, key) => {
        const flagKey = key as FeatureFlagKey
        const base = featureFlagRegistry[flagKey]
        const enabled = preset.overrides[flagKey]
        acc[flagKey] = {
          ...base,
          enabled,
          updatedAt: timestamp,
          updatedBy: preset.label,
        }
        return acc
      }, {} as FeatureFlagRecord)
      return next
    })

    setActivePreset(presetKey)
    setTone(preset.tone)

    try {
      const payload = await apiFetch<{ overrides?: FeatureFlagOverridePayload[] }>(FEATURE_FLAG_PRESETS_PATH, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ preset: presetKey, overrides: preset.overrides, updatedBy: preset.label }),
      })
      const remoteOverrides = (payload?.overrides ?? []) as FeatureFlagOverridePayload[]
      if (remoteOverrides.length) {
        setFlags((previous) => applyOverridesToFlags(previous, remoteOverrides))
      }
    } catch (error) {
      console.error('Failed to apply preset', error)
    }
  }, [isPresetEnforced])

  const resetToDefaults = React.useCallback(async () => {
    await applyPreset('professional')
  }, [applyPreset])

  const isEnabled = React.useCallback(
    (key: FeatureFlagKey) => {
      return flags[key]?.enabled ?? false
    },
    [flags]
  )

  const getFlag = React.useCallback(
    (key: FeatureFlagKey) => {
      return flags[key] ?? featureFlagRegistry[key]
    },
    [flags]
  )

  const value: FeatureFlagContextValue = {
    flags,
    isInitialized,
    isEnabled,
    getFlag,
    setFlag,
    setCategoryFlags,
    applyPreset,
    resetToDefaults,
    activePreset,
    isPresetEnforced,
    presets: featureFlagPresets,
    tone,
  }

  React.useEffect(() => {
    if (typeof document === 'undefined') return
    document.documentElement.setAttribute('data-app-tone', tone)
  }, [tone])

  return <FeatureFlagContext.Provider value={value}>{children}</FeatureFlagContext.Provider>
}

export function useFeatureFlagContext() {
  const context = React.useContext(FeatureFlagContext)
  if (!context) {
    throw new Error('useFeatureFlagContext must be used within FeatureFlagProvider')
  }
  return context
}
