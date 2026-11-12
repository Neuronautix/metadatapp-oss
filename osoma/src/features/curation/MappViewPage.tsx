import { useState, useCallback } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Download, Network, ChevronDown, ChevronRight, Link as LinkIcon } from 'lucide-react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { getMetaCollection, getMetaItem } from '@/metadatapp/api.ts'
import { resourceRegistry } from '@/metadatapp/registry.ts'

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type ValueKind = 'null' | 'boolean' | 'number' | 'string' | 'date' | 'iri' | 'array' | 'object'

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const JSON_LD_META_FIELDS = new Set(['@context', '@type', '@id', 'id', 'originId'])

function getValueKind(value: unknown): ValueKind {
  if (value === null || value === undefined) return 'null'
  if (typeof value === 'boolean') return 'boolean'
  if (typeof value === 'number') return 'number'
  if (typeof value === 'string') {
    if (/^\d{4}-\d{2}-\d{2}/.test(value)) return 'date'
    if (value.startsWith('/') || value.startsWith('http')) return 'iri'
    return 'string'
  }
  if (Array.isArray(value)) return 'array'
  return 'object'
}

function formatDate(value: string): string {
  try {
    return new Date(value).toLocaleString()
  } catch {
    return value
  }
}

function buildJsonLd(record: Record<string, unknown>): object {
  return {
    '@context': record['@context'] ?? { '@vocab': 'https://schema.org/', xsd: 'http://www.w3.org/2001/XMLSchema#' },
    '@type': record['@type'],
    '@id': record['@id'] ?? record['id'],
    ...record,
  }
}

function downloadJsonLd(record: Record<string, unknown>, resourceKey: string): void {
  const payload = buildJsonLd(record)
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/ld+json' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  const rawId = record['@id'] ?? record['id'] ?? 'unknown-record'
  a.href = url
  a.download = `${resourceKey}-${rawId}`.replace(/[^a-zA-Z0-9._-]/g, '_') + '.jsonld'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

// ---------------------------------------------------------------------------
// Value badge
// ---------------------------------------------------------------------------

const DEPTH_COLORS = [
  'border-blue-400 text-blue-800',
  'border-green-400 text-green-800',
  'border-orange-400 text-orange-800',
  'border-purple-400 text-purple-800',
  'border-red-400 text-red-800',
  'border-teal-400 text-teal-800',
]

function ValueBadge({ value }: { value: unknown }) {
  const kind = getValueKind(value)

  if (kind === 'null') {
    return <span className="rounded border border-slate-200 px-2 py-0.5 text-xs text-slate-400">—</span>
  }
  if (kind === 'boolean') {
    return (
      <span
        className={`rounded border px-2 py-0.5 text-xs font-medium ${
          value ? 'border-green-300 text-green-700' : 'border-slate-300 text-slate-500'
        }`}
      >
        {String(value)}
      </span>
    )
  }
  if (kind === 'date') {
    return (
      <span className="rounded border border-blue-200 px-2 py-0.5 text-xs text-blue-700">
        {formatDate(String(value))}
      </span>
    )
  }
  if (kind === 'iri') {
    return (
      <span className="inline-flex max-w-xs items-center gap-1 overflow-hidden text-ellipsis rounded border border-violet-200 px-2 py-0.5 text-xs text-violet-700">
        <LinkIcon className="h-3 w-3 flex-shrink-0" />
        <span className="truncate">{String(value)}</span>
      </span>
    )
  }
  return (
    <span className="max-w-xs truncate rounded border border-slate-200 px-2 py-0.5 text-xs text-slate-700">
      {String(value)}
    </span>
  )
}

// ---------------------------------------------------------------------------
// Recursive schema node
// ---------------------------------------------------------------------------

function SchemaNode({
  label,
  value,
  depth = 0,
}: {
  label: string
  value: unknown
  depth?: number
}) {
  const [open, setOpen] = useState(depth < 2)
  const kind = getValueKind(value)
  const isExpansible = kind === 'object' || kind === 'array'
  const accentClass = DEPTH_COLORS[depth % DEPTH_COLORS.length]
  const isMetaField = JSON_LD_META_FIELDS.has(label)

  const toggle = useCallback(() => setOpen((prev) => !prev), [])

  return (
    <div
      className={`${depth > 0 ? 'ml-5 border-l border-slate-200 pl-4' : ''} my-0.5`}
    >
      <div className="flex min-h-7 items-center gap-1.5">
        {isExpansible ? (
          <button
            onClick={toggle}
            className="flex-shrink-0 text-slate-400 hover:text-slate-700"
            aria-label={open ? 'Collapse' : 'Expand'}
          >
            {open ? (
              <ChevronDown className="h-3.5 w-3.5" />
            ) : (
              <ChevronRight className="h-3.5 w-3.5" />
            )}
          </button>
        ) : (
          <span className="w-3.5 flex-shrink-0" />
        )}

        <span
          className={`w-32 flex-shrink-0 font-mono text-xs font-medium ${
            isMetaField ? `font-bold ${accentClass.split(' ')[1]}` : 'text-slate-600'
          }`}
        >
          {label}
        </span>

        {!isExpansible && <ValueBadge value={value} />}
        {isExpansible && (
          <span className="text-xs text-slate-400">
            {kind === 'array'
              ? `[${(value as unknown[]).length} items]`
              : `{${Object.keys(value as object).length} fields}`}
          </span>
        )}
      </div>

      {isExpansible && open && (
        <div>
          {kind === 'array'
            ? (value as unknown[]).map((item, i) => (
                <SchemaNode key={i} label={`[${i}]`} value={item} depth={depth + 1} />
              ))
            : Object.entries(value as object).map(([k, v]) => (
                <SchemaNode key={k} label={k} value={v} depth={depth + 1} />
              ))}
        </div>
      )}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Schema tree (root)
// ---------------------------------------------------------------------------

function SchemaTree({ record }: { record: Record<string, unknown> }) {
  return (
    <div className="space-y-0.5">
      {Object.entries(record).map(([key, value]) => (
        <SchemaNode key={key} label={key} value={value} depth={0} />
      ))}
    </div>
  )
}

// ---------------------------------------------------------------------------
// Record selector
// ---------------------------------------------------------------------------

function RecordSelect({
  resourceKey: _resourceKey,
  collectionPath,
  selectedId,
  onSelect,
}: {
  resourceKey: string
  collectionPath: string
  selectedId: string
  onSelect: (id: string) => void
}) {
  const { data, isLoading } = useQuery({
    queryKey: ['mapp-view-list', collectionPath],
    queryFn: () => getMetaCollection(collectionPath),
  })

  if (isLoading) {
    return <Skeleton className="h-9 w-full" />
  }

  const items = (data?.data ?? []) as Array<Record<string, unknown>>

  if (!items.length) {
    return <p className="text-sm text-slate-500">No records found for this resource.</p>
  }

  return (
    <select
      value={selectedId}
      onChange={(e) => onSelect(e.target.value)}
      className="h-9 w-full rounded-xl border border-line bg-surface px-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-focus"
      aria-label="Select a record"
    >
      <option value="">— Select a record —</option>
      {items.map((item) => {
        const id = String(item['@id'] ?? item['id'] ?? '')
        const label = String(item['name'] ?? item['title'] ?? id)
        return (
          <option key={id} value={id}>
            {label}
          </option>
        )
      })}
    </select>
  )
}

// ---------------------------------------------------------------------------
// Schema detail
// ---------------------------------------------------------------------------

function SchemaDetail({
  resourceKey,
  recordId,
}: {
  resourceKey: string
  recordId: string
}) {
  const { toast } = useToast()
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['mapp-view-detail', recordId],
    queryFn: () => getMetaItem(recordId),
    enabled: Boolean(recordId),
  })

  const handleExport = () => {
    if (!data) return
    try {
      downloadJsonLd(data as Record<string, unknown>, resourceKey)
      toast({ title: 'Exported', description: 'JSON-LD file downloaded successfully.' })
    } catch {
      toast({ title: 'Export failed', description: 'Could not create JSON-LD file.', variant: 'error' })
    }
  }

  if (isLoading) {
    return (
      <div className="space-y-3">
        <Skeleton className="h-6 w-48" aria-label="Loading record details" />
        <Skeleton className="h-4 w-full" />
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-4 w-5/6" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <EmptyState
        title="Failed to load record"
        description={(error as Error)?.message ?? 'Unknown error'}
      />
    )
  }

  const record = data as Record<string, unknown>
  const displayName = String(record['name'] ?? record['title'] ?? recordId)

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="font-display text-lg text-slate-900">{displayName}</p>
          <p className="font-mono text-xs text-slate-400">{String(record['@id'] ?? recordId)}</p>
        </div>
        <Button size="sm" onClick={handleExport} className="gap-1.5">
          <Download className="h-4 w-4" />
          Export JSON-LD
        </Button>
      </div>

      <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
        <SchemaTree record={record} />
      </div>
    </div>
  )
}

// ---------------------------------------------------------------------------
// MappViewPage
// ---------------------------------------------------------------------------

const SUPPORTED_RESOURCES = resourceRegistry.filter((r) => r.supports.list && r.supports.read)

export function MappViewPage() {
  const [selectedResource, setSelectedResource] = useState(SUPPORTED_RESOURCES[0]?.key ?? '')
  const [selectedRecordId, setSelectedRecordId] = useState('')

  const entry = SUPPORTED_RESOURCES.find((r) => r.key === selectedResource)

  const handleResourceChange = (key: string) => {
    setSelectedResource(key)
    setSelectedRecordId('')
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="MappView"
        subtitle="Explore the full JSON-LD metadata schema tree of any element and export it for publication."
        actions={
          <div className="flex items-center gap-1.5 text-xs text-slate-500">
            <Network className="h-4 w-4" />
            Schema Explorer
          </div>
        }
      />

      {/* Step 1 — Choose resource */}
      <Card>
        <CardHeader>
          <CardTitle>1 — Choose a resource type</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <select
            value={selectedResource}
            onChange={(e) => handleResourceChange(e.target.value)}
            className="h-9 w-full max-w-sm rounded-xl border border-line bg-surface px-3 text-sm text-ink focus:outline-none focus:ring-2 focus:ring-focus"
            aria-label="Resource type"
          >
            {SUPPORTED_RESOURCES.map((r) => (
              <option key={r.key} value={r.key}>
                {r.label}
              </option>
            ))}
          </select>

          {/* Step 2 — Choose record */}
          {entry ? (
            <div className="space-y-1.5">
              <label className="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                2 — Select a record
              </label>
              <RecordSelect
                resourceKey={entry.key}
                collectionPath={entry.collectionPath}
                selectedId={selectedRecordId}
                onSelect={setSelectedRecordId}
              />
            </div>
          ) : null}
        </CardContent>
      </Card>

      {/* Schema view */}
      {selectedRecordId ? (
        <Card>
          <CardHeader>
            <CardTitle>Schema Tree</CardTitle>
          </CardHeader>
          <CardContent>
            <SchemaDetail resourceKey={selectedResource} recordId={selectedRecordId} />
          </CardContent>
        </Card>
      ) : (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-slate-300 py-12 text-slate-400">
          <Network className="h-10 w-10 opacity-30" aria-hidden="true" />
          <p className="text-sm">Select a record above to explore its schema.</p>
          <p className="text-xs">
            The full metadata tree will appear here, including all nested relationships.
          </p>
        </div>
      )}
    </div>
  )
}
