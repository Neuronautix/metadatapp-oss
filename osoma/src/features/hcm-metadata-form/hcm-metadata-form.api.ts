import { useQuery } from '@tanstack/react-query'
import { apiFetch, apiFetchHydraCollection } from '@/lib/api.ts'
import type {
  CanonicalForm,
  CanonicalFormSectionGroup,
} from './hcm-metadata-form.types.ts'

export const HCM_DOMAIN = 'home-cage-monitoring'
export const HCM_TITLE = 'HCM Minimal Metadata Form'

export async function fetchCanonicalForms(): Promise<CanonicalForm[]> {
  const collection = await apiFetchHydraCollection<CanonicalForm>('/canonical-forms')
  return collection.data
}

export async function fetchCanonicalForm(id: string): Promise<CanonicalForm> {
  return apiFetch<CanonicalForm>(`/canonical-forms/${id}`)
}

/**
 * Finds the HCM form within a collection, preferring an exact domain match and
 * falling back to a title match so the viewer stays resilient to seeding changes.
 */
export function pickHcmForm(forms: CanonicalForm[]): CanonicalForm | undefined {
  return (
    forms.find((form) => form.domain === HCM_DOMAIN) ??
    forms.find((form) => form.title === HCM_TITLE)
  )
}

/**
 * Resolves the HCM canonical form: lists the collection, selects the HCM entry,
 * then loads its detail (which carries the full sections + fields payload).
 */
export async function fetchHcmCanonicalForm(): Promise<CanonicalForm> {
  const forms = await fetchCanonicalForms()
  const summary = pickHcmForm(forms)
  if (!summary) {
    throw new Error('HCM Minimal Metadata Form is not available.')
  }
  return fetchCanonicalForm(summary.id)
}

export function useHcmCanonicalForm() {
  return useQuery({
    queryKey: ['canonical-forms', 'hcm'],
    // Wrapped (rather than passed by reference) so tests can mock
    // `fetchHcmCanonicalForm` via the module export and have it take effect here.
    queryFn: () => fetchHcmCanonicalForm(),
  })
}

const stringifyIsaNode = (node: unknown): string | null => {
  if (typeof node === 'string') return node
  if (node && typeof node === 'object') {
    const record = node as Record<string, unknown>
    const label = record.isaForm ?? record.isaNode ?? record.label
    if (typeof label === 'string') return label
  }
  return null
}

/**
 * Walks the form's `sourceLinks.isaMapping` tree (mirrors the seed JSON shape)
 * to find the ISA form annotation declared for a given section title/key.
 */
export function resolveIsaAnnotation(
  form: CanonicalForm,
  section: { title: string }
): string | null {
  const isaMapping = (form.sourceLinks as Record<string, unknown> | null)?.isaMapping
  if (!isaMapping || typeof isaMapping !== 'object') return null

  const sectionKey = section.title.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
  const matches: string[] = []

  const visit = (node: unknown) => {
    if (Array.isArray(node)) {
      for (const entry of node) {
        if (entry && typeof entry === 'object') {
          const record = entry as Record<string, unknown>
          const key = typeof record.section === 'string' ? record.section : null
          if (key && (key === sectionKey || section.title.toLowerCase() === key.replace(/_/g, ' '))) {
            const label = stringifyIsaNode(record.isaForm) ?? stringifyIsaNode(record)
            if (label) matches.push(label)
          }
        }
        visit(entry)
      }
      return
    }
    if (node && typeof node === 'object') {
      for (const value of Object.values(node as Record<string, unknown>)) {
        visit(value)
      }
    }
  }

  visit(isaMapping)
  return matches.length > 0 ? Array.from(new Set(matches)).join(' / ') : null
}

/**
 * Groups a form's fields under their section, ordered by `orderIndex`, and
 * annotates each group with its ISA hierarchy form (if declared in sourceLinks).
 */
export function groupFieldsBySection(form: CanonicalForm): CanonicalFormSectionGroup[] {
  const sections = [...form.sections].sort((a, b) => a.orderIndex - b.orderIndex)

  return sections.map((section) => {
    const fields = form.fields
      .filter((field) => field.section?.id === section.id)
      .sort((a, b) => a.orderIndex - b.orderIndex)

    return {
      section,
      fields,
      isaAnnotation: resolveIsaAnnotation(form, section),
    }
  })
}
