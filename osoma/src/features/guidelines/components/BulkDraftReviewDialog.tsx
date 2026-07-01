import { useMemo, useState } from 'react'
import { Check, X, Sparkles, Info } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import type { BulkAiDraft, BulkAiDraftItem } from '../guidelines.types.ts'

type RowState = 'pending' | 'accepted' | 'rejected'

type Props = {
  draft: BulkAiDraft
  /** Persist a single accepted draft (resolves once saved + reports invalidated). */
  onAccept: (fieldId: string, value: string) => Promise<void>
  onClose: () => void
}

/**
 * The bulk auto-fill review queue. Nothing is saved without an explicit Accept:
 * each drafted field carries its value (editable), rationale, AI citations, and
 * deterministic suggestions. When AI is unavailable across the board the drafts'
 * `available` is false, but their `suggestions` still make the queue actionable.
 */
export function BulkDraftReviewDialog({ draft, onAccept, onClose }: Props) {
  const { toast } = useToast()
  const [values, setValues] = useState<Record<string, string>>(() =>
    Object.fromEntries(draft.drafted.map((item) => [item.fieldId, item.value])),
  )
  const [states, setStates] = useState<Record<string, RowState>>(() =>
    Object.fromEntries(draft.drafted.map((item) => [item.fieldId, 'pending' as RowState])),
  )
  const [savingFieldId, setSavingFieldId] = useState<string | null>(null)

  // No drafted field reported AI availability → deterministic-only mode.
  const aiUnavailable = useMemo(
    () => draft.drafted.length > 0 && draft.drafted.every((item) => !item.available),
    [draft.drafted],
  )

  const accept = async (item: BulkAiDraftItem) => {
    const value = values[item.fieldId] ?? ''
    if (value.trim() === '') {
      toast({ title: 'Nothing to save', description: 'Enter a value before accepting.' })
      return
    }
    setSavingFieldId(item.fieldId)
    try {
      await onAccept(item.fieldId, value)
      setStates((prev) => ({ ...prev, [item.fieldId]: 'accepted' }))
    } catch {
      toast({ title: 'Save failed', description: `Could not save ${item.fieldId}.` })
    } finally {
      setSavingFieldId(null)
    }
  }

  const reject = (fieldId: string) => {
    setStates((prev) => ({ ...prev, [fieldId]: 'rejected' }))
  }

  const acceptAll = async () => {
    for (const item of draft.drafted) {
      if (states[item.fieldId] !== 'pending') continue
      if ((values[item.fieldId] ?? '').trim() === '') continue
      // Sequential so each save lands and the crosswalk re-derives in order.
      await accept(item)
    }
  }

  return (
    <Dialog open onOpenChange={(next) => (next ? undefined : onClose())}>
      <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto" data-testid="bulk-draft-review">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-brand" aria-hidden />
            Review auto-filled drafts
          </DialogTitle>
          <DialogDescription>
            {draft.drafted.length} field{draft.drafted.length === 1 ? '' : 's'} drafted. Nothing is saved
            until you accept it.
          </DialogDescription>
        </DialogHeader>

        {aiUnavailable ? (
          <p
            className="flex items-center gap-1.5 rounded-lg bg-warning/10 p-2 text-xs text-warning"
            data-testid="bulk-ai-unavailable"
          >
            <Info className="h-3.5 w-3.5" aria-hidden />
            AI unavailable — showing deterministic suggestions.
          </p>
        ) : null}

        {draft.capped ? (
          <p className="text-xs text-muted" data-testid="bulk-capped">
            Output was capped — re-run after accepting to draft the remainder.
          </p>
        ) : null}

        {draft.skipped.length > 0 ? (
          <p className="text-xs text-muted" data-testid="bulk-skipped">
            Skipped {draft.skipped.length} field{draft.skipped.length === 1 ? '' : 's'}:{' '}
            <span className="font-mono">{draft.skipped.join(', ')}</span>
          </p>
        ) : null}

        <div className="flex justify-end">
          <Button type="button" size="sm" onClick={acceptAll} data-testid="bulk-accept-all">
            <Check className="mr-1 h-3.5 w-3.5" aria-hidden />
            Accept all
          </Button>
        </div>

        <div className="space-y-3">
          {draft.drafted.map((item) => {
            const state = states[item.fieldId]
            return (
              <div
                key={item.fieldId}
                data-testid={`bulk-draft-${item.fieldId}`}
                className="space-y-2 rounded-xl border border-line bg-surface p-3"
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="font-mono text-sm font-semibold text-ink">{item.fieldId}</span>
                  {state === 'accepted' ? (
                    <Badge variant="success" data-testid={`bulk-status-${item.fieldId}`}>
                      Accepted
                    </Badge>
                  ) : state === 'rejected' ? (
                    <Badge variant="outline" data-testid={`bulk-status-${item.fieldId}`}>
                      Rejected
                    </Badge>
                  ) : null}
                </div>

                {state === 'rejected' ? null : (
                  <>
                    <Textarea
                      aria-label={`Draft value for ${item.fieldId}`}
                      value={values[item.fieldId] ?? ''}
                      onChange={(event) =>
                        setValues((prev) => ({ ...prev, [item.fieldId]: event.target.value }))
                      }
                      rows={2}
                    />

                    {item.rationale ? (
                      <p className="text-[11px] italic text-muted">{item.rationale}</p>
                    ) : null}

                    {item.usedEvidence.length > 0 ? (
                      <div className="space-y-1" data-testid={`bulk-citations-${item.fieldId}`}>
                        <p className="text-[11px] font-semibold text-ink">Grounded in:</p>
                        <ul className="space-y-0.5">
                          {item.usedEvidence.map((evidence, index) => (
                            <li key={`${evidence.source}-${index}`} className="text-[11px] text-muted">
                              <span className="font-medium">{evidence.label}</span> —{' '}
                              {evidence.url ? (
                                <a
                                  href={evidence.url}
                                  target="_blank"
                                  rel="noreferrer"
                                  className="underline hover:text-ink"
                                  aria-label={`Open standard: ${evidence.provenance}`}
                                >
                                  {evidence.provenance}
                                </a>
                              ) : (
                                evidence.provenance
                              )}
                            </li>
                          ))}
                        </ul>
                      </div>
                    ) : null}

                    {item.suggestions.length > 0 ? (
                      <div
                        className="flex flex-wrap gap-1.5"
                        data-testid={`bulk-suggestions-${item.fieldId}`}
                      >
                        {item.suggestions.map((suggestion, index) => (
                          <Button
                            key={`${suggestion.source}-${index}`}
                            type="button"
                            size="sm"
                            variant="ghost"
                            title={suggestion.provenance}
                            onClick={() =>
                              setValues((prev) => ({ ...prev, [item.fieldId]: suggestion.value }))
                            }
                          >
                            Use: {suggestion.value}
                          </Button>
                        ))}
                      </div>
                    ) : null}

                    <div className="flex gap-2">
                      <Button
                        type="button"
                        size="sm"
                        onClick={() => accept(item)}
                        disabled={savingFieldId === item.fieldId || state === 'accepted'}
                        data-testid={`bulk-accept-${item.fieldId}`}
                      >
                        <Check className="mr-1 h-3.5 w-3.5" aria-hidden />
                        Accept
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => reject(item.fieldId)}
                      >
                        <X className="mr-1 h-3.5 w-3.5" aria-hidden />
                        Reject
                      </Button>
                    </div>
                  </>
                )}
              </div>
            )
          })}
        </div>

        <div className="flex justify-end pt-2">
          <Button type="button" variant="outline" size="sm" onClick={onClose}>
            Done
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
