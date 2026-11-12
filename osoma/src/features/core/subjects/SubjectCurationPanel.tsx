import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  acceptSubjectCurationSuggestion,
  getSubjectCurationSuggestions,
  rejectSubjectCurationSuggestion,
  requestSubjectCurationSuggestions,
  type SubjectCurationSuggestion,
} from './subject.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'

const taskLabels: Record<SubjectCurationSuggestion['taskType'], string> = {
  missing_required_field: 'Missing field',
  value_normalization: 'Normalization',
  ontology_candidate: 'Ontology candidate',
  cross_field_consistency: 'Consistency check',
}

const statusLabels: Record<SubjectCurationSuggestion['status'], string> = {
  pending_review: 'Pending review',
  accepted: 'Accepted',
  rejected: 'Rejected',
  failed: 'Failed',
}

const statusVariants: Record<SubjectCurationSuggestion['status'], 'outline' | 'success' | 'warning' | 'critical'> = {
  pending_review: 'outline',
  accepted: 'success',
  rejected: 'warning',
  failed: 'critical',
}

const formatValue = (value: unknown) => {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'true' : 'false'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

export function SubjectCurationPanel({ subjectId }: { subjectId: string }) {
  const queryClient = useQueryClient()
  const { toast } = useToast()
  const suggestionsQuery = useQuery({
    queryKey: ['subject-curation-suggestions', subjectId],
    queryFn: () => getSubjectCurationSuggestions(subjectId),
    enabled: Boolean(subjectId),
  })

  const refreshSubject = () => {
    queryClient.invalidateQueries({ queryKey: ['subject', subjectId] })
    queryClient.invalidateQueries({ queryKey: ['subject-curation-suggestions', subjectId] })
  }

  const suggestMutation = useMutation({
    mutationFn: () => requestSubjectCurationSuggestions(subjectId),
    onSuccess: (suggestions) => {
      refreshSubject()
      toast({
        title: 'Curation suggestions refreshed',
        description: suggestions.length
          ? `${suggestions.length} review item${suggestions.length === 1 ? '' : 's'} available.`
          : 'No new suggestions were generated.',
      })
    },
  })

  const acceptMutation = useMutation({
    mutationFn: acceptSubjectCurationSuggestion,
    onSuccess: () => {
      refreshSubject()
      toast({ title: 'Suggestion accepted', description: 'Canonical subject metadata updated through review flow.' })
    },
  })

  const rejectMutation = useMutation({
    mutationFn: rejectSubjectCurationSuggestion,
    onSuccess: () => {
      refreshSubject()
      toast({ title: 'Suggestion rejected', description: 'The review decision was recorded for audit.' })
    },
  })

  return (
    <Card>
      <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <CardTitle>LLM curation review</CardTitle>
          <CardDescription>
            Suggestions stay separate from the subject record until a curator accepts them.
          </CardDescription>
        </div>
        <Button
          size="sm"
          onClick={() => suggestMutation.mutate()}
          disabled={suggestMutation.isPending}
        >
          {suggestMutation.isPending ? 'Generating…' : 'Generate suggestions'}
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        {suggestionsQuery.isLoading ? (
          <div className="space-y-3">
            <Skeleton className="h-24 w-full" />
            <Skeleton className="h-24 w-full" />
          </div>
        ) : suggestionsQuery.isError ? (
          <EmptyState
            title="Suggestions unavailable"
            description={suggestionsQuery.error instanceof Error ? suggestionsQuery.error.message : 'Unable to load suggestions.'}
            actionLabel="Retry"
            onAction={() => suggestionsQuery.refetch()}
          />
        ) : suggestionsQuery.data && suggestionsQuery.data.length > 0 ? (
          suggestionsQuery.data.map((suggestion) => {
            const isPending = suggestion.status === 'pending_review'
            return (
              <div key={suggestion.id} className="rounded-xl border border-slate-200 p-4">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge variant="outline">{taskLabels[suggestion.taskType]}</Badge>
                  <Badge variant={statusVariants[suggestion.status]}>{statusLabels[suggestion.status]}</Badge>
                  <span className="text-xs uppercase tracking-[0.2em] text-slate-400">{suggestion.fieldName}</span>
                </div>
                <div className="mt-3 grid gap-3 md:grid-cols-2">
                  <div className="rounded-lg bg-slate-50 p-3">
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Current value</p>
                    <p className="mt-1 font-medium text-slate-700">{formatValue(suggestion.currentValue)}</p>
                  </div>
                  <div className="rounded-lg bg-slate-50 p-3">
                    <p className="text-xs uppercase tracking-[0.2em] text-slate-400">Proposed value</p>
                    <p className="mt-1 font-medium text-slate-700">{formatValue(suggestion.proposedValue)}</p>
                  </div>
                </div>
                <div className="mt-3 space-y-2 text-sm text-slate-600">
                  <p>{suggestion.reason}</p>
                  <div className="flex flex-wrap gap-4 text-xs text-slate-500">
                    <span>Provider: {suggestion.providerName}</span>
                    <span>Confidence: {suggestion.confidence != null ? `${Math.round(suggestion.confidence * 100)}%` : 'Uncertain'}</span>
                    {suggestion.ontologyId ? <span>Ontology: {suggestion.ontologyId}</span> : null}
                    {suggestion.warningCode ? <span>Warning: {suggestion.warningCode}</span> : null}
                    {suggestion.failureDetails?.error ? <span>Failure: {String(suggestion.failureDetails.error)}</span> : null}
                    {suggestion.reviewedBy ? <span>Reviewed by: {suggestion.reviewedBy}</span> : null}
                  </div>
                </div>
                {isPending ? (
                  <div className="mt-4 flex flex-wrap gap-2">
                    <Button
                      size="sm"
                      onClick={() => acceptMutation.mutate(suggestion.id)}
                      disabled={acceptMutation.isPending || rejectMutation.isPending}
                    >
                      Accept
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => rejectMutation.mutate(suggestion.id)}
                      disabled={acceptMutation.isPending || rejectMutation.isPending}
                    >
                      Reject
                    </Button>
                  </div>
                ) : null}
              </div>
            )
          })
        ) : (
          <EmptyState
            title="No review items yet"
            description="Generate suggestions to see missing fields, normalizations, ontology candidates, and consistency checks."
            actionLabel="Generate suggestions"
            onAction={() => suggestMutation.mutate()}
          />
        )}
      </CardContent>
    </Card>
  )
}
