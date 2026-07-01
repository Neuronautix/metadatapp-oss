import { useMemo, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Download, FileArchive, Wand2 } from 'lucide-react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { downloadBlob } from '@/lib/download.ts'
import { getInvestigations } from '@/features/core/investigations/investigation.api.ts'
import { getStudies } from '@/features/core/studies/study.api.ts'
import { ConformanceDashboard } from './components/ConformanceDashboard.tsx'
import { BulkDraftReviewDialog } from './components/BulkDraftReviewDialog.tsx'
import { ReviewPanel } from './components/ReviewPanel.tsx'
import { brandingFor } from './guidelines-branding.ts'
import {
  aiDraftAll,
  aiDraftGuidelineField,
  downloadGuidelineReportMarkdown,
  downloadGuidelineReportZip,
  getCompletion,
  getConformance,
  getGuidelineReview,
  getGuidelineTemplate,
  reviewGuidelineField,
  setGuidelineField,
  submitGuidelineReviewDecision,
} from './guidelines.api.ts'
import type { AiDraftResult, BulkAiDraft, CompletionReport, GuidelineResourceType } from './guidelines.types.ts'

export function GuidelineDetailPage() {
  const { templateId } = useParams<{ templateId: string }>()
  const [searchParams] = useSearchParams()
  const { toast } = useToast()
  const queryClient = useQueryClient()

  const [investigationId, setInvestigationId] = useState<string | null>(
    searchParams.get('resType') === 'investigations' ? searchParams.get('resId') : null,
  )
  const [studyId, setStudyId] = useState<string | null>(
    searchParams.get('resType') === 'studies' ? searchParams.get('resId') : null,
  )
  const [savingFieldId, setSavingFieldId] = useState<string | null>(null)
  const [draftingFieldId, setDraftingFieldId] = useState<string | null>(null)
  const [reviewingFieldId, setReviewingFieldId] = useState<string | null>(null)
  const [bulkDraft, setBulkDraft] = useState<BulkAiDraft | null>(null)
  const [bulkDrafting, setBulkDrafting] = useState(false)
  const [submittingReportDecision, setSubmittingReportDecision] = useState(false)

  const resType: GuidelineResourceType = studyId ? 'studies' : 'investigations'
  const resId = studyId ?? investigationId
  const canQuery = Boolean(resId && templateId)

  const branding = brandingFor(templateId ?? '')
  const BrandIcon = branding.icon

  const investigationsQuery = useQuery({ queryKey: ['investigations'], queryFn: getInvestigations })
  const investigations = investigationsQuery.data?.data ?? []

  const studiesQuery = useQuery({
    queryKey: ['guidelines-studies', investigationId],
    queryFn: () => getStudies({ page: 1, pageSize: 100, investigation: investigationId ?? undefined }),
    enabled: Boolean(investigationId),
  })
  const studies = studiesQuery.data?.data ?? []

  const templateDetailQuery = useQuery({
    queryKey: ['guideline-template-detail', templateId],
    queryFn: () => getGuidelineTemplate(templateId!),
    enabled: Boolean(templateId),
  })
  const template = templateDetailQuery.data

  const conformanceKey = useMemo(
    () => ['guideline-conformance', resType, resId, templateId] as const,
    [resType, resId, templateId],
  )
  const completionKey = useMemo(
    () => ['guideline-completion', resType, resId, templateId] as const,
    [resType, resId, templateId],
  )
  const reviewKey = useMemo(() => ['guideline-review', resType, resId, templateId] as const, [resType, resId, templateId])

  const conformanceQuery = useQuery({
    queryKey: conformanceKey,
    queryFn: () => getConformance(resType, resId!, templateId!),
    enabled: canQuery,
  })

  const completionQuery = useQuery({
    queryKey: completionKey,
    queryFn: () => getCompletion(resType, resId!, templateId!),
    enabled: canQuery,
  })

  const reviewQuery = useQuery({
    queryKey: reviewKey,
    queryFn: () => getGuidelineReview(resType, resId!, templateId!),
    enabled: canQuery,
  })

  // Merge prompt/guidance from the resolved template into the completion fields.
  const completion: CompletionReport | undefined = useMemo(() => {
    const raw = completionQuery.data
    if (!raw) return undefined
    const meta = template?.requiredMetadata ?? []
    const promptById = new Map(meta.map((m) => [m.id, { prompt: m.prompt, guidance: m.guidance }]))
    return {
      ...raw,
      bySection: raw.bySection.map((section) => ({
        ...section,
        fields: section.fields.map((field) => {
          const hint = promptById.get(field.fieldId)
          return { ...field, prompt: hint?.prompt ?? field.prompt, guidance: hint?.guidance ?? field.guidance }
        }),
      })),
    }
  }, [completionQuery.data, template])

  const invalidateReports = () => {
    queryClient.invalidateQueries({ queryKey: conformanceKey })
    queryClient.invalidateQueries({ queryKey: completionKey })
  }

  const saveMutation = useMutation({
    mutationFn: ({ fieldId, value }: { fieldId: string; value: string }) =>
      setGuidelineField(resType, resId!, templateId!, fieldId, value),
    onMutate: ({ fieldId }) => setSavingFieldId(fieldId),
    onSuccess: () => {
      invalidateReports()
      toast({ title: 'Field saved' })
    },
    onError: () => toast({ title: 'Save failed', description: 'Could not save the field. Try again.' }),
    onSettled: () => setSavingFieldId(null),
  })

  const handleAiDraft = async (fieldId: string): Promise<AiDraftResult> => {
    if (!resId || !templateId) return null
    setDraftingFieldId(fieldId)
    try {
      const result = await aiDraftGuidelineField(resType, resId, templateId, fieldId)
      if (!result.available) {
        toast({ title: 'AI unavailable', description: result.message || 'Fill this field manually.' })
        return {
          value: '',
          rationale: '',
          available: false,
          usedEvidence: result.usedEvidence,
          suggestions: result.suggestions,
        }
      }
      return {
        value: result.value,
        rationale: result.rationale,
        available: true,
        usedEvidence: result.usedEvidence,
        suggestions: result.suggestions,
      }
    } catch {
      toast({ title: 'AI draft failed', description: 'Could not draft this field. Try again.' })
      return null
    } finally {
      setDraftingFieldId(null)
    }
  }

  const handleAiDraftAll = async () => {
    if (!resId || !templateId) return
    setBulkDrafting(true)
    try {
      const result = await aiDraftAll(resType, resId, templateId)
      setBulkDraft(result)
    } catch {
      toast({ title: 'Auto-fill failed', description: 'Could not draft missing fields. Try again.' })
    } finally {
      setBulkDrafting(false)
    }
  }

  const acceptDraft = async (fieldId: string, value: string) => {
    if (!resId || !templateId) return
    await setGuidelineField(resType, resId, templateId, fieldId, value)
    invalidateReports()
  }

  const handleReview = async (fieldId: string, status: 'reviewed' | 'draft') => {
    if (!resId || !templateId) return
    setReviewingFieldId(fieldId)
    try {
      await reviewGuidelineField(resType, resId, templateId, fieldId, status)
      invalidateReports()
    } catch {
      toast({ title: 'Review failed', description: `Could not update the review state for ${fieldId}.` })
    } finally {
      setReviewingFieldId(null)
    }
  }

  const handleReportDecision = async (decision: 'approved' | 'changes_requested', note: string) => {
    if (!resId || !templateId) return
    setSubmittingReportDecision(true)
    try {
      await submitGuidelineReviewDecision(resType, resId, templateId, decision, note || undefined)
      queryClient.invalidateQueries({ queryKey: reviewKey })
      invalidateReports()
      toast({ title: decision === 'approved' ? 'Report approved' : 'Changes requested' })
    } catch {
      toast({ title: 'Review failed', description: 'Could not record the sign-off decision. Try again.' })
    } finally {
      setSubmittingReportDecision(false)
    }
  }

  const downloadMarkdown = async () => {
    if (!resId || !templateId) return
    try {
      const md = await downloadGuidelineReportMarkdown(resType, resId, templateId)
      downloadBlob(new Blob([md], { type: 'text/markdown' }), `${templateId}-report.md`)
    } catch {
      toast({ title: 'Download failed', description: 'Could not generate the markdown report.' })
    }
  }

  const downloadZip = async () => {
    if (!resId || !templateId) return
    try {
      const blob = await downloadGuidelineReportZip(resType, resId, templateId)
      downloadBlob(blob, `${templateId}-report.zip`)
    } catch {
      toast({ title: 'Download failed', description: 'Could not generate the report bundle.' })
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <Link
          to="/guidelines"
          className="mb-2 inline-flex items-center gap-1 text-xs font-medium text-muted hover:text-ink"
        >
          <ArrowLeft className="h-3.5 w-3.5" aria-hidden />
          All guidelines
        </Link>
        <h1 className="flex items-center gap-2 font-display text-2xl font-semibold text-ink">
          <BrandIcon className={`h-6 w-6 ${branding.accent}`} aria-hidden />
          {template?.name ?? templateId}
        </h1>
        <p className="mt-1 text-sm text-muted">{template?.description ?? branding.tagline}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Target</CardTitle>
          <CardDescription>
            Choose an investigation. Optionally narrow to a single study under it.
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

      {!canQuery ? (
        <Card>
          <CardContent className="p-8 text-center text-sm text-muted">
            Select a target to see the live conformance dashboard.
          </CardContent>
        </Card>
      ) : conformanceQuery.isLoading || completionQuery.isLoading ? (
        <div className="space-y-3">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Skeleton className="h-24" />
            <Skeleton className="h-24" />
            <Skeleton className="h-24" />
            <Skeleton className="h-24" />
          </div>
          <Skeleton className="h-40" />
        </div>
      ) : conformanceQuery.data && completion ? (
        <>
          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              size="sm"
              onClick={handleAiDraftAll}
              disabled={bulkDrafting}
              data-testid="auto-fill-all"
            >
              <Wand2 className="mr-1 h-4 w-4" aria-hidden />
              {bulkDrafting ? 'Drafting…' : 'Auto-fill all missing'}
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={downloadMarkdown}>
              <Download className="mr-1 h-4 w-4" aria-hidden />
              Download report (.md)
            </Button>
            <Button type="button" variant="outline" size="sm" onClick={downloadZip}>
              <FileArchive className="mr-1 h-4 w-4" aria-hidden />
              Download report (.zip)
            </Button>
          </div>

          <ConformanceDashboard
            conformance={conformanceQuery.data}
            completion={completion}
            resType={resType}
            resId={resId!}
            templateId={templateId!}
            savingFieldId={savingFieldId}
            draftingFieldId={draftingFieldId}
            reviewingFieldId={reviewingFieldId}
            onSave={(fieldId, value) => saveMutation.mutate({ fieldId, value })}
            onAiDraft={handleAiDraft}
            onReview={handleReview}
          />

          {reviewQuery.data ? (
            <ReviewPanel
              review={reviewQuery.data}
              submitting={submittingReportDecision}
              onDecide={handleReportDecision}
            />
          ) : null}

          {bulkDraft ? (
            <BulkDraftReviewDialog draft={bulkDraft} onAccept={acceptDraft} onClose={() => setBulkDraft(null)} />
          ) : null}
        </>
      ) : (
        <Card>
          <CardContent className="p-8 text-center text-sm text-error">
            Could not load conformance for this template.
          </CardContent>
        </Card>
      )}
    </div>
  )
}
