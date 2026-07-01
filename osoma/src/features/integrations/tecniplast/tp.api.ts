import { apiFetch } from '@/lib/api.ts'

export const tpFetch = async <T>(path: string, init?: RequestInit) => apiFetch<T>(`/tp${path}`, init)

export const buildQuery = (params: Record<string, string | number | undefined | null>) => {
  const query = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === '') return
    query.set(key, String(value))
  })
  const value = query.toString()
  return value ? `?${value}` : ''
}
