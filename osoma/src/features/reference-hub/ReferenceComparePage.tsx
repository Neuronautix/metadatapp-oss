import { useMemo, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { ArrowLeft, Sparkles, Download, ExternalLink, Link2, AlertTriangle } from 'lucide-react'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Card } from '@/components/ui/card.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { useFeatureFlagContext } from '@/feature-flags/FeatureFlagProvider.tsx'
import {
    standardizeReferences,
    importReferences,
    compareSchemas,
    type ReferenceResult,
    type StandardizationResult,
    type ReferenceCluster,
    type HarmonizedField,
    type SchemaComparisonResult,
    type SchemaFieldGroup,
} from './reference-hub.api.ts'
import { TYPE_LABELS, readCompareItems, applyStandardization } from './reference-hub.shared.ts'

/**
 * Full-page Compare & Standardize view (route: /reference-hub/compare). Replaces
 * the former modal so the harmonized-field detail can scroll naturally. Reads the
 * selected references from session storage (written by the search page).
 */
export function ReferenceComparePage() {
    const navigate = useNavigate()
    const queryClient = useQueryClient()
    const { toast } = useToast()
    const { isEnabled } = useFeatureFlagContext()
    const standardizeEnabled = isEnabled('feature.referenceHub.standardize')

    const items = useMemo(() => readCompareItems(), [])
    const [standardization, setStandardization] = useState<StandardizationResult | null>(null)

    const SCHEMA_TYPES = new Set(['template', 'schema', 'guideline'])
    const isSchemaMode = items.every((i) => SCHEMA_TYPES.has(i.type))

    const standardizeMutation = useMutation({
        mutationFn: (toStandardize: ReferenceResult[]) => standardizeReferences(toStandardize),
        onSuccess: (result) => {
            setStandardization(result)
            if (!result.aiAvailable) {
                toast({ title: 'AI unavailable', description: 'Showing a heuristic grouping — turn on an AI provider for ontology-grounded standardization.' })
            }
        },
        onError: () => toast({ title: 'Standardization failed', description: 'Could not compare and link the selected references. Try again.' }),
    })

    const schemaCompareMutation = useMutation({
        mutationFn: compareSchemas,
    })

    const importMutation = useMutation({
        mutationFn: (toImport: ReferenceResult[]) => importReferences(toImport),
        onSuccess: (res) => {
            void queryClient.invalidateQueries({ queryKey: ['imported-references'] })
            toast({ title: 'Imported to library', description: `${res.count} reference${res.count === 1 ? '' : 's'} saved — find them under My Library.` })
            navigate('/reference-hub')
        },
        onError: () => toast({ title: 'Import failed', description: 'Could not save the selected references. Try again.' }),
    })

    const back = () => navigate('/reference-hub')

    if (items.length < 2) {
        return (
            <div className="space-y-4">
                <Button variant="ghost" size="sm" onClick={back}><ArrowLeft className="h-4 w-4 mr-1" /> Back to Reference Hub</Button>
                <EmptyState title="Nothing to compare" description="Select two or more results in the Reference Hub search, then choose Compare." />
            </div>
        )
    }

    return (
        <ReferenceCompareView
            items={items}
            onBack={back}
            standardizeEnabled={standardizeEnabled}
            onStandardize={() => standardizeMutation.mutate(items)}
            standardizing={standardizeMutation.isPending}
            standardization={standardization}
            onImport={() => importMutation.mutate(items)}
            onImportStandardized={() => importMutation.mutate(standardization ? applyStandardization(items, standardization) : items)}
            importing={importMutation.isPending}
            isSchemaMode={isSchemaMode}
            onCompareSchemas={() => schemaCompareMutation.mutate(items)}
            schemaComparing={schemaCompareMutation.isPending}
            schemaComparison={schemaCompareMutation.data ?? null}
        />
    )
}

interface ReferenceCompareViewProps {
    items: ReferenceResult[]
    onBack: () => void
    standardizeEnabled: boolean
    onStandardize: () => void
    standardizing: boolean
    standardization: StandardizationResult | null
    onImport: () => void
    onImportStandardized: () => void
    importing: boolean
    isSchemaMode?: boolean
    onCompareSchemas?: () => void
    schemaComparing?: boolean
    schemaComparison?: SchemaComparisonResult | null
}

export function ReferenceCompareView({
    items,
    onBack,
    standardizeEnabled,
    onStandardize,
    standardizing,
    standardization,
    onImport,
    onImportStandardized,
    importing,
    isSchemaMode,
    onCompareSchemas,
    schemaComparing,
    schemaComparison,
}: ReferenceCompareViewProps) {
    const fieldKeys = useMemo(() => {
        const keys = new Set<string>()
        for (const item of items) {
            for (const [k, v] of Object.entries({ ...(item.identifiers ?? {}), ...(item.raw ?? {}) })) {
                if (v !== null && v !== undefined && v !== '' && typeof v !== 'object') keys.add(k)
            }
        }
        return Array.from(keys).sort()
    }, [items])

    // refId → its cluster, so each column can show the concept it was linked to.
    const clusterByRefId = useMemo(() => {
        const map = new Map<string, ReferenceCluster>()
        for (const cluster of standardization?.clusters ?? []) {
            for (const refId of cluster.memberRefIds) map.set(refId, cluster)
        }
        return map
    }, [standardization])

    const value = (item: ReferenceResult, key: string): string => {
        const v = (item.raw ?? {})[key] ?? (item.identifiers ?? {})[key]
        return v === null || v === undefined || v === '' ? '—' : String(v)
    }

    return (
        <div className="space-y-4">
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-center gap-3 min-w-0">
                    <Button variant="ghost" size="sm" onClick={onBack}><ArrowLeft className="h-4 w-4 mr-1" /> Back</Button>
                    <div className="min-w-0">
                        <h1 className="font-display text-xl font-semibold text-ink">Compare &amp; standardize {items.length} references</h1>
                        <p className="text-xs text-muted">Cluster cross-source equivalents, map to canonical ontology terms, and harmonize the metadata fields.</p>
                    </div>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    {isSchemaMode && (
                        <Button
                            variant="outline"
                            onClick={onCompareSchemas}
                            disabled={schemaComparing}
                        >
                            {schemaComparing ? 'Comparing fields…' : 'Compare fields with AI'}
                        </Button>
                    )}
                    {standardizeEnabled && (
                        <Button variant="outline" onClick={onStandardize} disabled={standardizing}>
                            <Sparkles className="h-4 w-4 mr-1" /> {standardizing ? 'Standardizing…' : standardization ? 'Re-run AI' : 'Standardize with AI'}
                        </Button>
                    )}
                </div>
            </div>

            {standardization && <StandardizationPanel result={standardization} items={items} />}

            {schemaComparison && (
                <SchemaComparisonView result={schemaComparison} />
            )}

            <Card className="bg-surface border-line overflow-x-auto">
                <table className="w-full text-sm border-separate border-spacing-0">
                    <thead>
                        <tr>
                            <th className="sticky left-0 bg-surface text-left text-xs font-semibold text-muted uppercase tracking-wide px-3 py-2">Field</th>
                            {items.map((item) => (
                                <th key={item.id} className="text-left px-3 py-2 min-w-[14rem] align-bottom">
                                    <div className="font-medium text-ink">{item.title}</div>
                                    <div className="mt-1 flex items-center gap-1.5 flex-wrap">
                                        <Badge variant="outline" className="text-[10px]">{item.sourceName}</Badge>
                                        {item.externalUrl && (
                                            <a href={item.externalUrl} target="_blank" rel="noreferrer" className="inline-flex items-center gap-0.5 text-[10px] text-brand hover:underline">
                                                source <ExternalLink className="h-2.5 w-2.5" />
                                            </a>
                                        )}
                                    </div>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {standardization && (
                            <CompareRow
                                label="Canonical term"
                                items={items}
                                render={(i) => clusterByRefId.get(i.id)?.canonicalTerm?.label ?? '—'}
                            />
                        )}
                        <CompareRow label="Type" items={items} render={(i) => TYPE_LABELS[i.type] ?? i.type} />
                        <CompareRow label="Description" items={items} render={(i) => i.description ?? '—'} />
                        {fieldKeys.map((key) => (
                            <CompareRow key={key} label={key} mono items={items} render={(i) => value(i, key)} />
                        ))}
                    </tbody>
                </table>
            </Card>

            <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={onBack}>Cancel</Button>
                {standardization ? (
                    <Button onClick={onImportStandardized} disabled={importing}>
                        <Download className="h-4 w-4 mr-1" /> Import standardized
                    </Button>
                ) : (
                    <Button onClick={onImport} disabled={importing}>
                        <Download className="h-4 w-4 mr-1" /> Import all {items.length}
                    </Button>
                )}
            </div>
        </div>
    )
}

function StandardizationPanel({ result, items }: { result: StandardizationResult; items: ReferenceResult[] }) {
    const titleByRefId = useMemo(() => {
        const map = new Map<string, string>()
        for (const item of items) map.set(item.id, item.title)
        return map
    }, [items])

    return (
        <div className="space-y-2" data-testid="standardization-panel">
            <div className="flex items-center gap-2 text-xs font-semibold text-muted uppercase tracking-wide">
                <Link2 className="h-3.5 w-3.5" /> Linked concepts
                {!result.aiAvailable && <Badge variant="outline" className="text-[10px] normal-case">heuristic — AI off</Badge>}
            </div>

            {result.warnings.map((warning) => (
                <p key={warning} className="text-xs text-muted">{warning}</p>
            ))}

            <div className="grid gap-2 sm:grid-cols-2">
                {result.clusters.map((cluster) => (
                    <div key={cluster.clusterId} className="rounded-xl border border-line bg-surface-3 p-3">
                        <div className="flex items-center gap-2 flex-wrap">
                            {cluster.canonicalTerm ? (
                                <a
                                    href={cluster.canonicalTerm.iri}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 rounded-full bg-brand/10 px-2 py-0.5 text-xs font-medium text-brand hover:underline"
                                >
                                    {cluster.canonicalTerm.label}
                                    <span className="text-[10px] uppercase opacity-70">{cluster.canonicalTerm.ontology}</span>
                                </a>
                            ) : (
                                <span className="text-xs text-muted">No canonical term</span>
                            )}
                            {cluster.canonicalTerm && (
                                <span className="text-[10px] text-muted">{Math.round(cluster.canonicalTerm.confidence * 100)}% confidence</span>
                            )}
                        </div>
                        <p className="mt-1.5 text-xs text-ink">
                            {cluster.memberRefIds.map((refId) => titleByRefId.get(refId) ?? refId).join(' ↔ ')}
                        </p>
                        {cluster.evidence.length > 0 && (
                            <p className="mt-1 text-[11px] text-muted italic">{cluster.evidence[0]}</p>
                        )}
                        {cluster.harmonizedFields.length > 0 && <HarmonizedFields fields={cluster.harmonizedFields} />}
                    </div>
                ))}
            </div>
        </div>
    )
}

function HarmonizedFields({ fields }: { fields: HarmonizedField[] }) {
    return (
        <div className="mt-2 border-t border-line pt-2" data-testid="harmonized-fields">
            <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-muted">Harmonized fields</p>
            <dl className="space-y-1.5">
                {fields.map((field) => (
                    <div key={field.field} className="text-[11px]">
                        <div className="flex items-center gap-1.5 flex-wrap">
                            <span className="font-mono text-muted">{field.field}</span>
                            <span className="font-medium text-ink">{field.value || '—'}</span>
                            {field.conflict && (
                                <span className="inline-flex items-center gap-0.5 rounded-full bg-warning/15 px-1.5 py-0.5 text-[9px] font-medium text-warning">
                                    <AlertTriangle className="h-2.5 w-2.5" /> conflict
                                </span>
                            )}
                        </div>
                        {field.conflict && (
                            <p className="text-[10px] text-muted">
                                {field.sourceValues.map((sv) => `${sv.source}: ${sv.value}`).join('  ·  ')}
                            </p>
                        )}
                        {field.rationale && <p className="text-[10px] italic text-muted">{field.rationale}</p>}
                    </div>
                ))}
            </dl>
        </div>
    )
}

function CompareRow({ label, items, render, mono }: { label: string; items: ReferenceResult[]; render: (item: ReferenceResult) => string; mono?: boolean }) {
    return (
        <tr className="border-t border-line">
            <td className={`sticky left-0 bg-surface align-top px-3 py-2 text-muted ${mono ? 'font-mono text-xs' : 'text-xs font-medium'}`}>{label}</td>
            {items.map((item) => (
                <td key={item.id} className="align-top px-3 py-2 text-ink break-words border-t border-line">{render(item)}</td>
            ))}
        </tr>
    )
}

function SchemaComparisonView({ result }: { result: SchemaComparisonResult }) {
    if (result.fieldGroups.length === 0) {
        return <p className="text-sm text-muted-foreground mt-4">No field groups found.</p>
    }
    return (
        <div className="mt-6 space-y-4">
            <h3 className="font-semibold text-sm">Field harmonization</h3>
            {!result.aiAvailable && (
                <p className="text-xs text-muted-foreground">AI unavailable — showing label-based grouping.</p>
            )}
            {result.warnings.map((w, i) => (
                <p key={i} className="text-xs text-yellow-600">{w}</p>
            ))}
            <div className="divide-y divide-border rounded-lg border">
                {result.fieldGroups.map((group) => (
                    <SchemaFieldGroupRow key={group.groupId} group={group} />
                ))}
            </div>
        </div>
    )
}

function SchemaFieldGroupRow({ group }: { group: SchemaFieldGroup }) {
    return (
        <div className="p-3 space-y-1">
            <div className="flex items-center gap-2">
                <span className="font-medium text-sm">{group.canonicalLabel}</span>
                {group.canonicalDatatype && (
                    <span className="text-xs text-muted-foreground">({group.canonicalDatatype})</span>
                )}
                {group.canonicalUnit && (
                    <span className="text-xs text-muted-foreground">· {group.canonicalUnit}</span>
                )}
                <span className="ml-auto text-xs text-muted-foreground">
                    {Math.round(group.confidence * 100)}% confidence
                </span>
            </div>
            <div className="flex flex-wrap gap-1 mt-1">
                {group.members.map((m, i) => (
                    <span key={i} className="text-xs bg-secondary px-1.5 py-0.5 rounded" title={m.evidence}>
                        {m.label} <span className="text-muted-foreground">({m.mappingType})</span>
                    </span>
                ))}
            </div>
            {group.conflicts.length > 0 && (
                <p className="text-xs text-red-600">
                    ⚠ Conflict: {group.conflicts.map((c) => c.field).join(', ')}
                </p>
            )}
        </div>
    )
}
