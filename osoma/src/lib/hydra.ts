import type { PaginatedResponse } from './types.ts'

export type HydraView = {
  '@id'?: string
  '@type'?: string
  'hydra:first'?: string
  'hydra:last'?: string
  'hydra:previous'?: string
  'hydra:next'?: string
}

export type HydraCollection<T> = {
  'hydra:member': T[]
  'hydra:totalItems'?: number
  'hydra:view'?: HydraView
}

const getParamFromUrl = (url: string | undefined, key: string): number | null => {
  if (!url) return null
  try {
    const parsed = new URL(url, 'http://localhost')
    const value = parsed.searchParams.get(key)
    if (!value) return null
    const parsedNumber = Number(value)
    return Number.isFinite(parsedNumber) ? parsedNumber : null
  } catch {
    return null
  }
}

const getPageFromPath = (path?: string) => getParamFromUrl(path, 'page')

const getPageSizeFromPath = (path?: string) =>
  getParamFromUrl(path, 'pageSize') ?? getParamFromUrl(path, 'itemsPerPage')

export const toPaginatedResponse = <T>(payload: HydraCollection<T>, path?: string): PaginatedResponse<T> => {
  const data = payload['hydra:member'] ?? []
  const total = payload['hydra:totalItems'] ?? data.length
  const pageFromView = getParamFromUrl(payload['hydra:view']?.['@id'], 'page')
  const page = pageFromView ?? getPageFromPath(path) ?? 1
  const pageSize = getPageSizeFromPath(path) ?? data.length

  return {
    data,
    page,
    pageSize,
    total,
  }
}

export const mapHydraCollection = <T, U>(
  payload: HydraCollection<T>,
  mapper: (item: T) => U,
  path?: string
): PaginatedResponse<U> => {
  const base = toPaginatedResponse(payload, path)
  return {
    ...base,
    data: base.data.map(mapper),
  }
}
