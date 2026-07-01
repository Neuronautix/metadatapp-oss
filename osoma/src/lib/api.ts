import { AuthService } from './auth.ts'
import { getDataMode } from './mode.ts'
import type { PaginatedResponse } from './types.ts'
import type { HydraCollection } from './hydra.ts'
import { mapHydraCollection, toPaginatedResponse } from './hydra.ts'

// In development, use relative paths which will be proxied by Vite
// In production, use the configured API URL or default to same origin
const DEFAULT_DEV_API_BASE = '/api'
const BROWSER_INCOMPATIBLE_API_HOSTNAMES = new Set(['frontend', 'api'])

const isBrowserIncompatibleApiBase = (base: string) => {
  try {
    const parsed = new URL(base, typeof window !== 'undefined' ? window.location.origin : 'http://localhost')
    // `frontend` and `api` are docker-internal hostnames and are not reachable from browsers.
    return BROWSER_INCOMPATIBLE_API_HOSTNAMES.has(parsed.hostname)
  } catch {
    return false
  }
}

const getApiBase = () => {
  if (getDataMode() === 'mock') {
    // In mock mode always target the local API namespace so MSW handlers
    // registered on `/api/*` reliably intercept requests.
    return DEFAULT_DEV_API_BASE
  }

  const envBase = import.meta.env.VITE_API_URL?.trim()
  if (envBase) {
    if (isBrowserIncompatibleApiBase(envBase)) {
      return DEFAULT_DEV_API_BASE
    }
    return envBase.replace(/\/$/, '')
  }

  return DEFAULT_DEV_API_BASE
}

const resolveRequestUrl = (path: string) => {
  const candidate = `${getApiBase()}${path}`

  try {
    const parsed = new URL(candidate, typeof window !== 'undefined' ? window.location.origin : 'http://localhost')
    if (parsed.hostname === 'frontend') {
      return `${DEFAULT_DEV_API_BASE}${path}`
    }
  } catch {
    // Ignore URL parse errors and keep candidate.
  }

  return candidate
}

/**
 * Resolves a backend static asset path (e.g. `/images/apps/elabftw.svg`)
 * against the current API base so it is routed through the same proxy
 * as regular API calls (e.g. `/api/images/apps/elabftw.svg` in dev).
 */
export const resolveApiAssetUrl = (path: string): string => `${getApiBase()}${path}`

export type ApiFetchOptions = RequestInit & {
  responseType?: 'json' | 'text' | 'blob' | 'arrayBuffer' | 'none'
}

const resolveErrorMessage = (responseText: string, statusText: string) => {
  if (!responseText) return statusText
  try {
    const parsed = JSON.parse(responseText)
    return (
      parsed?.message ||
      parsed?.detail ||
      parsed?.['hydra:description'] ||
      statusText
    )
  } catch {
    return statusText
  }
}

export async function apiFetch<T>(path: string, init?: ApiFetchOptions) {
  const authService = AuthService.getInstance();
  const headers: Record<string, string> = {
    'Accept': 'application/ld+json, application/json;q=0.9',
    ...(init?.headers as Record<string, string> ?? {}),
  };

  // Set Content-Type based on method (skip for FormData: browser sets it with the boundary)
  if (init?.body && !(init.body instanceof FormData) && !headers['Content-Type']) {
    if (init.method === 'PATCH') {
      headers['Content-Type'] = 'application/merge-patch+json';
    } else {
      headers['Content-Type'] = 'application/ld+json';
    }
  }

  // Add authorization header if we have a token
  const token = authService.getAccessToken();
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const bypassMock = getDataMode() === 'real';

  const response = await fetch(resolveRequestUrl(path), {
    ...init,
    headers: {
      ...headers,
      ...(bypassMock ? { 'X-Bypass-Mock': 'true' } : {})
    },
  });

  if (!response.ok) {
    const text = await response.text()
    if (text) {
      console.error('API Error:', path, text)
    }

    if (response.status === 401) {
      // Avoid redirect loops: only trigger remote logout if we actually had
      // a REAL authenticated token on this request.
      if (token && token !== 'mock-access-token') {
        console.warn('Received 401 Unauthorized with real token, forcing logout to clear invalid session.')
        authService.logout()
      }
    }

    throw new Error(resolveErrorMessage(text, response.statusText))
  }

  const responseType = init?.responseType ?? 'json'

  if (responseType === 'none') {
    return null as T
  }

  if (responseType === 'blob') {
    return (await response.blob()) as T
  }

  if (responseType === 'arrayBuffer') {
    return (await response.arrayBuffer()) as T
  }

  if (responseType === 'text') {
    return (await response.text()) as T
  }

  const text = await response.text()
  if (!text) {
    return {} as T
  }
  try {
    return JSON.parse(text) as T
  } catch (err) {
    console.error('API Success but Parse Failed: Received invalid JSON from', path, text)
    throw err
  }
}

export async function apiFetchHydraCollection<T>(path: string, init?: ApiFetchOptions): Promise<PaginatedResponse<T>> {
  const payload = await apiFetch<HydraCollection<T>>(path, init)
  return toPaginatedResponse(payload, path)
}

export async function apiFetchHydraMapped<T, U>(
  path: string,
  mapper: (item: T) => U,
  init?: ApiFetchOptions
): Promise<PaginatedResponse<U>> {
  const payload = await apiFetch<HydraCollection<T>>(path, init)
  return mapHydraCollection(payload, mapper, path)
}
