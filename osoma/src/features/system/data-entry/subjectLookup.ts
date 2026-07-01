import { apiFetch } from '@/lib/api.ts'
import type { Subject } from '@/domain/resources.ts'

export type SubjectLookupResult = {
  found: boolean
  subject?: Subject
  cohort?: string
}

export async function lookupSubjectIds(ids: string[]): Promise<Record<string, SubjectLookupResult>> {
  const unique = [...new Set(ids.filter(Boolean))]
  if (!unique.length) return {}

  const raw = await apiFetch<Record<string, Subject | null>>('/subjects/lookup', {
    method: 'POST',
    body: JSON.stringify({ ids: unique }),
  })

  const results: Record<string, SubjectLookupResult> = {}
  for (const id of unique) {
    const hit = raw[id]
    results[id] = hit
      ? { found: true, subject: hit, cohort: hit.cohort }
      : { found: false }
  }
  return results
}
