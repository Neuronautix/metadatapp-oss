import { useState } from 'react'
import {
  CheckCircle2,
  AlertTriangle,
  XCircle,
  Sparkles,
  Save,
  Info,
  Sigma,
  History,
  Link as LinkIcon,
  ShieldCheck,
  FlaskConical,
  Boxes,
  BookOpen,
  ClipboardCheck,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { cn } from '@/lib/utils.ts'
import { getFieldEvidence } from '../guidelines.api.ts'
import type {
  AiDraftResult,
  CompletionField,
  ConformanceStatus,
  EvidenceItem,
  EvidenceSource,
  GuidelineResourceType,
  GuidelineSeverity,
  Suggestion,
} from '../guidelines.types.ts'

const STATUS_META: Record<
  ConformanceStatus,
  { label: string; icon: typeof CheckCircle2; variant: 'success' | 'warning' | 'critical' }
> = {
  satisfied: { label: 'Satisfied', icon: CheckCircle2, variant: 'success' },
  partial: { label: 'Partial', icon: AlertTriangle, variant: 'warning' },
  missing: { label: 'Missing', icon: XCircle, variant: 'critical' },
}

const SEVERITY_VARIANT: Record<GuidelineSeverity, 'critical' | 'warning' | 'outline'> = {
  high: 'critical',
  medium: 'warning',
  low: 'outline',
}

/** Per-source chip presentation: icon + a short, human label prefix. */
const SOURCE_META: Record<EvidenceSource, { icon: typeof Sigma; label: string }> = {
  computed: { icon: Sigma, label: 'auto-computed' },
  history: { icon: History, label: 'from history' },
  connected_link: { icon: LinkIcon, label: 'connected' },
  elabftw: { icon: FlaskConical, label: 'from elabFTW' },
  fair: { icon: ShieldCheck, label: 'FAIR' },
  entity: { icon: Boxes, label: 'from entity' },
  reference: { icon: BookOpen, label: 'Standard' },
}

/** A small provenance chip describing where a value / suggestion came from. */
function ProvenanceChip({ source, provenance }: { source: EvidenceSource; provenance: string }) {
  const meta = SOURCE_META[source] ?? SOURCE_META.entity
  const Icon = meta.icon
  return (
    <Badge variant="outline" className="flex items-center gap-1" data-testid={`provenance-chip-${source}`}>
      <Icon className="h-3 w-3" aria-hidden />
      <span>{provenance || meta.label}</span>
    </Badge>
  )
}

type Props = {
  field: CompletionField
  resType: GuidelineResourceType
  resId: string
  templateId: string
  saving: boolean
  drafting: boolean
  reviewing: boolean
  onSave: (fieldId: string, value: string) => void
  onAiDraft: (fieldId: string) => Promise<AiDraftResult>
  onReview: (fieldId: string, status: 'reviewed' | 'draft') => void
}

export function GuidelineFieldRow({
  field,
  resType,
  resId,
  templateId,
  saving,
  drafting,
  reviewing,
  onSave,
  onAiDraft,
  onReview,
}: Props) {
  const [value, setValue] = useState(field.value ?? '')
  const [draft, setDraft] = useState<{ value: string; rationale: string; usedEvidence: EvidenceItem[] } | null>(
    null,
  )
  // Only flips true once a draft attempt returns `available === false`.
  const [aiUnavailable, setAiUnavailable] = useState(false)
  // Deterministic one-click candidates — lazily fetched from the evidence endpoint
  // (or seeded from an ai-draft response).
  const [suggestions, setSuggestions] = useState<Suggestion[]>([])
  const [loadingEvidence, setLoadingEvidence] = useState(false)

  const status = STATUS_META[field.status]
  const StatusIcon = status.icon

  const loadEvidence = async () => {
    setLoadingEvidence(true)
    try {
      const evidence = await getFieldEvidence(resType, resId, templateId, field.fieldId)
      setSuggestions(evidence.suggestions)
    } finally {
      setLoadingEvidence(false)
    }
  }

  const handleDraft = async () => {
    const result = await onAiDraft(field.fieldId)
    if (!result) {
      setDraft(null)
      return
    }
    // Even when AI is unavailable the draft response carries deterministic
    // suggestions — surface them so the field stays one-click satisfiable.
    if (result.suggestions.length > 0) setSuggestions(result.suggestions)
    if (result.available === false) {
      setAiUnavailable(true)
      setDraft(null)
      return
    }
    setAiUnavailable(false)
    setDraft({ value: result.value, rationale: result.rationale, usedEvidence: result.usedEvidence })
  }

  const acceptDraft = () => {
    if (!draft) return
    setValue(draft.value)
    onSave(field.fieldId, draft.value)
    setDraft(null)
  }

  const useSuggestion = (suggestion: Suggestion) => {
    setValue(suggestion.value)
    onSave(field.fieldId, suggestion.value)
  }

  return (
    <div
      data-testid={`guideline-field-${field.fieldId}`}
      className="space-y-3 rounded-xl border border-line bg-surface p-4"
    >
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <span className="font-mono text-sm font-semibold text-ink">{field.fieldId}</span>
          <Badge variant={SEVERITY_VARIANT[field.severity]}>{field.severity}</Badge>
        </div>
        <Badge variant={status.variant} className="flex items-center gap-1">
          <StatusIcon className="h-3.5 w-3.5" aria-hidden />
          {status.label}
        </Badge>
      </div>

      {field.hasValue ? (
        <p
          className={cn(
            'flex items-center gap-1.5 text-xs',
            field.reviewStatus === 'reviewed' ? 'text-success' : 'text-muted',
          )}
          data-testid={`review-status-${field.fieldId}`}
        >
          <ClipboardCheck className="h-3.5 w-3.5" aria-hidden />
          {field.reviewStatus === 'reviewed' && field.reviewedBy
            ? `Reviewed by ${field.reviewedBy}${field.reviewedAt ? ` on ${new Date(field.reviewedAt).toLocaleDateString()}` : ''}`
            : 'Not yet reviewed'}
        </p>
      ) : null}

      {field.viaCrosswalk && field.satisfyingSibling ? (
        <p className="flex items-center gap-1.5 text-xs text-success">
          <Info className="h-3.5 w-3.5" aria-hidden />
          Satisfied by <span className="font-mono">{field.satisfyingSibling}</span> (via crosswalk)
        </p>
      ) : null}

      {/* Provenance chips: where a satisfied / suggested value came from. */}
      {suggestions.length > 0 ? (
        <div className="flex flex-wrap items-center gap-1.5" data-testid={`provenance-${field.fieldId}`}>
          {suggestions.map((suggestion, index) => (
            <ProvenanceChip
              key={`${suggestion.source}-${index}`}
              source={suggestion.source}
              provenance={suggestion.provenance}
            />
          ))}
        </div>
      ) : null}

      {field.prompt ? <p className="text-xs text-muted">{field.prompt}</p> : null}
      {field.guidance ? <p className="text-xs text-muted">{field.guidance}</p> : null}

      <Textarea
        aria-label={`Value for ${field.fieldId}`}
        value={value}
        onChange={(event) => setValue(event.target.value)}
        rows={2}
        placeholder="Enter a value…"
      />

      <div className="flex flex-wrap items-center gap-2">
        <Button
          type="button"
          size="sm"
          onClick={() => onSave(field.fieldId, value)}
          disabled={saving || value.trim() === ''}
        >
          <Save className="mr-1 h-3.5 w-3.5" aria-hidden />
          Save
        </Button>

        {aiUnavailable ? (
          <span className="text-xs italic text-muted">AI unavailable — fill manually</span>
        ) : (
          <Button type="button" size="sm" variant="outline" onClick={handleDraft} disabled={drafting}>
            <Sparkles className="mr-1 h-3.5 w-3.5" aria-hidden />
            AI draft
          </Button>
        )}

        {suggestions.length === 0 ? (
          <Button
            type="button"
            size="sm"
            variant="ghost"
            onClick={loadEvidence}
            disabled={loadingEvidence}
          >
            <Sigma className="mr-1 h-3.5 w-3.5" aria-hidden />
            {loadingEvidence ? 'Finding suggestions…' : 'Suggestions'}
          </Button>
        ) : null}

        {field.hasValue ? (
          <Button
            type="button"
            size="sm"
            variant={field.reviewStatus === 'reviewed' ? 'outline' : 'ghost'}
            onClick={() => onReview(field.fieldId, field.reviewStatus === 'reviewed' ? 'draft' : 'reviewed')}
            disabled={reviewing}
            data-testid={`review-toggle-${field.fieldId}`}
          >
            <ClipboardCheck className="mr-1 h-3.5 w-3.5" aria-hidden />
            {field.reviewStatus === 'reviewed' ? 'Unmark reviewed' : 'Mark reviewed'}
          </Button>
        ) : null}
      </div>

      {/* One-click deterministic suggestions — fill + save without an AI key. */}
      {suggestions.length > 0 ? (
        <div className="flex flex-wrap gap-2" data-testid={`suggestions-${field.fieldId}`}>
          {suggestions.map((suggestion, index) => {
            const meta = SOURCE_META[suggestion.source] ?? SOURCE_META.entity
            const Icon = meta.icon
            return (
              <Button
                key={`use-${suggestion.source}-${index}`}
                type="button"
                size="sm"
                variant="outline"
                onClick={() => useSuggestion(suggestion)}
                disabled={saving}
                title={suggestion.provenance}
              >
                <Icon className="mr-1 h-3.5 w-3.5" aria-hidden />
                Use: {suggestion.value}
              </Button>
            )
          })}
        </div>
      ) : null}

      {draft ? (
        <div
          data-testid={`ai-draft-${field.fieldId}`}
          className="space-y-2 rounded-lg border border-brand/40 bg-brand/5 p-3"
        >
          <p className="text-xs font-semibold text-ink">AI draft</p>
          <p className="text-sm text-ink">{draft.value}</p>
          <p className="text-[11px] italic text-muted">{draft.rationale}</p>

          {draft.usedEvidence.length > 0 ? (
            <div className="space-y-1" data-testid={`ai-citations-${field.fieldId}`}>
              <p className="text-[11px] font-semibold text-ink">Grounded in:</p>
              <ul className="space-y-0.5">
                {draft.usedEvidence.map((item, index) => (
                  <li
                    key={`${item.source}-${index}`}
                    className="flex items-center gap-1.5 text-[11px] text-muted"
                  >
                    <ProvenanceChip source={item.source} provenance={item.label} />
                    {item.url ? (
                      <a
                        href={item.url}
                        target="_blank"
                        rel="noreferrer"
                        className="underline hover:text-ink"
                        aria-label={`Open standard: ${item.provenance}`}
                      >
                        {item.provenance}
                      </a>
                    ) : (
                      <span>{item.provenance}</span>
                    )}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          <div className="flex gap-2">
            <Button type="button" size="sm" onClick={acceptDraft} disabled={saving}>
              Accept &amp; save
            </Button>
            <Button type="button" size="sm" variant="outline" onClick={() => setDraft(null)}>
              Dismiss
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  )
}
