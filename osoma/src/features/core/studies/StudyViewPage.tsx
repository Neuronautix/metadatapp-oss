import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import { ArrowLeft, Pencil, Lock, FileCheck, Download, FileBarChart2 } from 'lucide-react'
import {
  getStudy,
  signStudy,
  downloadStudyFairReportPdf,
  getConnectedResourceLinksForStudy,
} from './study.api.ts'
import { exportInvestigationRoCrate } from '@/features/core/investigations/investigation.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { DetailTabs } from '@/components/DetailTabs.tsx'
import { RestrictedActions } from '@/components/RestrictedActions.tsx'
import { ResourceAuditPanel } from '@/features/system/audit/ResourceAuditPanel.tsx'
import { Timeline } from '@/features/system/audit/Timeline.tsx'
import { useTimelineEvents } from '@/features/system/audit/useTimelineEvents.ts'
import { useRole } from '@/app/role-context.tsx'
import { canEdit, isAdmin } from '@/lib/rbac.ts'
import { exportJson } from '@/lib/export.ts'
import { downloadBlob } from '@/lib/download.ts'
import { useToast } from '@/components/ui/toast.tsx'
import { formatDate, formatDateTime, formatNumber } from '@/lib/format.ts'
import { Feature } from '@/feature-flags/Feature.tsx'
import { FeatureFallback } from '@/feature-flags/FeatureFallback.tsx'
import { FairScorePanel } from './components/FairScorePanel.tsx'
import { computeFairCriteria } from '@/lib/fair.ts'

export function StudyViewPage() {
  const { studyId } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()

  const signMutation = useMutation({
    mutationFn: signStudy,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['study', studyId] })
      toast({
        title: 'Study Signed',
        description: 'Scientific validation has been recorded.',
        variant: 'success',
      })
    },
  })

  const roCrateMutation = useMutation({
    mutationFn: () => {
      if (!data?.investigationId) {
        throw new Error('Investigation not available for export.')
      }
      return exportInvestigationRoCrate(data.investigationId)
    },
    onSuccess: (blob) => {
      downloadBlob(blob, `${data?.id ?? 'study'}-ro-crate.zip`)
      toast({ title: 'RO-Crate export ready', description: 'Download complete.' })
    },
  })

  const fairReportMutation = useMutation({
    mutationFn: () => downloadStudyFairReportPdf(studyId ?? ''),
    onSuccess: (blob) => {
      downloadBlob(blob, `fair-report-study-${studyId ?? 'unknown'}.pdf`)
      toast({ title: 'FAIR Report ready', description: 'PDF downloaded.' })
    },
    onError: (error: Error) => {
      toast({ title: 'FAIR Report export failed', description: error.message, variant: 'error' })
    },
  })

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['study', studyId],
    queryFn: () => getStudy(studyId ?? ''),
    enabled: Boolean(studyId),
  })

  const connectedLinksQuery = useQuery({
    queryKey: ['study', studyId, 'connected-resource-links'],
    queryFn: () => getConnectedResourceLinksForStudy(studyId ?? ''),
    enabled: Boolean(studyId),
  })

  const timeline = useTimelineEvents('study', data?.id ?? '')

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <EmptyState
        title="Study unavailable"
        description={(error as Error)?.message ?? 'Study record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['study', studyId] })}
      />
    )
  }

  const statusVariant = data.status === 'running' ? 'success' : data.status === 'paused' ? 'warning' : 'outline'
  const qcVariant = data.qcStatus === 'pass' ? 'success' : data.qcStatus === 'review' ? 'warning' : 'outline'
  const allowEdit = canEdit(role)
  const allowAdmin = isAdmin(role)

  const overview = (
    <div className="space-y-6">
      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle>Run context</CardTitle>
            <CardDescription>Protocol lineage and investigation alignment.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-slate-600">
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Investigation</p>
              <p className="font-semibold text-slate-700">{data.investigationName}</p>
              <p>{data.investigationId}</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Protocol</p>
              <p className="font-semibold text-slate-700">{data.assayName}</p>
              <p>{data.assayId}</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Started</p>
              <p className="font-semibold text-slate-700">{formatDateTime(data.startedAt)}</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Completed</p>
              <p className="font-semibold text-slate-700">
                {data.completedAt ? formatDateTime(data.completedAt) : 'In progress'}
              </p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Metrics</CardTitle>
            <CardDescription>Throughput and sample load.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <p>Runs completed: {data.runCount}</p>
            <p>Samples in scope: {data.sampleCount}</p>
            <p>Data volume: {formatNumber(data.dataVolumeGb)} GB</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <FairScorePanel score={data.fairScore} criteria={computeFairCriteria(data)} />

        <Card>
          <CardHeader>
            <CardTitle>Provenance</CardTitle>
            <CardDescription>Recent audit trail.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            {data.provenance.map((event) => (
              <div key={event.id} className="space-y-1">
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">{formatDate(event.at)}</p>
                <p className="font-semibold text-slate-700">{event.action}</p>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Lifecycle</CardTitle>
            <CardDescription>Operational timestamps.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <p>Created {formatDate(data.createdAt)}</p>
            <p>Updated {formatDateTime(data.updatedAt)}</p>
          </CardContent>
        </Card>
      </div>

      <Card data-testid="connected-resources">
        <CardHeader>
          <CardTitle>Connected resources</CardTitle>
          <CardDescription>External systems linked to this study (eLabFTW, DVC, OSF, FAIR3R, ...).</CardDescription>
        </CardHeader>
        <CardContent className="space-y-3 text-sm text-slate-600">
          {connectedLinksQuery.isLoading ? (
            <Skeleton className="h-16 w-full" />
          ) : connectedLinksQuery.isError ? (
            <p className="text-slate-500">Unable to load connected resources.</p>
          ) : (connectedLinksQuery.data ?? []).length === 0 ? (
            <p className="text-slate-500">No external resources linked yet.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {(connectedLinksQuery.data ?? []).map((link) => (
                <li key={link.id} className="flex items-start justify-between gap-4 py-2">
                  <div>
                    <p className="font-semibold text-slate-700">
                      {link.label ?? `${link.provider} · ${link.externalId}`}
                    </p>
                    <p className="text-xs uppercase tracking-[0.15em] text-slate-400">
                      {link.provider} · {link.resourceType}
                      {link.subjectExternalId ? ` · subject ${link.subjectExternalId}` : ''}
                    </p>
                    <p className="text-xs text-slate-500">ID: {link.externalId}</p>
                  </div>
                  {link.url ? (
                    <a
                      className="text-xs font-medium text-emerald-700 hover:underline"
                      href={link.url}
                      rel="noreferrer noopener"
                      target="_blank"
                    >
                      Open ↗
                    </a>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>FAIR timeline</CardTitle>
          <CardDescription>Resource events grouped by day.</CardDescription>
        </CardHeader>
        <CardContent>
          {timeline.isLoading ? (
            <div className="space-y-3">
              {Array.from({ length: 3 }).map((_, index) => (
                <Skeleton key={index} className="h-16 w-full" />
              ))}
            </div>
          ) : timeline.isError ? (
            <EmptyState
              title="Timeline unavailable"
              description={timeline.error?.message ?? 'Unable to load timeline.'}
              actionLabel="Retry"
              onAction={timeline.refetch}
            />
          ) : (
            <Timeline events={timeline.events} />
          )}
        </CardContent>
      </Card>

      <RestrictedActions
        title="Restricted action"
        description="Archiving an study requires administrative approval."
        actionLabel="Archive study"
        disabled={!allowAdmin}
        onAction={() =>
          toast({
            title: 'Archive queued',
            description: 'Administrative review required before archival.',
          })
        }
      />
    </div>
  )

  const studyalPanels = (
    <div className="space-y-3">
      <Feature flag="studyal.agentic">
        <Card className="border border-rose-200 bg-rose-50/60">
          <CardHeader className="flex items-start justify-between gap-3">
            <div>
              <CardTitle>Agentic Science Mode</CardTitle>
              <CardDescription>Autonomous plans, next steps, and context for busy operators.</CardDescription>
            </div>
            <Badge variant="warning">Studyal</Badge>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <p className="font-semibold text-slate-700">Suggested focus</p>
            <ul className="ml-4 list-disc space-y-1">
              <li>Accelerate optical control sequence for batch {data.sampleCount}.</li>
              <li>Reprioritize anomaly triage for high-risk subjects.</li>
              <li>Schedule the next meeting with a shortened briefing attached.</li>
            </ul>
          </CardContent>
        </Card>
      </Feature>

      <Feature flag="studyal.explainability">
        <Card className="border border-line bg-white/80">
          <CardHeader className="flex items-start justify-between gap-3">
            <div>
              <CardTitle>Explain / Why Panel</CardTitle>
              <CardDescription>Signal trace and reasoning snapshots for every insight.</CardDescription>
            </div>
            <Badge variant="outline">Explain</Badge>
          </CardHeader>
          <CardContent className="grid gap-2 text-sm text-slate-600">
            <div className="flex items-center justify-between rounded-xl border border-line/80 bg-surface-2/80 px-3 py-2">
              <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Signal strength</span>
              <span className="font-semibold text-slate-700">93%</span>
            </div>
            <p className="text-sm">
              Why this stood out: new biomarker on {formatDate(data.startedAt)} aligned with investigationed
              reproducibility targets while staying within normal variability.
            </p>
          </CardContent>
        </Card>
      </Feature>

      <Feature flag="studyal.virtualControl">
        <Card className="border border-line bg-slate-50/70">
          <CardHeader className="flex items-start justify-between gap-3">
            <div>
              <CardTitle>Virtual Control Advanced Insights</CardTitle>
              <CardDescription>Layered hints for controls, reagents, and contingency actions.</CardDescription>
            </div>
            <Badge variant="outline">Virtual</Badge>
          </CardHeader>
          <CardContent className="grid gap-3 text-sm text-slate-600">
            <div className="flex items-center justify-between rounded-xl border border-line/80 px-3 py-2">
              <span>Active controls</span>
              <span className="font-semibold text-slate-700">4</span>
            </div>
            <div className="flex items-center justify-between rounded-xl border border-line/80 px-3 py-2">
              <span>Auto-calibration status</span>
              <span className="font-semibold text-emerald-600">Ready</span>
            </div>
            <p className="text-xs text-slate-500">
              Next tip: reroute ion-channel gating to offset the scheduled humidity drift.
            </p>
          </CardContent>
        </Card>
      </Feature>

      <Feature flag="studyal.narrative">
        <Card className="border border-line bg-gradient-to-r from-emerald-50 via-white to-slate-50">
          <CardHeader className="flex items-start justify-between gap-3">
            <div>
              <CardTitle>Narrative / Story Mode</CardTitle>
              <CardDescription>Human-centred summaries that link today’s runs to tomorrow’s breakthroughs.</CardDescription>
            </div>
            <Badge variant="outline">Narrative</Badge>
          </CardHeader>
          <CardContent className="space-y-2 text-sm text-slate-600">
            <p className="leading-6 text-slate-700">
              “Study {data.name} keeps momentum as the {data.assayName} protocol orchestrates the next wave
              of samples. The protocol weave weaves resilience into the pipeline.”
            </p>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Story cue</p>
          </CardContent>
        </Card>
      </Feature>
    </div>
  )

  const auditTab = (
    <Feature
      flag="feature.auditLogs"
      fallback={
        <FeatureFallback
          title="Resource audit logs hidden"
          description="Enable Audit Logs in the Feature Flag Studio to explore this study’s lineage."
          warning="Compliance"
          actionLabel="Manage flags"
          onAction={() => navigate('/admin/feature-flags')}
        />
      }
    >
      <ResourceAuditPanel resourceType="study" resourceId={data.id} />
    </Feature>
  )

  return (
    <div className="space-y-6">
      {data.isSigned ? (
        <div className="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-900">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100">
              <Lock className="h-5 w-5 text-emerald-600" />
            </div>
            <div>
              <p className="font-semibold">Study Signed & Locked</p>
              <p className="text-sm text-emerald-700">
                Validated by {data.signedBy} on {formatDateTime(data.signedAt ?? '')}
              </p>
            </div>
          </div>
          <Badge variant="success" className="bg-emerald-200 text-emerald-800 hover:bg-emerald-300">
            <FileCheck className="mr-1 h-3 w-3" />
            GLP Compliant
          </Badge>
        </div>
      ) : null}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to="/studies">
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Study</p>
            <h2 className="font-display text-2xl">{data.name}</h2>
            <p className="text-sm text-slate-500">{data.id}</p>
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={statusVariant}>{data.status}</Badge>
          <Badge variant={qcVariant}>QC {data.qcStatus}</Badge>
          <Button
            variant="outline"
            size="sm"
            onClick={() => roCrateMutation.mutate()}
            disabled={roCrateMutation.isPending || !data.investigationId}
          >
            <Download className="h-4 w-4" />
            Export RO-Crate
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => fairReportMutation.mutate()}
            disabled={fairReportMutation.isPending}
          >
            <FileBarChart2 className="h-4 w-4" />
            {fairReportMutation.isPending ? 'Generating…' : 'FAIR Report'}
          </Button>
          <Button variant="outline" size="sm" onClick={() => exportJson(data, `study-${data.id}`)}>
            Export JSON
          </Button>
          {allowEdit && !data.isSigned ? (
            <Button variant="outline" size="sm" asChild>
              <Link to={`/studies/${data.id}/edit`}>
                <Pencil className="h-4 w-4" />
                Edit
              </Link>
            </Button>
          ) : null}
        </div>
      </div>

      {studyalPanels}
      <DetailTabs overview={overview} audit={auditTab} />

      {!data.isSigned && allowEdit ? (
        <Card className="border-t-4 border-t-slate-400">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <FileCheck className="h-5 w-5 text-slate-500" />
              Scientific Validation
            </CardTitle>
            <CardDescription>
              Sign this study to lock it and certify data integrity for GLP compliance.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="flex items-center justify-between bg-slate-50 p-4 rounded-md border border-slate-100">
              <div className="text-sm text-slate-600">
                <p className="font-medium text-slate-900">Declaration</p>
                <p>I certify that the data collection is complete and the protocols were followed.</p>
              </div>
              <Button
                onClick={() => signMutation.mutate(data.id)}
                disabled={signMutation.isPending}
              >
                {signMutation.isPending ? 'Signing...' : 'Sign & Lock'}
              </Button>
            </div>
          </CardContent>
        </Card>
      ) : null}
    </div>
  )
}
