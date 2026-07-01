import { Link } from 'react-router-dom'
import {
  Search,
  Lock,
  Share2,
  RefreshCw,
  ArrowRight,
  CheckCircle2,
  Heart,
  FlaskConical,
  BarChart3,
  FileText,
  Sparkles,
} from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'

const PILLARS = [
  {
    letter: 'F',
    label: 'Findable',
    color: 'bg-blue-500',
    textColor: 'text-blue-700',
    borderColor: 'border-blue-200',
    bgLight: 'bg-blue-50',
    icon: Search,
    tagline: 'No lost data → fewer repeated experiments',
    animalImpact:
      'Every experiment that cannot be found gets re-run. FAIR-compliant persistent identifiers and rich metadata ensure that prior work is discovered and reused, directly reducing the number of animals needed.',
    criteria: ['Persistent unique identifier (DOI/IRI)', 'Rich descriptive metadata', 'Indexed in searchable registry'],
  },
  {
    letter: 'A',
    label: 'Accessible',
    color: 'bg-violet-500',
    textColor: 'text-violet-700',
    borderColor: 'border-violet-200',
    bgLight: 'bg-violet-50',
    icon: Lock,
    tagline: 'Open access → global collaboration',
    animalImpact:
      'When data is locked in silos, institutions repeat each other\'s work. Accessible data — retrievable via standard protocols even after the original study ends — enables cross-institutional synthesis and accelerates development of non-animal methods.',
    criteria: ['Standardised protocol (API/HTTPS)', 'Authentication with open metadata', 'Long-term data availability'],
  },
  {
    letter: 'I',
    label: 'Interoperable',
    color: 'bg-amber-500',
    textColor: 'text-amber-700',
    borderColor: 'border-amber-200',
    bgLight: 'bg-amber-50',
    icon: Share2,
    tagline: 'Systems talk to each other → better welfare tracking',
    animalImpact:
      'Interoperability with community ontologies (species, strain, procedure) means welfare indicators from one system can be meaningfully combined with another. Cage sensors, ELN data, and study metadata all become parts of one welfare picture.',
    criteria: ['FAIR-compliant vocabularies / ontologies', 'Qualified references to related data', 'Formal knowledge representation (JSON-LD)'],
  },
  {
    letter: 'R',
    label: 'Reusable',
    color: 'bg-emerald-500',
    textColor: 'text-emerald-700',
    borderColor: 'border-emerald-200',
    bgLight: 'bg-emerald-50',
    icon: RefreshCw,
    tagline: 'Data reused → 3Rs in action',
    animalImpact:
      'Reusable data — rich context, provenance, clear licence — is the data that becomes training sets for NAM/in-silico models, that gets cited in regulatory submissions, and that enables statistical power calculations that reduce cohort sizes.',
    criteria: ['Clear data usage licence', 'Detailed provenance trail', 'Domain-relevant community standards (ISA framework)'],
  },
]

const DEMO_STEPS = [
  {
    step: 1,
    title: 'Dashboard — the welfare landscape at a glance',
    path: '/',
    icon: BarChart3,
    description:
      'Open the dashboard to see aggregate FAIR compliance across all active investigations. The WellFAIR banner contextualises why FAIR data directly reduces animal use.',
  },
  {
    step: 2,
    title: 'Investigations — every study, FAIR-scored',
    path: '/investigations',
    icon: FlaskConical,
    description:
      'The investigations list shows a FAIR badge alongside each program. Click into an investigation to see the full FAIR pillar breakdown and which criteria pass or need attention.',
  },
  {
    step: 3,
    title: 'FAIR Score panel — criterion-by-criterion audit',
    path: '/investigations',
    icon: CheckCircle2,
    description:
      'Inside an investigation, scroll to the FAIR Score panel. Each of the 15 sub-principles is shown with a pass/fail indicator linked to concrete metadata signals — not box-ticking.',
  },
  {
    step: 4,
    title: 'AI Assistant — ask "how FAIR is this study?"',
    path: '/assistant',
    icon: Sparkles,
    description:
      'Open the AI assistant and ask it to assess FAIR compliance for a study. The MCP bridge retrieves the live assessment and explains which criteria are failing and why — in plain language.',
  },
  {
    step: 5,
    title: 'FAIR3R Sync — push FDF JSON & DataCite XML',
    path: '/connected-apps',
    icon: RefreshCw,
    description:
      'Open the FAIR3R connected app and use the Schema Exchange panel to Fetch, Verify, or Push the investigation as an FDF JSON document (fdf-dataset schema) or a DataCite XML file — compatible with validation.fair3r.fr and DOI registration agencies.',
  },
  {
    step: 6,
    title: 'Export — FAIR metadata bundle',
    path: '/export',
    icon: FileText,
    description:
      'Download an RO-Crate bundle or a FAIR PDF report. Both formats are structured for repository deposit and regulatory submission, making re-use by other teams frictionless.',
  },
]

export function WellFairPage() {
  return (
    <div className="space-y-10">
      {/* Hero */}
      <div className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 px-8 py-14 text-white shadow-xl">
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(99,102,241,0.25),_transparent_60%)]" />
        <div className="relative mx-auto max-w-3xl text-center">
          <Badge className="mb-4 border-indigo-400/30 bg-indigo-500/20 text-indigo-200">
            WellFAIR Framework
          </Badge>
          <h1 className="font-display text-4xl font-bold leading-tight tracking-tight text-white sm:text-5xl">
            Data welfare{' '}
            <span className="text-indigo-300">is</span>{' '}
            animal welfare
          </h1>
          <p className="mt-4 text-lg text-slate-300">
            FAIR-by-design metadata is not a compliance checkbox — it is the mechanism by which every
            experiment informs the next, reducing redundancy and, with it, reducing animal use.
          </p>
          <div className="mt-8 flex flex-wrap items-center justify-center gap-3">
            <Button asChild size="lg" className="bg-indigo-500 hover:bg-indigo-400 text-white">
              <Link to="/investigations">
                Explore investigations
                <ArrowRight className="ml-2 h-4 w-4" />
              </Link>
            </Button>
            <Button asChild variant="outline" size="lg" className="border-slate-600 bg-transparent text-slate-200 hover:bg-slate-800 hover:text-white">
              <Link to="/assistant">Ask the AI assistant</Link>
            </Button>
          </div>
        </div>
      </div>

      {/* FAIR pillars */}
      <PageHeader
        title="The WellFAIR connection"
        subtitle="Each FAIR pillar maps to a measurable reduction in animal use or an improvement in welfare monitoring."
      />

      <div className="grid gap-5 md:grid-cols-2">
        {PILLARS.map((pillar) => {
          const Icon = pillar.icon
          return (
            <Card key={pillar.letter} className={`border ${pillar.borderColor} ${pillar.bgLight}`}>
              <CardHeader className="flex flex-row items-start gap-4 pb-3">
                <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ${pillar.color} text-white`}>
                  <span className="text-2xl font-bold">{pillar.letter}</span>
                </div>
                <div className="flex-1">
                  <div className="flex items-center gap-2">
                    <CardTitle className={`text-lg ${pillar.textColor}`}>{pillar.label}</CardTitle>
                    <Icon className={`h-4 w-4 ${pillar.textColor}`} />
                  </div>
                  <p className="mt-0.5 text-sm font-semibold text-slate-600">{pillar.tagline}</p>
                </div>
              </CardHeader>
              <CardContent className="space-y-3">
                <p className="text-sm text-slate-700">{pillar.animalImpact}</p>
                <ul className="space-y-1">
                  {pillar.criteria.map((c) => (
                    <li key={c} className="flex items-center gap-2 text-xs text-slate-600">
                      <CheckCircle2 className="h-3.5 w-3.5 shrink-0 text-emerald-500" />
                      {c}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          )
        })}
      </div>

      {/* 3Rs connection */}
      <Card className="border-rose-100 bg-rose-50">
        <CardHeader>
          <div className="flex items-center gap-3">
            <Heart className="h-6 w-6 text-rose-500" />
            <CardTitle className="text-rose-700">FAIR data and the 3Rs</CardTitle>
          </div>
        </CardHeader>
        <CardContent className="grid gap-4 text-sm text-slate-700 sm:grid-cols-3">
          <div className="space-y-1">
            <p className="font-bold text-rose-600">Replacement</p>
            <p>
              Reusable, interoperable datasets become training corpora for in-silico and organ-on-chip models,
              accelerating the shift to non-animal methods.
            </p>
          </div>
          <div className="space-y-1">
            <p className="font-bold text-rose-600">Reduction</p>
            <p>
              Findable, accessible prior results enable robust power calculations and avoid redundant studies —
              fewer animals for the same scientific output.
            </p>
          </div>
          <div className="space-y-1">
            <p className="font-bold text-rose-600">Refinement</p>
            <p>
              Live sensor data linked to FAIR metadata lets welfare officers detect distress earlier and adapt
              protocols in real time.
            </p>
          </div>
        </CardContent>
      </Card>

      {/* Live demo workflow */}
      <div>
        <PageHeader
          title="Live demo workflow"
          subtitle="Follow these steps to walk through the WellFAIR capabilities in Metadatapp."
        />
        <div className="mt-6 space-y-4">
          {DEMO_STEPS.map((s) => {
            const Icon = s.icon
            return (
              <div key={s.step} className="flex items-start gap-4 rounded-2xl border border-line bg-white p-5 shadow-subtle">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">
                  {s.step}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <Icon className="h-4 w-4 shrink-0 text-slate-500" />
                    <h3 className="font-semibold text-slate-900">{s.title}</h3>
                    <Badge variant="outline" className="font-mono text-xs">
                      {s.path}
                    </Badge>
                  </div>
                  <p className="mt-1 text-sm text-slate-600">{s.description}</p>
                </div>
                <Button variant="ghost" size="sm" asChild className="shrink-0">
                  <Link to={s.path}>
                    Open
                    <ArrowRight className="ml-1 h-3.5 w-3.5" />
                  </Link>
                </Button>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
