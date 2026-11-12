import { getIdFromIri, getIri, type IriLike } from './iri.ts'

const DISPLAY_NAME_KEYS = ['name', 'label', 'title', 'displayName', 'externalId', 'email', 'code'] as const

const safeDecode = (value: string): string => {
  try {
    return decodeURIComponent(value)
  } catch {
    return value
  }
}

const humanize = (value: string) =>
  value
    .replace(/[_-]+/g, ' ')
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/\b\w/g, (char) => char.toUpperCase())

const getRawResourceId = (value: unknown, fallback = ''): string => {
  if (value === null || value === undefined || value === '') return fallback
  if (typeof value === 'string') {
    if (!value.startsWith('/')) return value
    const id = getIdFromIri(value)
    if (id) return id
  }
  if (typeof value === 'object') {
    const id = getIdFromIri(value as IriLike)
    if (id) return id
  }
  return String(value)
}

export const getReadableResourceId = (value: unknown, fallback = ''): string => {
  const id = getRawResourceId(value, fallback)
  return id ? safeDecode(id) : fallback
}

export const getRouteResourceId = (value: unknown, fallback = ''): string => {
  const id = getReadableResourceId(value, fallback)
  return id ? encodeURIComponent(id) : fallback
}

export const getReadableIriLabel = (value: IriLike): string | null => {
  const iri = getIri(value)
  if (!iri) return null
  if (!iri.startsWith('/')) return null

  const trimmed = iri.replace(/\/$/, '')
  const segments = trimmed.split('/').filter(Boolean)
  const id = safeDecode(segments[segments.length - 1] ?? '')

  if (!id) return null
  if (segments.length < 2) return id

  const resource = humanize(safeDecode(segments[segments.length - 2] ?? ''))
  return resource ? `${resource} · ${id}` : id
}

export const getReadableResourceLabel = (value: unknown, fallback = '—'): string => {
  if (value === null || value === undefined || value === '') return fallback

  if (Array.isArray(value)) {
    const labels = value
      .map((entry) => getReadableResourceLabel(entry, ''))
      .filter((entry) => entry.length > 0)

    return labels.length > 0 ? labels.join(', ') : fallback
  }

  if (typeof value === 'string') {
    return getReadableIriLabel(value) ?? value
  }

  if (typeof value === 'number' || typeof value === 'boolean') {
    return String(value)
  }

  if (typeof value === 'object') {
    const record = value as Record<string, unknown>

    for (const key of DISPLAY_NAME_KEYS) {
      const candidate = record[key]
      if (typeof candidate === 'string' && candidate.trim().length > 0) {
        return candidate
      }
    }

    return getReadableIriLabel(record as IriLike) ?? fallback
  }

  return String(value)
}
