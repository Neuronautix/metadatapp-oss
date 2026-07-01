import { http, HttpResponse } from 'msw'
import type { FeatureFlagKey } from '@/feature-flags/featureFlag.types.ts'
import { featureFlagPresets, type FeatureFlagPresetKey } from '@/feature-flags/presets.ts'

interface FeatureFlagOverridePayload {
  key: FeatureFlagKey
  enabled: boolean
  updatedAt: string
  updatedBy: string
}

// Simulate an account-level preset for development/testing.
// Set to a FeatureFlagPresetKey (e.g. 'zefix') to emulate an account that
// has that profile enforced server-side, or null to use per-session overrides.
const MOCK_ACCOUNT_PRESET: FeatureFlagPresetKey | null = null

let storedOverrides: FeatureFlagOverridePayload[] = []

const upsertOverride = (entry: FeatureFlagOverridePayload) => {
  storedOverrides = [...storedOverrides.filter((override) => override.key !== entry.key), entry]
}

const buildPresetOverrides = (presetKey: FeatureFlagPresetKey): FeatureFlagOverridePayload[] => {
  const preset = featureFlagPresets[presetKey]
  if (!preset) return []
  const now = new Date().toISOString()
  return (Object.entries(preset.overrides) as [FeatureFlagKey, boolean][]).map(([key, enabled]) => ({
    key,
    enabled,
    updatedAt: now,
    updatedBy: `Account preset: ${presetKey}`,
  }))
}

export const featureFlagsHandlers = [
  http.get('/api/feature-flags/overrides', async () => {
    if (MOCK_ACCOUNT_PRESET) {
      return HttpResponse.json({
        overrides: buildPresetOverrides(MOCK_ACCOUNT_PRESET),
        preset: MOCK_ACCOUNT_PRESET,
        enforced: true,
      })
    }
    return HttpResponse.json({ overrides: storedOverrides, preset: null, enforced: false })
  }),

  http.post('/api/feature-flags/overrides', async ({ request }) => {
    if (MOCK_ACCOUNT_PRESET) {
      return HttpResponse.json({ override: null, enforced: true })
    }

    const payload = (await request.json()) as Partial<FeatureFlagOverridePayload>
    if (!payload.key) {
      return HttpResponse.json({ message: 'Invalid feature flag key' }, { status: 400 })
    }

    const override: FeatureFlagOverridePayload = {
      key: payload.key,
      enabled: payload.enabled ?? false,
      updatedAt: new Date().toISOString(),
      updatedBy: payload.updatedBy ?? 'Feature Flag Studio',
    }

    upsertOverride(override)
    return HttpResponse.json({ override })
  }),

  http.post('/api/feature-flags/presets', async ({ request }) => {
    if (MOCK_ACCOUNT_PRESET) {
      return HttpResponse.json({ overrides: [], enforced: true })
    }

    const payload = (await request.json()) as {
      preset: string
      overrides: FeatureFlagOverridePayload[]
    }
    storedOverrides = payload.overrides
    return HttpResponse.json({ overrides: storedOverrides })
  }),
]

