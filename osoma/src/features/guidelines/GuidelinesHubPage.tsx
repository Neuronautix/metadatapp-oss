import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ClipboardCheck } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { getInvestigations } from '@/features/core/investigations/investigation.api.ts'
import { getStudies } from '@/features/core/studies/study.api.ts'
import { brandingFor } from './guidelines-branding.ts'
import { getCompletion, getGuidelineTemplates } from './guidelines.api.ts'
import type { GuidelineResourceType, GuidelineTemplateSummary } from './guidelines.types.ts'

/**
 * Landing hub: one card per guideline, each linking to its dedicated page at
 * `/guidelines/:templateId`. Picking a target here carries it along in the
 * link's query string so the detail page opens pre-scoped instead of asking
 * the user to pick a target twice.
 */
export function GuidelinesHubPage() {
  const [investigationId, setInvestigationId] = useState<string | null>(null)
  const [studyId, setStudyId] = useState<string | null>(null)

  const resType: GuidelineResourceType = studyId ? 'studies' : 'investigations'
  const resId = studyId ?? investigationId

  const investigationsQuery = useQuery({ queryKey: ['investigations'], queryFn: getInvestigations })
  const investigations = investigationsQuery.data?.data ?? []

  const studiesQuery = useQuery({
    queryKey: ['guidelines-studies', investigationId],
    queryFn: () => getStudies({ page: 1, pageSize: 100, investigation: investigationId ?? undefined }),
    enabled: Boolean(investigationId),
  })
  const studies = studiesQuery.data?.data ?? []

  const templatesQuery = useQuery({ queryKey: ['guideline-templates'], queryFn: getGuidelineTemplates })
  const templates = templatesQuery.data ?? []

  return (
    <div className="space-y-6">
      <div>
        <h1 className="flex items-center gap-2 font-display text-2xl font-semibold text-ink">
          <ClipboardCheck className="h-6 w-6 text-brand" aria-hidden />
          International Guidelines Reporting
        </h1>
        <p className="mt-1 text-sm text-muted">
          Each guideline has its own dedicated page: live conformance, evidence-grounded auto-fill, and a QC
          sign-off before the compliance report is considered final.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Target</CardTitle>
          <CardDescription>
            Optionally pick an investigation or study to see its live completion on each card below.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4 md:flex-row">
          <label className="flex w-full flex-col gap-1 text-sm md:w-80">
            <span className="text-xs font-medium text-muted">Investigation</span>
            <select
              aria-label="Investigation"
              className="w-full rounded-lg border border-line bg-white p-3 text-sm"
              value={investigationId ?? ''}
              onChange={(event) => {
                setInvestigationId(event.target.value || null)
                setStudyId(null)
              }}
            >
              <option value="">Select an investigation…</option>
              {investigations.map((investigation) => (
                <option key={investigation.id} value={investigation.id}>
                  {investigation.name}
                </option>
              ))}
            </select>
          </label>

          <label className="flex w-full flex-col gap-1 text-sm md:w-80">
            <span className="text-xs font-medium text-muted">Study (optional)</span>
            <select
              aria-label="Study"
              className="w-full rounded-lg border border-line bg-white p-3 text-sm"
              value={studyId ?? ''}
              onChange={(event) => setStudyId(event.target.value || null)}
              disabled={!investigationId || studies.length === 0}
            >
              <option value="">Whole investigation</option>
              {studies.map((study) => (
                <option key={study.id} value={study.id}>
                  {study.name}
                </option>
              ))}
            </select>
          </label>
        </CardContent>
      </Card>

      {templatesQuery.isLoading ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <Skeleton className="h-36" />
          <Skeleton className="h-36" />
          <Skeleton className="h-36" />
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="guideline-hub-cards">
          {templates.map((template) => (
            <GuidelineCard key={template.id} template={template} resType={resType} resId={resId} />
          ))}
        </div>
      )}
    </div>
  )
}

function GuidelineCard({
  template,
  resType,
  resId,
}: {
  template: GuidelineTemplateSummary
  resType: GuidelineResourceType
  resId: string | null
}) {
  const branding = brandingFor(template.id)
  const Icon = branding.icon

  const completionQuery = useQuery({
    queryKey: ['guideline-completion', resType, resId, template.id],
    queryFn: () => getCompletion(resType, resId!, template.id),
    enabled: Boolean(resId),
  })

  const target = resId ? `?resType=${resType}&resId=${resId}` : ''
  const totals = completionQuery.data?.totals
  const total = totals ? totals.satisfiedDirect + totals.satisfiedViaCrosswalk + totals.partial + totals.missing : 0
  const satisfied = totals ? totals.satisfiedDirect + totals.satisfiedViaCrosswalk : 0
  const pct = total > 0 ? Math.round((satisfied / total) * 100) : null

  return (
    <Link to={`/guidelines/${template.id}${target}`} data-testid={`guideline-card-${template.id}`}>
      <Card className="h-full transition hover:border-brand/60">
        <CardContent className="space-y-2 p-4">
          <div className="flex items-center justify-between gap-2">
            <span className="flex items-center gap-2 font-display text-sm font-semibold text-ink">
              <Icon className={`h-4 w-4 ${branding.accent}`} aria-hidden />
              {template.name}
            </span>
            <Badge variant="outline">v{template.version}</Badge>
          </div>
          <p className="text-xs text-muted">{template.description}</p>
          {resId ? (
            completionQuery.isLoading ? (
              <Skeleton className="h-4 w-24" />
            ) : pct !== null ? (
              <Badge variant={pct === 100 ? 'success' : pct > 0 ? 'warning' : 'outline'}>
                {pct}% complete
              </Badge>
            ) : null
          ) : null}
        </CardContent>
      </Card>
    </Link>
  )
}
