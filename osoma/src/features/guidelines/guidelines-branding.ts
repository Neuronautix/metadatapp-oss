import { ClipboardCheck, FlaskConical, ListChecks, ShieldCheck, Waypoints } from 'lucide-react'

/**
 * Presentation-only per-template branding for the dedicated guideline pages.
 * The backend template config (api/config/guidelines/*.yaml) carries no
 * icon/color metadata — none is needed there — so this is a small,
 * frontend-only lookup keyed by the stable template id.
 */
export type GuidelineBranding = {
  icon: typeof ClipboardCheck
  accent: string
  tagline: string
}

const BRANDING: Record<string, GuidelineBranding> = {
  'arrive-v2': {
    icon: ClipboardCheck,
    accent: 'text-brand',
    tagline: 'What you must report when publishing in-vivo research.',
  },
  'prepare-v1': {
    icon: ListChecks,
    accent: 'text-success',
    tagline: 'What you must plan before touching an animal.',
  },
  'eqipd-v1': {
    icon: ShieldCheck,
    accent: 'text-warning',
    tagline: "Research-led quality framework for the unit's processes.",
  },
  'mnms-v1': {
    icon: FlaskConical,
    accent: 'text-info',
    tagline: 'Minimum CSV columns, units, and roles for a dataset.',
  },
  'arrive-prepare-crosswalk-v1': {
    icon: Waypoints,
    accent: 'text-brand',
    tagline: 'Union of ARRIVE reporting and PREPARE planning fields.',
  },
}

const DEFAULT_BRANDING: GuidelineBranding = {
  icon: ClipboardCheck,
  accent: 'text-brand',
  tagline: 'Guideline conformance and reporting.',
}

export function brandingFor(templateId: string): GuidelineBranding {
  return BRANDING[templateId] ?? DEFAULT_BRANDING
}
