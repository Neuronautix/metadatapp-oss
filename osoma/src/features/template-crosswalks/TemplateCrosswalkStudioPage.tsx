import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  Boxes,
  CheckCircle2,
  Download,
  FileUp,
  Link as LinkIcon,
  Plus,
  Sparkles,
  Upload,
  XCircle,
} from 'lucide-react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import {
  FIELD_MAPPING_TYPES,
  useAcceptFieldCrosswalk,
  useCreateCde,
  useExportJsonLd,
  useExportRoCrate,
  useGetCrosswalk,
  useImportEln,
  useImportElnUrl,
  useListCrosswalks,
  useRejectFieldCrosswalk,
  useSearchCdes,
  useSuggestMappings,
  useUpdateFieldCrosswalk,
  useValidateCrosswalk,
  type CommonDataElement,
  type CrosswalkMappingType,
  type ExtractedTemplateField,
  type FieldCrosswalk,
  type MappingStatus,
  type TemplateFormCrosswalk,
  type ValidationReport,
} from './template-crosswalks.api.ts'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function statusVariant(status: MappingStatus): 'success' | 'warning' | 'critical' | 'outline' {
  switch (status) {
    case 'accepted':
      return 'success'
    case 'suggested':
      return 'warning'
    case 'rejected':
      return 'critical'
    default:
      return 'outline'
  }
}

function downloadJson(payload: unknown, filename: string) {
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/ld+json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

// ---------------------------------------------------------------------------
// LEFT panel — imported ELN template structure / extracted fields
// ---------------------------------------------------------------------------

function ExtractedFieldRow({
  field,
  selected,
  onSelect,
}: {
  field: ExtractedTemplateField
  selected: boolean
  onSelect: () => void
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={`w-full rounded-xl border px-3 py-2 text-left transition ${
        selected ? 'border-brand bg-surface-2' : 'border-line bg-surface hover:bg-surface-2'
      }`}
    >
      <div className="flex items-center justify-between gap-2">
        <span className="truncate text-sm font-medium text-ink">{field.label}</span>
        {field.mapped ? (
          <Badge variant="success">mapped</Badge>
        ) : (
          <Badge variant="outline">unmapped</Badge>
        )}
      </div>
      {field.jsonldPath ? (
        <p className="mt-1 truncate font-mono text-[11px] text-muted" title={field.jsonldPath}>
          {field.jsonldPath}
        </p>
      ) : null}
      <div className="mt-1 flex flex-wrap gap-2 text-[11px] text-muted">
        {field.datatype ? <span>type: {field.datatype}</span> : null}
        {field.unit ? <span>unit: {field.unit}</span> : null}
        {field.exampleValue ? <span className="truncate">value: {field.exampleValue}</span> : null}
      </div>
      {field.ontologyTerms.length > 0 ? (
        <div className="mt-1 flex flex-wrap gap-1">
          {field.ontologyTerms.map((term) => (
            <span
              key={term.iri}
              className="inline-flex items-center gap-1 rounded border border-violet-200 px-1.5 py-0.5 text-[10px] text-violet-700"
            >
              <LinkIcon className="h-2.5 w-2.5" />
              {term.label ?? term.iri}
            </span>
          ))}
        </div>
      ) : null}
    </button>
  )
}

function ImportPanel({
  onImported,
}: {
  onImported: () => void
}) {
  const { toast } = useToast()
  const [url, setUrl] = useState('')
  const importEln = useImportEln()
  const importUrl = useImportElnUrl()

  const handleFile = (file: File | undefined) => {
    if (!file) return
    importEln.mutate(file, {
      onSuccess: () => {
        toast({ title: 'Template imported', description: file.name })
        onImported()
      },
      onError: (err) =>
        toast({ title: 'Import failed', description: (err as Error).message, variant: 'error' }),
    })
  }

  const handleUrl = () => {
    if (!url.trim()) return
    importUrl.mutate(url.trim(), {
      onSuccess: () => {
        toast({ title: 'Template imported', description: url })
        setUrl('')
        onImported()
      },
      onError: (err) =>
        toast({ title: 'Import failed', description: (err as Error).message, variant: 'error' }),
    })
  }

  return (
    <div className="space-y-3 rounded-xl border border-dashed border-line p-3">
      <Label className="flex cursor-pointer items-center gap-2 text-sm">
        <FileUp className="h-4 w-4" />
        <span>Import .eln file</span>
        <input
          type="file"
          accept=".eln,application/zip"
          className="hidden"
          aria-label="Import .eln file"
          onChange={(e) => handleFile(e.target.files?.[0])}
        />
      </Label>
      <div className="flex items-center gap-2">
        <Input
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          placeholder="https://eln.community/record/..."
          aria-label="ELN record URL"
        />
        <Button size="sm" variant="outline" onClick={handleUrl} disabled={importUrl.isPending}>
          <Upload className="h-4 w-4" />
          URL
        </Button>
      </div>
      {importEln.isPending ? <p className="text-xs text-muted">Uploading…</p> : null}
    </div>
  )
}

// ---------------------------------------------------------------------------
// RIGHT panel — candidate CDEs
// ---------------------------------------------------------------------------

function CdeCandidatePanel({
  onPickCde,
  onCreateCde,
}: {
  onPickCde: (cde: CommonDataElement) => void
  onCreateCde: () => void
}) {
  const [query, setQuery] = useState('')
  const { data: candidates = [], isFetching } = useSearchCdes(query)

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-2">
        <h3 className="font-display text-sm font-semibold">Candidate CDEs</h3>
        <Button size="sm" variant="outline" onClick={onCreateCde}>
          <Plus className="h-4 w-4" />
          New CDE
        </Button>
      </div>
      <Input
        value={query}
        onChange={(e) => setQuery(e.target.value)}
        placeholder="Search CDEs…"
        aria-label="Search CDEs"
      />
      {isFetching ? <Skeleton className="h-16 w-full" /> : null}
      <div className="space-y-2">
        {candidates.map((cde) => (
          <div key={cde.id} className="rounded-xl border border-line bg-surface p-3">
            <div className="flex items-center justify-between gap-2">
              <span className="text-sm font-medium text-ink">{cde.label}</span>
              <Badge variant="outline">{cde.source}</Badge>
            </div>
            {cde.definition ? (
              <p className="mt-1 line-clamp-2 text-xs text-muted">{cde.definition}</p>
            ) : null}
            <div className="mt-1 flex flex-wrap gap-2 text-[11px] text-muted">
              {cde.datatype ? <span>type: {cde.datatype}</span> : null}
              {cde.unitConstraint ? <span>unit: {cde.unitConstraint}</span> : null}
              {cde.version ? <span>v{cde.version}</span> : null}
            </div>
            {cde.permissibleValues.length > 0 ? (
              <div className="mt-1 flex flex-wrap gap-1">
                {cde.permissibleValues.slice(0, 6).map((pv) => (
                  <span
                    key={pv.value}
                    className="rounded border border-line px-1.5 py-0.5 text-[10px] text-muted"
                  >
                    {pv.value}
                  </span>
                ))}
              </div>
            ) : null}
            {cde.ontologyMappings.length > 0 ? (
              <div className="mt-1 flex flex-wrap gap-1">
                {cde.ontologyMappings.map((om) => (
                  <span
                    key={om.iri}
                    className="inline-flex items-center gap-1 rounded border border-violet-200 px-1.5 py-0.5 text-[10px] text-violet-700"
                  >
                    <LinkIcon className="h-2.5 w-2.5" />
                    {om.label ?? om.iri}
                  </span>
                ))}
              </div>
            ) : null}
            <Button size="sm" className="mt-2 w-full" onClick={() => onPickCde(cde)}>
              Map to selected field
            </Button>
          </div>
        ))}
        {!isFetching && query && candidates.length === 0 ? (
          <p className="text-xs text-muted">No matching CDEs.</p>
        ) : null}
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// MIDDLE panel — field crosswalk editor
// ---------------------------------------------------------------------------

function FieldCrosswalkRow({
  fieldCrosswalk,
  crosswalkId,
  selected,
  onSelect,
}: {
  fieldCrosswalk: FieldCrosswalk
  crosswalkId: string
  selected: boolean
  onSelect: () => void
}) {
  const { toast } = useToast()
  const update = useUpdateFieldCrosswalk()
  const accept = useAcceptFieldCrosswalk()
  const reject = useRejectFieldCrosswalk()

  const cde = typeof fieldCrosswalk.cde === 'object' ? fieldCrosswalk.cde : null
  const [notes, setNotes] = useState(fieldCrosswalk.curatorNotes ?? '')

  const handleMappingType = (mappingType: CrosswalkMappingType) => {
    update.mutate({ id: fieldCrosswalk.id, patch: { mappingType } })
  }

  const saveNotes = () => {
    update.mutate(
      { id: fieldCrosswalk.id, patch: { curatorNotes: notes } },
      { onSuccess: () => toast({ title: 'Notes saved' }) },
    )
  }

  const elnLabel =
    typeof fieldCrosswalk.elnField === 'object' && fieldCrosswalk.elnField
      ? fieldCrosswalk.elnField.label
      : (fieldCrosswalk.elnPropertyId ?? fieldCrosswalk.elnJsonldPath ?? 'ELN field')

  return (
    <div
      data-crosswalk={crosswalkId}
      className={`rounded-xl border p-3 ${selected ? 'border-brand bg-surface-2' : 'border-line bg-surface'}`}
    >
      <button type="button" onClick={onSelect} className="flex w-full items-center justify-between gap-2 text-left">
        <span className="truncate text-sm font-medium text-ink">
          {elnLabel}
          <span className="px-1 text-muted">→</span>
          {cde ? cde.label : <span className="text-muted">unmapped</span>}
        </span>
        <Badge variant={statusVariant(fieldCrosswalk.mappingStatus)}>{fieldCrosswalk.mappingStatus}</Badge>
      </button>

      <div className="mt-2 flex flex-wrap items-center gap-2">
        <Label className="text-xs text-muted">Mapping type</Label>
        <select
          value={fieldCrosswalk.mappingType}
          onChange={(e) => handleMappingType(e.target.value as CrosswalkMappingType)}
          aria-label={`Mapping type for ${elnLabel}`}
          className="h-8 rounded-lg border border-line bg-surface px-2 text-xs text-ink focus:outline-none focus:ring-2 focus:ring-focus"
        >
          {FIELD_MAPPING_TYPES.map((type) => (
            <option key={type} value={type}>
              {type}
            </option>
          ))}
        </select>
        {typeof fieldCrosswalk.confidence === 'number' ? (
          <span className="text-[11px] text-muted">conf {(fieldCrosswalk.confidence * 100).toFixed(0)}%</span>
        ) : null}
      </div>

      {selected ? (
        <div className="mt-3 space-y-2 border-t border-line pt-3">
          <div className="grid grid-cols-2 gap-2 text-[11px] text-muted">
            <div>
              <span className="font-semibold">Datatype:</span>{' '}
              {cde?.datatype ?? '—'}
            </div>
            <div>
              <span className="font-semibold">Unit:</span> {cde?.unitConstraint ?? '—'}
            </div>
          </div>
          {cde && cde.permissibleValues.length > 0 ? (
            <div>
              <p className="text-[11px] font-semibold text-muted">Permissible values</p>
              <div className="mt-1 flex flex-wrap gap-1">
                {cde.permissibleValues.map((pv) => (
                  <span key={pv.value} className="rounded border border-line px-1.5 py-0.5 text-[10px] text-muted">
                    {pv.value}
                  </span>
                ))}
              </div>
            </div>
          ) : null}
          <div>
            <Label className="text-xs text-muted">Curator notes</Label>
            <Textarea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="mt-1 min-h-[60px] text-sm"
              aria-label={`Curator notes for ${elnLabel}`}
            />
            <Button size="sm" variant="outline" className="mt-1" onClick={saveNotes} disabled={update.isPending}>
              Save notes
            </Button>
          </div>
          <div className="flex gap-2">
            <Button
              size="sm"
              onClick={() =>
                accept.mutate(fieldCrosswalk.id, {
                  onSuccess: () => toast({ title: 'Mapping accepted' }),
                })
              }
              disabled={accept.isPending}
            >
              <CheckCircle2 className="h-4 w-4" />
              Accept
            </Button>
            <Button
              size="sm"
              variant="destructive"
              onClick={() =>
                reject.mutate(fieldCrosswalk.id, {
                  onSuccess: () => toast({ title: 'Mapping rejected' }),
                })
              }
              disabled={reject.isPending}
            >
              <XCircle className="h-4 w-4" />
              Reject
            </Button>
          </div>
        </div>
      ) : null}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Validation summary
// ---------------------------------------------------------------------------

function ValidationSummary({ report }: { report: ValidationReport }) {
  return (
    <div className="space-y-2 rounded-xl border border-line bg-surface p-3">
      <div className="flex items-center justify-between">
        <h3 className="font-display text-sm font-semibold">Validation</h3>
        <Badge variant={report.semanticCompletenessScore >= 70 ? 'success' : 'warning'}>
          {report.semanticCompletenessScore}/100
        </Badge>
      </div>
      <div className="grid grid-cols-2 gap-1 text-[11px] text-muted">
        <span>Extracted: {report.fieldsExtracted}</span>
        <span>Mapped: {report.fieldsMappedToCdes}</span>
        <span>Unmapped: {report.unmappedFields}</span>
        <span>Need review: {report.fieldsRequiringReview}</span>
      </div>
      {report.issues.length > 0 ? (
        <ul className="space-y-1">
          {report.issues.map((issue, i) => (
            <li key={`${issue.code}-${i}`} className="text-[11px]">
              <Badge
                variant={
                  issue.severity === 'error' ? 'critical' : issue.severity === 'warning' ? 'warning' : 'outline'
                }
              >
                {issue.code}
              </Badge>{' '}
              <span className="text-muted">{issue.message}</span>
            </li>
          ))}
        </ul>
      ) : (
        <p className="text-[11px] text-muted">No issues reported.</p>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Create CDE dialog (inline form)
// ---------------------------------------------------------------------------

function CreateCdeForm({ onClose }: { onClose: () => void }) {
  const { toast } = useToast()
  const createCde = useCreateCde()
  const [label, setLabel] = useState('')
  const [definition, setDefinition] = useState('')
  const [datatype, setDatatype] = useState('')
  const [unitConstraint, setUnitConstraint] = useState('')

  const submit = () => {
    if (!label.trim()) return
    createCde.mutate(
      { label: label.trim(), definition, datatype, unitConstraint },
      {
        onSuccess: () => {
          toast({ title: 'CDE created', description: label })
          onClose()
        },
        onError: (err) =>
          toast({ title: 'Failed', description: (err as Error).message, variant: 'error' }),
      },
    )
  }

  return (
    <div className="space-y-2 rounded-xl border border-line bg-surface-2 p-3">
      <h3 className="font-display text-sm font-semibold">Create new Metadatapp CDE</h3>
      <div className="space-y-1">
        <Label className="text-xs">Label</Label>
        <Input value={label} onChange={(e) => setLabel(e.target.value)} aria-label="New CDE label" />
      </div>
      <div className="space-y-1">
        <Label className="text-xs">Definition</Label>
        <Textarea
          value={definition}
          onChange={(e) => setDefinition(e.target.value)}
          className="min-h-[60px] text-sm"
          aria-label="New CDE definition"
        />
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div className="space-y-1">
          <Label className="text-xs">Datatype</Label>
          <Input value={datatype} onChange={(e) => setDatatype(e.target.value)} aria-label="New CDE datatype" />
        </div>
        <div className="space-y-1">
          <Label className="text-xs">Unit</Label>
          <Input
            value={unitConstraint}
            onChange={(e) => setUnitConstraint(e.target.value)}
            aria-label="New CDE unit"
          />
        </div>
      </div>
      <div className="flex gap-2">
        <Button size="sm" onClick={submit} disabled={createCde.isPending}>
          Create
        </Button>
        <Button size="sm" variant="ghost" onClick={onClose}>
          Cancel
        </Button>
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Studio (loaded crosswalk)
// ---------------------------------------------------------------------------

function CrosswalkStudio({ crosswalk }: { crosswalk: TemplateFormCrosswalk }) {
  const { toast } = useToast()
  const suggest = useSuggestMappings()
  const validate = useValidateCrosswalk()
  const exportRoCrate = useExportRoCrate()
  const exportJsonLd = useExportJsonLd()
  const update = useUpdateFieldCrosswalk()

  const [selectedFieldId, setSelectedFieldId] = useState<string | undefined>()
  const [selectedCrosswalkId, setSelectedCrosswalkId] = useState<string | undefined>(
    crosswalk.fieldCrosswalks[0]?.id,
  )
  const [report, setReport] = useState<ValidationReport | undefined>()
  const [showCreateCde, setShowCreateCde] = useState(false)

  const template = typeof crosswalk.externalTemplate === 'object' ? crosswalk.externalTemplate : undefined
  const canonicalForm = typeof crosswalk.canonicalForm === 'object' ? crosswalk.canonicalForm : undefined

  const extractedFields = useMemo(() => template?.extractedFields ?? [], [template])

  const selectedFieldCrosswalk = crosswalk.fieldCrosswalks.find((fc) => fc.id === selectedCrosswalkId)

  const handlePickCde = (cde: CommonDataElement) => {
    if (!selectedFieldCrosswalk) {
      toast({ title: 'Select a field first', variant: 'error' })
      return
    }
    update.mutate(
      { id: selectedFieldCrosswalk.id, patch: { cde: cde.id } },
      { onSuccess: () => toast({ title: 'CDE mapped', description: cde.label }) },
    )
  }

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
      {/* LEFT */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-base">
            <Boxes className="h-4 w-4" />
            ELN Template
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {template ? (
            <div>
              <p className="text-sm font-medium text-ink">{template.title}</p>
              {template.externalUrl ? (
                <p className="truncate font-mono text-[11px] text-muted">{template.externalUrl}</p>
              ) : null}
            </div>
          ) : null}
          <div className="space-y-2">
            {extractedFields.map((field) => (
              <ExtractedFieldRow
                key={field.id}
                field={field}
                selected={selectedFieldId === field.id}
                onSelect={() => setSelectedFieldId(field.id)}
              />
            ))}
            {extractedFields.length === 0 ? (
              <p className="text-xs text-muted">No extracted fields on this template.</p>
            ) : null}
          </div>
        </CardContent>
      </Card>

      {/* MIDDLE */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between gap-2 text-base">
            <span>{canonicalForm?.title ?? 'Canonical Form'}</span>
            <Badge variant={statusVariant(crosswalk.mappingStatus)}>{crosswalk.mappingStatus}</Badge>
          </CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex flex-wrap gap-2">
            <Button
              size="sm"
              variant="outline"
              onClick={() =>
                suggest.mutate(crosswalk.id, {
                  onSuccess: (res) =>
                    toast({
                      title: 'Suggestions ready',
                      description: `${res.suggestions.length} suggestions`,
                    }),
                })
              }
              disabled={suggest.isPending}
            >
              <Sparkles className="h-4 w-4" />
              Suggest
            </Button>
            <Button
              size="sm"
              variant="outline"
              onClick={() =>
                validate.mutate(crosswalk.id, {
                  onSuccess: (res) => setReport(res),
                })
              }
              disabled={validate.isPending}
            >
              <CheckCircle2 className="h-4 w-4" />
              Validate
            </Button>
          </div>

          {report ? <ValidationSummary report={report} /> : null}

          <div className="space-y-2">
            {crosswalk.fieldCrosswalks.map((fc) => (
              <FieldCrosswalkRow
                key={fc.id}
                fieldCrosswalk={fc}
                crosswalkId={fc.id}
                selected={selectedCrosswalkId === fc.id}
                onSelect={() => setSelectedCrosswalkId(fc.id)}
              />
            ))}
            {crosswalk.fieldCrosswalks.length === 0 ? (
              <p className="text-xs text-muted">No field mappings yet. Use Suggest to generate some.</p>
            ) : null}
          </div>
        </CardContent>
      </Card>

      {/* RIGHT */}
      <Card>
        <CardHeader>
          <CardTitle className="text-base">CDE Library</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          {showCreateCde ? (
            <CreateCdeForm onClose={() => setShowCreateCde(false)} />
          ) : (
            <CdeCandidatePanel onPickCde={handlePickCde} onCreateCde={() => setShowCreateCde(true)} />
          )}

          <div className="flex flex-wrap gap-2 border-t border-line pt-3">
            <Button
              size="sm"
              variant="outline"
              onClick={() =>
                exportRoCrate.mutate(crosswalk.id, {
                  onSuccess: (payload) => {
                    downloadJson(payload, `crosswalk-${crosswalk.id}-ro-crate.jsonld`)
                    toast({ title: 'RO-Crate exported' })
                  },
                })
              }
              disabled={exportRoCrate.isPending}
            >
              <Download className="h-4 w-4" />
              RO-Crate
            </Button>
            <Button
              size="sm"
              variant="outline"
              onClick={() =>
                exportJsonLd.mutate(crosswalk.id, {
                  onSuccess: (payload) => {
                    downloadJson(payload, `crosswalk-${crosswalk.id}.jsonld`)
                    toast({ title: 'JSON-LD exported' })
                  },
                })
              }
              disabled={exportJsonLd.isPending}
            >
              <Download className="h-4 w-4" />
              JSON-LD
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function TemplateCrosswalkStudioPage() {
  const { data: crosswalks = [], isLoading, refetch } = useListCrosswalks()
  const [searchParams] = useSearchParams()
  // Allow deep links such as those emitted after a CEDAR import
  // (`/template-crosswalks?crosswalk=<id>`) to preselect a crosswalk.
  const requestedCrosswalkId = searchParams.get('crosswalk') ?? undefined
  const [selectedCrosswalkId, setSelectedCrosswalkId] = useState<string | undefined>()

  const activeId = selectedCrosswalkId ?? requestedCrosswalkId ?? crosswalks[0]?.id
  const { data: crosswalk, isLoading: isLoadingDetail } = useGetCrosswalk(activeId)

  return (
    <div className="space-y-6">
      <PageHeader
        title="Template Crosswalk Studio"
        subtitle="Import ELN templates, map their fields to Common Data Elements and canonical forms, then export enriched RO-Crate / JSON-LD."
        actions={
          <div className="flex items-center gap-1.5 text-xs text-muted">
            <Boxes className="h-4 w-4" />
            ELN ↔ CDE Crosswalk
          </div>
        }
      />

      <Card>
        <CardHeader>
          <CardTitle>Import &amp; select crosswalk</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <ImportPanel onImported={() => void refetch()} />

          {isLoading ? (
            <Skeleton className="h-9 w-full max-w-sm" />
          ) : crosswalks.length > 0 ? (
            <div className="space-y-1.5">
              <label
                htmlFor="crosswalk-select"
                className="text-xs font-semibold uppercase tracking-[0.2em] text-muted"
              >
                Select a crosswalk
              </label>
              <select
                id="crosswalk-select"
                value={activeId ?? ''}
                onChange={(e) => setSelectedCrosswalkId(e.target.value)}
                aria-label="Select a crosswalk"
                className="h-9 w-full max-w-sm rounded-xl border border-line bg-surface px-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-focus"
              >
                {crosswalks.map((cw) => {
                  const templateTitle =
                    typeof cw.externalTemplate === 'object' ? cw.externalTemplate.title : cw.id
                  return (
                    <option key={cw.id} value={cw.id}>
                      {templateTitle}
                    </option>
                  )
                })}
              </select>
            </div>
          ) : (
            <p className="text-sm text-muted">
              No crosswalks yet. Import an ELN template above to get started.
            </p>
          )}
        </CardContent>
      </Card>

      {isLoadingDetail && activeId ? (
        <Skeleton className="h-64 w-full" />
      ) : crosswalk ? (
        <CrosswalkStudio crosswalk={crosswalk} />
      ) : !isLoading && crosswalks.length === 0 ? (
        <EmptyState
          title="Nothing to map yet"
          description="Import an .eln file or an ELN.community record URL to begin building a crosswalk."
        />
      ) : null}
    </div>
  )
}

export default TemplateCrosswalkStudioPage
