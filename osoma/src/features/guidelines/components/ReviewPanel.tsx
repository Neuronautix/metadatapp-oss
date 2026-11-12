import { useState } from 'react'
import { CheckCircle2, ChevronDown, Clock, MessageSquareWarning, ShieldQuestion } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { cn } from '@/lib/utils.ts'
import type { GuidelineReportReview } from '../guidelines.types.ts'

const STATUS_META: Record<
  GuidelineReportReview['status'],
  { label: string; icon: typeof CheckCircle2; variant: 'success' | 'warning' | 'outline' }
> = {
  not_reviewed: { label: 'Not yet reviewed', icon: ShieldQuestion, variant: 'outline' },
  approved: { label: 'Approved', icon: CheckCircle2, variant: 'success' },
  changes_requested: { label: 'Changes requested', icon: MessageSquareWarning, variant: 'warning' },
}

type Props = {
  review: GuidelineReportReview
  submitting: boolean
  onDecide: (decision: 'approved' | 'changes_requested', note: string) => void
}

/**
 * Report-level QC sign-off: the final human checkpoint before a compliance
 * report is considered official. Field-level review (GuidelineFieldRow) covers
 * individual values; this covers the report as a whole, and its history is the
 * audit trail behind every "Approved by X on Y" stamp on the exported report.
 */
export function ReviewPanel({ review, submitting, onDecide }: Props) {
  const [note, setNote] = useState('')
  const [historyOpen, setHistoryOpen] = useState(false)
  const status = STATUS_META[review.status]
  const StatusIcon = status.icon

  const decide = (decision: 'approved' | 'changes_requested') => {
    onDecide(decision, note)
    setNote('')
  }

  return (
    <Card data-testid="review-panel">
      <CardHeader>
        <CardTitle className="flex items-center justify-between gap-2">
          <span>Quality control sign-off</span>
          <Badge variant={status.variant} className="flex items-center gap-1" data-testid="review-status">
            <StatusIcon className="h-3.5 w-3.5" aria-hidden />
            {status.label}
          </Badge>
        </CardTitle>
      </CardHeader>
      <CardContent className="space-y-3">
        {review.reviewedBy ? (
          <p className="text-xs text-muted">
            {review.status === 'approved' ? 'Approved' : 'Changes requested'} by{' '}
            <span className="font-medium text-ink">{review.reviewedBy}</span>
            {review.reviewedAt ? <> on {new Date(review.reviewedAt).toLocaleString()}</> : null}
            {review.note ? <span className="italic"> — “{review.note}”</span> : null}
          </p>
        ) : (
          <p className="text-xs text-muted">
            Approve the report once every field has been filled and checked, or request changes with a note
            for the person filling it.
          </p>
        )}

        <Textarea
          aria-label="Review note"
          placeholder="Optional note for this decision…"
          value={note}
          onChange={(event) => setNote(event.target.value)}
          rows={2}
        />

        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            size="sm"
            onClick={() => decide('approved')}
            disabled={submitting}
            data-testid="approve-report"
          >
            <CheckCircle2 className="mr-1 h-3.5 w-3.5" aria-hidden />
            Approve report
          </Button>
          <Button
            type="button"
            size="sm"
            variant="outline"
            onClick={() => decide('changes_requested')}
            disabled={submitting}
            data-testid="request-changes"
          >
            <MessageSquareWarning className="mr-1 h-3.5 w-3.5" aria-hidden />
            Request changes
          </Button>
        </div>

        {review.history.length > 0 ? (
          <div>
            <button
              type="button"
              aria-expanded={historyOpen}
              onClick={() => setHistoryOpen((v) => !v)}
              className="flex items-center gap-1.5 text-xs font-medium text-muted hover:text-ink"
              data-testid="review-history-toggle"
            >
              <Clock className="h-3.5 w-3.5" aria-hidden />
              Audit history ({review.history.length})
              <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', historyOpen && 'rotate-180')} aria-hidden />
            </button>
            {historyOpen ? (
              <ul className="mt-2 space-y-1.5" data-testid="review-history">
                {review.history.map((event, index) => (
                  <li key={`${event.action}-${index}`} className="text-xs text-muted">
                    <span className="font-medium text-ink">{event.actor}</span> {describeAction(event.action)}
                    {event.fieldId ? (
                      <>
                        {' '}
                        <span className="font-mono">{event.fieldId}</span>
                      </>
                    ) : null}{' '}
                    — {new Date(event.occurredAt).toLocaleString()}
                    {event.note ? <span className="italic"> (“{event.note}”)</span> : null}
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
        ) : null}
      </CardContent>
    </Card>
  )
}

function describeAction(action: string): string {
  switch (action) {
    case 'report_approved':
      return 'approved the report'
    case 'report_changes_requested':
      return 'requested changes'
    case 'field_reviewed':
      return 'marked reviewed'
    case 'field_unreviewed':
      return 'cleared review on'
    case 'field_value_changed':
      return 'edited (review reset)'
    default:
      return action
  }
}
