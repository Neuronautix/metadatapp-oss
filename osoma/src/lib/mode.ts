export type DataMode = 'mock' | 'real'
export type AuthMode = 'real' | 'bypass'

const DATA_MODE_KEY = 'metadatapp_data_mode'
const AUTH_MODE_KEY = 'metadatapp_auth_mode'
const LEGACY_MSW_KEY = 'use_msw'

const safeLocalGet = (key: string) => {
  if (typeof window === 'undefined') return null
  try {
    return window.localStorage.getItem(key)
  } catch {
    return null
  }
}

const safeLocalSet = (key: string, value: string) => {
  if (typeof window === 'undefined') return
  try {
    window.localStorage.setItem(key, value)
  } catch {
    // Ignore storage errors (private mode, blocked, etc.)
  }
}

const normalizeDataMode = (value: string | null | undefined): DataMode | null => {
  if (value === 'mock' || value === 'real') return value
  return null
}

const normalizeAuthMode = (value: string | null | undefined): AuthMode | null => {
  if (value === 'real' || value === 'bypass') return value
  return null
}

const getUrlDataModeOverride = (): DataMode | null => {
  if (typeof window === 'undefined') return null

  try {
    const params = new URLSearchParams(window.location.search)
    return normalizeDataMode(params.get('dataMode') ?? params.get('mode'))
  } catch {
    return null
  }
}

export const getDataMode = (): DataMode => {
  const urlOverride = getUrlDataModeOverride()
  if (urlOverride) {
    safeLocalSet(DATA_MODE_KEY, urlOverride)
    safeLocalSet(LEGACY_MSW_KEY, String(urlOverride === 'mock'))
    return urlOverride
  }

  const legacy = safeLocalGet(LEGACY_MSW_KEY)
  if (legacy !== null) {
    return legacy === 'false' ? 'real' : 'mock'
  }

  const stored = normalizeDataMode(safeLocalGet(DATA_MODE_KEY))
  if (stored) return stored

  const env = normalizeDataMode(import.meta.env.VITE_DATA_MODE)
  // In production builds default to real; in dev default to mock for DX.
  return env ?? (import.meta.env.DEV ? 'mock' : 'real')
}

export const setDataMode = (mode: DataMode) => {
  safeLocalSet(DATA_MODE_KEY, mode)
  // Keep backward compatibility with legacy checks still using `use_msw`.
  safeLocalSet(LEGACY_MSW_KEY, String(mode === 'mock'))
}

export const getAuthMode = (): AuthMode => {
  const stored = normalizeAuthMode(safeLocalGet(AUTH_MODE_KEY))
  if (stored) return stored

  const env = normalizeAuthMode(import.meta.env.VITE_AUTH_MODE)
  if (env) return env

  return getDataMode() === 'mock' ? 'bypass' : 'real'
}

export const setAuthMode = (mode: AuthMode) => {
  safeLocalSet(AUTH_MODE_KEY, mode)
}
