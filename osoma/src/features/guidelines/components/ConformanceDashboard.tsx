import { useState } from 'react'
import { ChevronDown } from 'lucide-react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { cn } from '@/lib/utils.ts'
import { GuidelineFieldRow } from './GuidelineFieldRow.tsx'
import type { AiDraftResult, CompletionReport, ConformanceReport, GuidelineResourceType } from '../guidelines.types.ts'

type Props = {
  conformance: ConformanceReport
  completion: CompletionReport
  resType: GuidelineResourceType
  resId: string
  templateId: string
  savingFieldId: string | null
  draftingFieldId: string | null
  reviewingFieldId: string | null
  onSave: (fieldId: string, value: string) => void
  onAiDraft: (fieldId: string) => Promise<AiDraftResult>
  onReview: (fieldId: string, status: 'reviewed' | 'draft') => void
}

export function ConformanceDashboard({
  conformance,
  completion,
  resType,
  resId,
  templateId,
  savingFieldId,
  draftingFieldId,
  reviewingFieldId,
  onSave,
  onAiDraft,
  onReview,
}: Props) {
  const { score } = conformance
  // Top-level conformance totals only expose satisfied/total; partial + missing
  // come from the completion roll-up (the engine's enriched split).
  const satisfied = conformance.totals.satisfied
  const partial = completion.totals.partial
  const missing = completion.totals.missing

  return (
    <div className="space-y-4">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader>
            <CardTitle className="text-sm text-muted">FAIR {score.dimension}-dimension score</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="font-display text-3xl font-semibold text-ink" data-testid="conformance-score">
              {score.points}/{score.maxPoints}
            </p>
            <p className="text-xs text-muted">{Math.round(score.satisfiedPct)}% satisfied</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm text-muted">Satisfied</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="font-display text-3xl font-semibold text-success" data-testid="totals-satisfied">
              {satisfied}
            </p>
            <p className="text-xs text-muted">of {conformance.totals.total}</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm text-muted">Partial</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="font-display text-3xl font-semibold text-warning" data-testid="totals-partial">
              {partial}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle className="text-sm text-muted">Missing</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="font-display text-3xl font-semibold text-error" data-testid="totals-missing">
              {missing}
            </p>
          </CardContent>
        </Card>
      </div>

      {completion.totals.satisfiedViaCrosswalk > 0 ? (
        <p className="text-xs text-muted">
          {completion.totals.satisfiedViaCrosswalk} field
          {completion.totals.satisfiedViaCrosswalk === 1 ? '' : 's'} auto-satisfied via crosswalk.
        </p>
      ) : null}

      <div className="space-y-3">
        {(() => {
          // A field can legitimately belong to several sections (e.g. a PREPARE
          // field carrying both an arrive_section and a prepare_section), so the
          // same field id appears in multiple `bySection` buckets. Render its
          // editable row exactly ONCE — under the first section that owns it — so
          // two copies of the same field cannot hold divergent local edit state
          // (and so test ids / aria-labels stay unique). The section badge still
          // reflects full membership counts.
          const renderedFieldIds = new Set<string>()
          return completion.bySection.map((section, index) => {
            const rowFields = section.fields.filter((field) => {
              if (renderedFieldIds.has(field.fieldId)) return false
              renderedFieldIds.add(field.fieldId)
              return true
            })
            return (
              <SectionAccordion
                key={`${section.section}-${index}`}
                section={section}
                rowFields={rowFields}
                defaultOpen={index === 0}
                resType={resType}
                resId={resId}
                templateId={templateId}
                savingFieldId={savingFieldId}
                draftingFieldId={draftingFieldId}
                reviewingFieldId={reviewingFieldId}
                onSave={onSave}
                onAiDraft={onAiDraft}
                onReview={onReview}
              />
            )
          })
        })()}
      </div>
    </div>
  )
}

function SectionAccordion({
  section,
  rowFields,
  defaultOpen,
  resType,
  resId,
  templateId,
  savingFieldId,
  draftingFieldId,
  reviewingFieldId,
  onSave,
  onAiDraft,
  onReview,
}: {
  section: CompletionReport['bySection'][number]
  /** Fields to render an editable row for (deduped across sections). */
  rowFields: CompletionReport['bySection'][number]['fields']
  defaultOpen: boolean
  resType: GuidelineResourceType
  resId: string
  templateId: string
  savingFieldId: string | null
  draftingFieldId: string | null
  reviewingFieldId: string | null
  onSave: (fieldId: string, value: string) => void
  onAiDraft: (fieldId: string) => Promise<AiDraftResult>
  onReview: (fieldId: string, status: 'reviewed' | 'draft') => void
}) {
  const [open, setOpen] = useState(defaultOpen)

  return (
    <Card>
      <button
        type="button"
        aria-expanded={open}
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center justify-between gap-2 px-4 py-3 text-left"
      >
        <span className="font-display text-sm font-semibold text-ink">{section.section || 'Other'}</span>
        <span className="flex items-center gap-2">
          <Badge variant="outline">
            {section.satisfied}/{section.fields.length}
          </Badge>
          <ChevronDown className={cn('h-4 w-4 transition-transform', open && 'rotate-180')} aria-hidden />
        </span>
      </button>
      {open ? (
        <CardContent className="space-y-3 pt-0">
          {rowFields.map((field) => (
            <GuidelineFieldRow
              key={field.fieldId}
              field={field}
              resType={resType}
              resId={resId}
              templateId={templateId}
              saving={savingFieldId === field.fieldId}
              drafting={draftingFieldId === field.fieldId}
              reviewing={reviewingFieldId === field.fieldId}
              onSave={onSave}
              onAiDraft={onAiDraft}
              onReview={onReview}
            />
          ))}
        </CardContent>
      ) : null}
    </Card>
  )
}
