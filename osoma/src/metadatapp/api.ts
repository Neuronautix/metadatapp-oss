import { apiFetch, apiFetchHydraCollection } from '@/lib/api.ts'
import type { SchemaRef, SchemaType } from './openapi-types.ts'

export const getMetaCollection = <T = Record<string, unknown>>(
  path: string,
  query?: Record<string, string | number>
) => {
  const params = new URLSearchParams()
  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value === undefined || value === null) return
    params.set(key, String(value))
  })
  const qs = params.toString()
  return apiFetchHydraCollection<T>(`${path}${qs ? `?${qs}` : ''}`)
}

export const getMetaItem = <T = Record<string, unknown>>(path: string) => apiFetch<T>(path)

export const createMetaItem = <T = Record<string, unknown>>(path: string, payload: Record<string, unknown>) =>
  apiFetch<T>(path, {
    method: 'POST',
    body: JSON.stringify(payload),
  })

export const updateMetaItem = <T = Record<string, unknown>>(
  path: string,
  payload: Record<string, unknown>,
  method: 'PATCH' | 'PUT'
) =>
  apiFetch<T>(path, {
    method,
    body: JSON.stringify(payload),
  })

export const deleteMetaItem = (path: string) =>
  apiFetch<void>(path, {
    method: 'DELETE',
    responseType: 'none',
  })

export const getMetaCollectionByRef = <Ref extends SchemaRef>(
  path: string,
  ref: Ref,
  query?: Record<string, string | number>
) => {
  void ref
  return getMetaCollection<SchemaType<Ref>>(path, query)
}

export const getMetaItemByRef = <Ref extends SchemaRef>(path: string, ref: Ref) => {
  void ref
  return getMetaItem<SchemaType<Ref>>(path)
}

export const createMetaItemByRef = <Ref extends SchemaRef>(
  path: string,
  ref: Ref,
  payload: Record<string, unknown>
) => {
  void ref
  return createMetaItem<SchemaType<Ref>>(path, payload)
}

export const updateMetaItemByRef = <Ref extends SchemaRef>(
  path: string,
  ref: Ref,
  payload: Record<string, unknown>,
  method: 'PATCH' | 'PUT'
) => {
  void ref
  return updateMetaItem<SchemaType<Ref>>(path, payload, method)
}
