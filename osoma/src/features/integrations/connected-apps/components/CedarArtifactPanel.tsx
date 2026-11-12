import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import {
    getCedarArtifact,
    createCedarArtifact,
    updateCedarArtifact,
    deleteCedarArtifact,
    validateCedarArtifact,
    importCedarTemplate,
    exportCanonicalFormToCedar,
    listCanonicalForms,
} from '../connected-apps.api.ts'
import type { CedarArtifact, CedarArtifactKind, CedarValidationReport } from '../connected-apps.api.ts'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { Download, Search, CheckCircle2, XCircle, Save, Plus, Trash2, ShieldCheck, ArrowRight, Upload, Braces } from 'lucide-react'

type CedarImportResult = {
    title: string
    fieldCount: number
    crosswalkId: string | null
}

const ARTIFACT_KINDS: { value: CedarArtifactKind; label: string }[] = [
    { value: 'template', label: 'Template' },
    { value: 'element', label: 'Element' },
    { value: 'field', label: 'Field' },
    { value: 'instance', label: 'Instance' },
]

interface CedarArtifactPanelProps {
    appId: string
}

function stringify(value: unknown): string {
    return JSON.stringify(value, null, 2)
}

// CEDAR validation items are objects (commonly { message, ... }); surface the
// human-readable message and fall back to a compact JSON rendering.
function describeReportItem(item: unknown): string {
    if (typeof item === 'string') return item
    if (item && typeof item === 'object') {
        const record = item as Record<string, unknown>
        const message = record.message ?? record.error ?? record.detail
        if (typeof message === 'string' && message.trim() !== '') return message
    }
    return JSON.stringify(item)
}

function ReportList({ items, tone }: { items: unknown[]; tone: 'error' | 'muted' }) {
    return (
        <ul className={`space-y-1 text-xs ${tone === 'error' ? 'text-error' : 'text-muted'}`}>
            {items.map((item, index) => (
                <li key={index} className="flex gap-2">
                    <span aria-hidden="true">•</span>
                    <span className="break-words">{describeReportItem(item)}</span>
                </li>
            ))}
        </ul>
    )
}

export function CedarArtifactPanel({ appId }: CedarArtifactPanelProps) {
    const { toast } = useToast()

    const [kind, setKind] = useState<CedarArtifactKind>('template')
    const [iri, setIri] = useState('')
    const [draft, setDraft] = useState('')
    const [report, setReport] = useState<CedarValidationReport | null>(null)
    const [confirmDeleteOpen, setConfirmDeleteOpen] = useState(false)
    const [importResult, setImportResult] = useState<CedarImportResult | null>(null)
    const [exportFormId, setExportFormId] = useState('')

    const formsQuery = useQuery({
        queryKey: ['cedar-export-canonical-forms'],
        queryFn: listCanonicalForms,
    })

    function parseDraft(): CedarArtifact {
        const trimmed = draft.trim()
        if (trimmed === '') {
            throw new Error('Paste a CEDAR artifact (JSON) into the editor first.')
        }
        let parsed: unknown
        try {
            parsed = JSON.parse(trimmed)
        } catch {
            throw new Error('The editor does not contain valid JSON.')
        }
        if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
            throw new Error('A CEDAR artifact must be a JSON object.')
        }
        return parsed as CedarArtifact
    }

    function reportError(title: string, err: unknown) {
        toast({
            title,
            description: err instanceof Error ? err.message : String(err),
            variant: 'error',
        })
    }

    function formatDraft() {
        try {
            setDraft(stringify(parseDraft()))
        } catch (err) {
            reportError('Could not format JSON', err)
        }
    }

    // A GET, so it uses useQuery (like the JAX/NIH CDE browsers) for caching and
    // convention alignment. Triggered on demand via refetch() from the Fetch
    // button rather than running on mount.
    const fetchQuery = useQuery({
        queryKey: ['cedar-artifact', appId, kind, iri.trim()],
        queryFn: () => getCedarArtifact(appId, kind, iri.trim()),
        enabled: false,
    })

    async function handleFetch() {
        const result = await fetchQuery.refetch()
        if (result.data) {
            setDraft(stringify(result.data.artifact))
            setReport(null)
            toast({ title: 'Artifact fetched', description: `Loaded ${kind} into the editor.` })
        } else if (result.error) {
            reportError('Fetch failed', result.error)
        }
    }

    const createMutation = useMutation({
        mutationFn: () => createCedarArtifact(appId, kind, parseDraft()),
        onSuccess: ({ artifact }) => {
            setDraft(stringify(artifact))
            const newId = typeof artifact['@id'] === 'string' ? (artifact['@id'] as string) : null
            if (newId) setIri(newId)
            toast({ title: `${kind} created`, description: newId ? `CEDAR assigned @id ${newId}` : 'Artifact created on CEDAR.' })
        },
        onError: (err) => reportError('Create failed', err),
    })

    const updateMutation = useMutation({
        mutationFn: () => updateCedarArtifact(appId, kind, iri.trim(), parseDraft()),
        onSuccess: ({ artifact }) => {
            setDraft(stringify(artifact))
            toast({ title: `${kind} updated`, description: 'Changes saved to CEDAR.' })
        },
        onError: (err) => reportError('Update failed', err),
    })

    const deleteMutation = useMutation({
        mutationFn: () => deleteCedarArtifact(appId, kind, iri.trim()),
        onSuccess: ({ deleted }) => {
            setDraft('')
            setReport(null)
            toast({ title: `${kind} deleted`, description: deleted })
        },
        onError: (err) => reportError('Delete failed', err),
    })

    const validateMutation = useMutation({
        mutationFn: () => validateCedarArtifact(appId, parseDraft(), kind),
        onSuccess: (result) => {
            setReport(result)
            toast({
                title: result.validates ? 'Artifact is valid' : 'Validation found issues',
                description: result.validates
                    ? 'CEDAR validated the artifact against its meta-model.'
                    : `${result.errors.length} error(s), ${result.warnings.length} warning(s).`,
                variant: result.validates ? undefined : 'error',
            })
        },
        onError: (err) => reportError('Validation failed', err),
    })

    const importMutation = useMutation({
        mutationFn: () => importCedarTemplate(appId, iri.trim()),
        onSuccess: (result) => {
            setImportResult({
                title: result.title,
                fieldCount: result.fieldCount,
                crosswalkId: result.crosswalkId,
            })
            toast({
                title: 'Template imported',
                description: `"${result.title}" saved as an external template for crosswalking.`,
            })
        },
        onError: (err) => reportError('Import failed', err),
    })

    const exportMutation = useMutation({
        mutationFn: () => exportCanonicalFormToCedar(appId, exportFormId, iri.trim() || undefined),
        onSuccess: (result) => {
            setKind('template')
            setDraft(stringify(result.artifact))
            setReport(null)
            if (result.id) setIri(result.id)
            const verb = iri.trim() ? 'updated' : 'created'
            toast({
                title: `Form ${verb} on CEDAR`,
                description: `"${result.title}" (${result.fieldCount} field(s)) ${verb} as a CEDAR template.`,
            })
        },
        onError: (err) => reportError('Export failed', err),
    })

    const hasIri = iri.trim().length > 0
    const anyPending =
        fetchQuery.isFetching ||
        createMutation.isPending ||
        updateMutation.isPending ||
        deleteMutation.isPending ||
        validateMutation.isPending ||
        importMutation.isPending ||
        exportMutation.isPending

    function confirmDelete() {
        setConfirmDeleteOpen(false)
        deleteMutation.mutate()
    }

    const crosswalkHref = importResult?.crosswalkId
        ? `/template-crosswalks?crosswalk=${encodeURIComponent(importResult.crosswalkId)}`
        : '/template-crosswalks'

    return (
        <div className="space-y-5">
            <p className="text-sm text-muted">
                Work with CEDAR artifacts (templates, elements, fields and instances) directly. This panel
                mirrors the{' '}
                <code className="rounded bg-surface-2 px-1 py-0.5 text-xs font-mono">cedar-artifact-rest-mcp</code>{' '}
                surface: fetch, create, update, delete and validate, plus import a template into Metadatapp.
            </p>

            {/* Artifact kind selector */}
            <div className="flex flex-wrap gap-2">
                {ARTIFACT_KINDS.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        onClick={() => setKind(option.value)}
                        className={`rounded-full border px-3 py-1 text-xs font-medium transition-colors ${
                            kind === option.value
                                ? 'border-brand bg-brand/10 text-brand'
                                : 'border-line bg-surface text-muted hover:border-brand hover:text-ink'
                        }`}
                    >
                        {option.label}
                    </button>
                ))}
            </div>

            {/* IRI + fetch/import */}
            <div className="space-y-2">
                <label className="text-xs font-semibold uppercase tracking-wide text-muted">Artifact @id (IRI)</label>
                <div className="flex flex-wrap gap-2">
                    <div className="relative min-w-[16rem] flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                        <Input
                            className="pl-9"
                            placeholder="https://repo.metadatacenter.org/templates/…"
                            value={iri}
                            onChange={(e) => setIri(e.target.value)}
                        />
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => void handleFetch()}
                        disabled={!hasIri || anyPending}
                    >
                        <Search className="mr-2 h-4 w-4" />
                        {fetchQuery.isFetching ? 'Fetching…' : 'Fetch'}
                    </Button>
                    {kind === 'template' && (
                        <Button
                            variant="outline"
                            onClick={() => importMutation.mutate()}
                            disabled={!hasIri || anyPending}
                        >
                            <Download className="mr-2 h-4 w-4" />
                            {importMutation.isPending ? 'Importing…' : 'Import'}
                        </Button>
                    )}
                </div>
            </div>

            {/* Write-back: push a Metadatapp canonical form to CEDAR as a template */}
            <div className="space-y-2 rounded-xl border border-line bg-surface-2 p-4">
                <label className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted">
                    <Upload className="h-3.5 w-3.5" /> Export a canonical form to CEDAR
                </label>
                <p className="text-xs text-muted">
                    Builds a CEDAR template from a Metadatapp form and creates it on CEDAR — or updates the
                    template named by the @id above when one is set. Closes the loop with the import flow.
                </p>
                <div className="flex flex-wrap gap-2">
                    <select
                        aria-label="Canonical form to export"
                        className="h-9 min-w-[16rem] flex-1 rounded-xl border border-line bg-surface px-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-focus"
                        value={exportFormId}
                        onChange={(e) => setExportFormId(e.target.value)}
                        disabled={anyPending || formsQuery.isLoading}
                    >
                        <option value="">
                            {formsQuery.isLoading
                                ? 'Loading forms…'
                                : (formsQuery.data?.length ?? 0) === 0
                                  ? 'No canonical forms available'
                                  : 'Select a canonical form…'}
                        </option>
                        {formsQuery.data?.map((form) => (
                            <option key={form.id} value={form.id}>
                                {form.title}
                            </option>
                        ))}
                    </select>
                    <Button
                        variant="outline"
                        onClick={() => exportMutation.mutate()}
                        disabled={exportFormId === '' || anyPending}
                    >
                        <Upload className="mr-2 h-4 w-4" />
                        {exportMutation.isPending ? 'Pushing…' : hasIri ? 'Update on CEDAR' : 'Push to CEDAR'}
                    </Button>
                </div>
            </div>

            {/* Import follow-up: link the freshly imported template into the Crosswalk Studio */}
            {importResult && (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand/30 bg-brand/5 p-4">
                    <div className="min-w-0">
                        <p className="text-sm font-medium text-ink">
                            Imported “{importResult.title}”
                            <span className="text-muted"> · {importResult.fieldCount} field(s) extracted</span>
                        </p>
                        <p className="text-xs text-muted">
                            A draft crosswalk is ready. Map its fields to Common Data Elements in the Template
                            Crosswalk Studio.
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link to={crosswalkHref}>
                            Open in Crosswalk Studio
                            <ArrowRight className="ml-2 h-4 w-4" />
                        </Link>
                    </Button>
                </div>
            )}

            {/* Artifact editor */}
            <div className="space-y-2">
                <label className="text-xs font-semibold uppercase tracking-wide text-muted">Artifact JSON</label>
                <Textarea
                    className="min-h-[220px] font-mono text-xs"
                    placeholder={`Paste a CEDAR ${kind} as JSON, or Fetch one by its @id above.`}
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    spellCheck={false}
                />
                <div className="flex flex-wrap gap-2">
                    <Button onClick={() => validateMutation.mutate()} disabled={anyPending}>
                        <ShieldCheck className="mr-2 h-4 w-4" />
                        {validateMutation.isPending ? 'Validating…' : 'Validate'}
                    </Button>
                    <Button variant="outline" onClick={() => createMutation.mutate()} disabled={anyPending}>
                        <Plus className="mr-2 h-4 w-4" />
                        {createMutation.isPending ? 'Creating…' : 'Create'}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => updateMutation.mutate()}
                        disabled={!hasIri || anyPending}
                    >
                        <Save className="mr-2 h-4 w-4" />
                        {updateMutation.isPending ? 'Updating…' : 'Update'}
                    </Button>
                    <Button
                        variant="outline"
                        className="text-error hover:text-error"
                        onClick={() => setConfirmDeleteOpen(true)}
                        disabled={!hasIri || anyPending}
                    >
                        <Trash2 className="mr-2 h-4 w-4" />
                        {deleteMutation.isPending ? 'Deleting…' : 'Delete'}
                    </Button>
                    <Button
                        variant="ghost"
                        onClick={formatDraft}
                        disabled={draft.trim() === '' || anyPending}
                    >
                        <Braces className="mr-2 h-4 w-4" />
                        Format JSON
                    </Button>
                </div>
                <p className="text-xs text-muted">
                    Create and Validate use the editor contents. Update, Delete and Import act on the @id above.
                    On Create, CEDAR assigns the authoritative @id.
                </p>
            </div>

            {/* Validation report */}
            {report && (
                <div className="rounded-xl border border-line bg-surface-2 p-4">
                    <div className="mb-2 flex items-center gap-2">
                        {report.validates ? (
                            <Badge variant="success" className="gap-1">
                                <CheckCircle2 className="h-3 w-3" /> Valid
                            </Badge>
                        ) : (
                            <Badge variant="critical" className="gap-1">
                                <XCircle className="h-3 w-3" /> Invalid
                            </Badge>
                        )}
                        <span className="text-xs text-muted">
                            {report.errors.length} error(s) · {report.warnings.length} warning(s)
                        </span>
                    </div>
                    {report.errors.length > 0 && (
                        <div className="max-h-48 overflow-auto rounded-lg bg-surface p-3">
                            <ReportList items={report.errors} tone="error" />
                        </div>
                    )}
                    {report.warnings.length > 0 && (
                        <div className="mt-2 max-h-48 overflow-auto rounded-lg bg-surface p-3">
                            <ReportList items={report.warnings} tone="muted" />
                        </div>
                    )}
                </div>
            )}

            {/* Delete confirmation — replaces the native window.confirm for a11y/testability */}
            <Dialog open={confirmDeleteOpen} onOpenChange={setConfirmDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this CEDAR {kind}?</DialogTitle>
                        <DialogDescription>
                            This permanently deletes the {kind} at{' '}
                            <code className="font-mono text-xs">{iri.trim() || '—'}</code> on CEDAR. This
                            cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmDeleteOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            variant="outline"
                            className="text-error hover:text-error"
                            onClick={confirmDelete}
                        >
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete {kind}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    )
}
