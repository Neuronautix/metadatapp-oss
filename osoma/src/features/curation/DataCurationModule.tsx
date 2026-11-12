import { Routes, Route, Navigate, useLocation, NavLink } from 'react-router-dom'
import { Upload, GitMerge, ShieldCheck, Network, Filter, Database } from 'lucide-react'
import { DataImportPage } from './DataImportPage.tsx'
import { MappingStudio } from './MappingStudio.tsx'
import { ValidationPage } from './ValidationPage.tsx'
import { GraphViewerPage } from './GraphViewerPage.tsx'
import { ProfilesPage } from './ProfilesPage.tsx'
import { MappViewPage } from './MappViewPage.tsx'
import { CurationProvider } from './curation-context.tsx'
import { ResolutionPage } from './ResolutionPage.tsx'
import { PatchReviewPage } from './PatchReviewPage.tsx'
import { cn } from '@/lib/utils.ts'

const WORKFLOW_STEPS = [
  { path: 'import', label: 'Upload', icon: Upload },
  { path: 'mapping', label: 'Map', icon: GitMerge },
  { path: 'validation', label: 'Validate', icon: ShieldCheck },
  { path: 'resolution', label: 'Resolve', icon: Network },
  { path: 'patch-review', label: 'Review', icon: Filter },
  { path: 'graph', label: 'Graph', icon: Database },
] as const

function CurationStepper() {
  const { pathname } = useLocation()
  const currentIndex = WORKFLOW_STEPS.findIndex((s) => pathname === `/curation/${s.path}`)

  return (
    <nav
      aria-label="Curation workflow steps"
      className="mb-6 flex items-center gap-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm"
    >
      {WORKFLOW_STEPS.map((step, i) => {
        const Icon = step.icon
        const isPast = i < currentIndex
        const isCurrent = i === currentIndex
        return (
          <div key={step.path} className="flex items-center gap-1">
            <NavLink
              to={`/curation/${step.path}`}
              aria-current={isCurrent ? 'step' : undefined}
              className={cn(
                'flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition-colors',
                isCurrent && 'bg-slate-900 text-white shadow-sm',
                isPast && 'text-emerald-600 hover:bg-emerald-50',
                !isCurrent && !isPast && 'text-slate-400 hover:bg-slate-50 hover:text-slate-600',
              )}
            >
              <Icon className="h-3 w-3 shrink-0" aria-hidden="true" />
              <span className="sr-only sm:not-sr-only sm:inline">{step.label}</span>
            </NavLink>
            {i < WORKFLOW_STEPS.length - 1 && (
              <span className="h-px w-3 shrink-0 bg-slate-200" />
            )}
          </div>
        )
      })}
    </nav>
  )
}

export function DataCurationModule() {
  return (
    <CurationProvider>
      <CurationStepper />
      <Routes>
        <Route index element={<Navigate to="import" replace />} />
        <Route path="import" element={<DataImportPage />} />
        <Route path="mapping" element={<MappingStudio />} />
        <Route path="validation" element={<ValidationPage />} />
        <Route path="resolution" element={<ResolutionPage />} />
        <Route path="patch-review" element={<PatchReviewPage />} />
        <Route path="graph" element={<GraphViewerPage />} />
        <Route path="schemas" element={<ProfilesPage />} />
        <Route path="schemas/mapp-view" element={<MappViewPage />} />
      </Routes>
    </CurationProvider>
  )
}
