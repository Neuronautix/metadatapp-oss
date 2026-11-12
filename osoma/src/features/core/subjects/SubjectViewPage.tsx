import { Link, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Pencil } from 'lucide-react'
import { getSubject } from './subject.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { DetailLayout } from '@/components/DetailLayout.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { RestrictedActions } from '@/components/RestrictedActions.tsx'
import { ResourceAuditPanel } from '@/features/system/audit/ResourceAuditPanel.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit, isAdmin } from '@/lib/rbac.ts'
import { exportJson } from '@/lib/export.ts'
import { useToast } from '@/components/ui/toast.tsx'
import { formatDate } from '@/lib/format.ts'
import { Feature } from '@/feature-flags/Feature.tsx'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { useTimelineEvents } from '@/features/system/audit/useTimelineEvents.ts'
import { AssayTimeline } from '@/features/system/timeline/AssayTimeline.tsx'
import { HousingBatches } from './HousingBatches.tsx'
import { ImagingMocks } from './ImagingMocks.tsx'
import { SubjectCurationPanel } from './SubjectCurationPanel.tsx'

export function SubjectViewPage() {
  const { subjectId } = useParams()
  const queryClient = useQueryClient()
  const { role } = useRole()
  const { toast } = useToast()
  const curateGptEnabled = useFeatureFlag('feature.curateGpt.enabled')

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['subject', subjectId],
    queryFn: () => getSubject(subjectId ?? ''),
    enabled: Boolean(subjectId),
  })

  // Hook must be called unconditionally before early returns
  const timeline = useTimelineEvents('subject', subjectId ?? '')

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
        title="Subject unavailable"
        description={(error as Error)?.message ?? 'Subject record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['subject', subjectId] })}
      />
    )
  }

  const consentVariant = data.consent === 'granted' ? 'success' : data.consent === 'restricted' ? 'warning' : 'outline'
  const allowEdit = canEdit(role)
  const allowAdmin = isAdmin(role)

  const overview = (
    <div className="space-y-6">
      <div className="grid gap-4 lg:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Profile</CardTitle>
            <CardDescription>Identity, cohort, and consent posture.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4 text-sm text-slate-600">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Species</p>
                <p className="font-semibold text-slate-700">{data.species}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Cohort</p>
                <p className="font-semibold text-slate-700">{data.cohort ?? '—'}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Strain</p>
                <p className="font-semibold text-slate-700">{data.strain ?? '—'}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Sex</p>
                <p className="font-semibold text-slate-700">{data.sex ?? '—'}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Status</p>
                <p className="font-semibold text-slate-700">{data.status}</p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Consent</p>
                <p className="font-semibold text-slate-700">{data.consent}</p>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Lifecycle</CardTitle>
            <CardDescription>Registry timestamps.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-3 text-sm text-slate-600">
            <p>Born {data.birthAt ? formatDate(data.birthAt) : 'Unknown'}</p>
            <p>Created {formatDate(data.createdAt)}</p>
            <p>Updated {formatDate(data.updatedAt)}</p>
          </CardContent>
        </Card>
      </div>

      <RestrictedActions
        title="Restricted action"
        description="Consent overrides require administrative approval."
        actionLabel="Request consent override"
        disabled={!allowAdmin}
        onAction={() =>
          toast({
            title: 'Override queued',
            description: 'Compliance review required before changing consent.',
          })
        }
      />
    </div>
  )

  const cageTab = (
    <Card>
      <CardHeader>
        <CardTitle>Cage assignment</CardTitle>
        <CardDescription>Live housing context and quick access.</CardDescription>
      </CardHeader>
      <CardContent className="space-y-3 text-sm text-slate-600">
        {data.cageId ? (
          <>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Cage</p>
              <p className="font-semibold text-slate-800">{data.cageLabel ?? data.cageId}</p>
              <p className="text-xs text-slate-500">{data.cageId}</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Location</p>
              <p className="font-semibold text-slate-800">{data.cageRoom ?? 'Unknown room'}</p>
              <p className="text-xs text-slate-500">{data.cageRack ?? 'Unknown rack'}</p>
            </div>
            <Button variant="outline" size="sm" asChild>
              <Link to={`/cages/${data.cageId}`}>Open cage</Link>
            </Button>
          </>
        ) : (
          <p>No cage assignment recorded for this subject.</p>
        )}
      </CardContent>
    </Card>
  )

  const timelineTab = (
    <Card>
      <CardHeader>
        <CardTitle>Assay timeline</CardTitle>
        <CardDescription>Horizontal timeline of subject assay and audit activity.</CardDescription>
      </CardHeader>
      <CardContent>
        {timeline.isError ? (
          <EmptyState
            title="Timeline unavailable"
            description={timeline.error?.message ?? 'Unable to load timeline.'}
            actionLabel="Retry"
            onAction={timeline.refetch}
          />
        ) : timeline.isLoading ? (
          <Skeleton className="h-40 w-full" />
        ) : (
          <AssayTimeline events={timeline.events} />
        )}
      </CardContent>
    </Card>
  )

  const isZebrafish = data.species.toLowerCase() === 'zebrafish'

  const tabs = [
    { id: 'overview', label: 'Overview', content: overview },
  ]

  if (isZebrafish) {
    tabs.push({
      id: 'batches',
      label: 'Batches & Imaging',
      content: (
        <div className="space-y-6">
          <HousingBatches />
          <ImagingMocks />
        </div>
      ),
    })
  } else {
    tabs.push({ id: 'cage', label: 'Cage', content: cageTab })
  }

  tabs.push({
    id: 'timeline',
    label: 'Timeline',
    content: (
      <Feature flag="timeline.enabled" fallback={<p className="text-sm text-slate-500">Timeline is disabled.</p>}>
        {timelineTab}
      </Feature>
    ),
  })

  if (curateGptEnabled) {
    tabs.push({
      id: 'curation',
      label: 'Curation',
      content: <SubjectCurationPanel subjectId={data.id} />,
    })
  }

  tabs.push({ id: 'audit', label: 'Audit', content: <ResourceAuditPanel resourceType="subject" resourceId={data.id} /> })

  return (
    <DetailLayout
      title={data.label}
      subtitle={data.id}
      backLink="/subjects"
      backLabel="Subjects"
      actions={
        <div className="flex flex-wrap items-center gap-2">
          <Badge variant={consentVariant}>{data.consent}</Badge>
          <Button variant="outline" size="sm" onClick={() => exportJson(data, `subject-${data.id}`)}>
            Export JSON
          </Button>
          {allowEdit ? (
            <Button variant="outline" size="sm" asChild>
              <Link to={`/subjects/${data.id}/edit`}>
                <Pencil className="h-4 w-4" />
                Edit
              </Link>
            </Button>
          ) : null}
        </div>
      }
      tabs={tabs}
    />
  )
}
