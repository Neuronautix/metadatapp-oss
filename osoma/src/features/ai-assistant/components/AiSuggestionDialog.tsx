import { useMemo, useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Loader2, Sparkles } from 'lucide-react'
import { requestAiSuggestion, getAiStatus } from '../assistant.api'
import type { AiSuggestAction, AiSuggestResponse } from '../assistant.types'
import { AiStatusBadge } from './AiStatusBadge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'

type AiSuggestionDialogProps = {
  triggerLabel: string
  title: string
  description: string
  action: AiSuggestAction
  contextType: string
  buildContext: () => Record<string, unknown>
  promptLabel?: string
  promptPlaceholder?: string
  initialPrompt?: string
  onApply?: (response: AiSuggestResponse) => void
  applyLabel?: string
}

export function AiSuggestionDialog({
  triggerLabel,
  title,
  description,
  action,
  contextType,
  buildContext,
  promptLabel,
  promptPlaceholder,
  initialPrompt = '',
  onApply,
  applyLabel = 'Apply draft',
}: AiSuggestionDialogProps) {
  const [open, setOpen] = useState(false)
  const [prompt, setPrompt] = useState(initialPrompt)

  const statusQuery = useQuery({
    queryKey: ['ai-status'],
    queryFn: getAiStatus,
    staleTime: 30_000,
  })

  const suggestionMutation = useMutation({
    mutationFn: () =>
      requestAiSuggestion({
        action,
        contextType,
        context: buildContext(),
        prompt: prompt.trim() || undefined,
      }),
  })

  const response = suggestionMutation.data
  const canApply = Boolean(onApply && response?.status.available)
  const hasPrompt = useMemo(() => Boolean(promptLabel), [promptLabel])

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" size="sm" className="gap-2">
          <Sparkles className="h-4 w-4" />
          {triggerLabel}
        </Button>
      </DialogTrigger>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>

        {statusQuery.data ? (
          <div className="rounded-xl border border-line bg-surface p-3 text-sm text-muted">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <span>Provider status</span>
              <AiStatusBadge status={statusQuery.data.status} />
            </div>
            <p className="mt-2 text-xs">{statusQuery.data.status.message}</p>
          </div>
        ) : null}

        {hasPrompt ? (
          <div className="space-y-2">
            <label htmlFor="ai-suggestion-prompt" className="text-sm font-medium text-ink">{promptLabel}</label>
            <Input
              id="ai-suggestion-prompt"
              value={prompt}
              onChange={(event) => setPrompt(event.target.value)}
              placeholder={promptPlaceholder}
            />
          </div>
        ) : null}

        <div className="flex justify-end">
          <Button onClick={() => suggestionMutation.mutate()} disabled={suggestionMutation.isPending}>
            {suggestionMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
            Generate AI preview
          </Button>
        </div>

        {suggestionMutation.isError ? (
          <Card className="space-y-2 border-error/30 bg-error/5 p-4 text-sm text-error">
            <p className="font-medium">AI preview unavailable</p>
            <p>{suggestionMutation.error instanceof Error ? suggestionMutation.error.message : 'Unable to generate AI output.'}</p>
          </Card>
        ) : null}

        {response ? (
          <Card className="space-y-4 p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p className="text-sm font-semibold text-ink">AI-generated preview</p>
                <p className="text-xs text-muted">Review before applying. Nothing is written automatically.</p>
              </div>
              <AiStatusBadge status={response.status} />
            </div>

            <div className="rounded-xl bg-surface-2 p-3 text-sm text-ink">
              <p>{response.summary}</p>
            </div>

            {response.suggestions.length ? (
              <div className="space-y-3">
                {response.suggestions.map((suggestion) => (
                  <div key={`${suggestion.field}-${suggestion.label}`} className="rounded-xl border border-line p-3">
                    <div className="flex items-center justify-between gap-3">
                      <p className="font-medium text-ink">{suggestion.label}</p>
                      <span className="text-xs uppercase tracking-[0.2em] text-muted">{suggestion.field}</span>
                    </div>
                    <p className="mt-2 rounded-lg bg-surface-2 p-2 text-sm text-ink">{suggestion.preview}</p>
                    <p className="mt-2 text-xs text-muted">{suggestion.reason}</p>
                  </div>
                ))}
              </div>
            ) : null}

            {Object.keys(response.structuredOutput).length ? (
              <div className="space-y-2">
                <p className="text-sm font-medium text-ink">Structured output</p>
                <pre className="overflow-x-auto rounded-xl bg-slate-950 p-3 text-xs text-slate-50">
                  {JSON.stringify(response.structuredOutput, null, 2)}
                </pre>
              </div>
            ) : null}

            {response.provenance.length ? (
              <div className="space-y-2">
                <p className="text-sm font-medium text-ink">Provenance</p>
                <div className="space-y-2">
                  {response.provenance.map((item) => (
                    <div key={`${item.label}-${item.value}`} className="rounded-xl border border-line bg-surface-2 p-3 text-xs text-muted">
                      <p className="font-medium text-ink">{item.label}</p>
                      <p>{item.value}</p>
                      {item.detail ? <p className="mt-1">{item.detail}</p> : null}
                    </div>
                  ))}
                </div>
              </div>
            ) : null}

            {response.warnings.length ? (
              <div className="rounded-xl border border-warning/30 bg-warning/5 p-3 text-xs text-warning">
                {response.warnings.join(' ')}
              </div>
            ) : null}

            <p className="text-xs text-muted">Trace ID: {response.trace.traceId}</p>
          </Card>
        ) : null}

        <DialogFooter>
          {canApply ? (
            <Button
              onClick={() => {
                if (response) {
                  onApply?.(response)
                  setOpen(false)
                }
              }}
            >
              {applyLabel}
            </Button>
          ) : null}
          <Button variant="outline" onClick={() => setOpen(false)}>
            Close
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
