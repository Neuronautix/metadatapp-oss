import { Link, useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Pencil } from 'lucide-react'
import { getAtmpStudy } from './atmp.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Feature } from '@/feature-flags/Feature.tsx'
import { StudyOverviewCard } from './components/StudyOverviewCard.tsx'
import { COITimeline } from './components/COITimeline.tsx'
import { EndpointMatrix } from './components/EndpointMatrix.tsx'

export function AtmpStudyDetailPage() {
  const { investigationID } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['atmp-study', investigationID],
    queryFn: () => getAtmpStudy(investigationID ?? ''),
    enabled: Boolean(investigationID),
  })

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
        title="ATMP study unavailable"
        description={(error as Error)?.message ?? 'ATMP study record missing.'}
        actionLabel="Retry"
        onAction={() => queryClient.invalidateQueries({ queryKey: ['atmp-study', investigationID] })}
      />
    )
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <Button variant="ghost" size="sm" asChild>
            <Link to="/atmp">
              <ArrowLeft className="h-4 w-4" />
              Back
            </Link>
          </Button>
          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-slate-400">ATMP study</p>
            <h2 className="font-display text-2xl">{data.investigationID}</h2>
          </div>
        </div>
        <Feature flag="atmp.enabled">
          <Button variant="outline" size="sm" onClick={() => navigate(`/atmp/${data.investigationID}/edit`)}>
            <Pencil className="h-4 w-4" />
            Edit metadata
          </Button>
        </Feature>
      </div>

      <StudyOverviewCard study={data} />

      <Feature flag="atmp.enabled">
        <COITimeline entries={data.coiTimeline} />
      </Feature>

      <Feature flag="atmp.enabled">
        <EndpointMatrix entries={data.endpointMatrix} />
      </Feature>
    </div>
  )
}
