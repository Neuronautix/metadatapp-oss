import { useMemo } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { AiActionPanel } from '@/features/ai-assistant/components/AiActionPanel'
import { AiSuggestionDialog } from '@/features/ai-assistant/components/AiSuggestionDialog'
import { deleteMetaItem, getMetaItemByRef } from '@/metadatapp/api.ts'
import { getRouteResourceId } from '@/metadatapp/display.ts'
import { getResourceEntry } from '@/metadatapp/registry.ts'
import { extractReference, getDetailFieldsForEntry, getFormFieldsForEntry, getResourceDisplayLabel } from '@/metadatapp/ui-config.ts'
import type { SchemaRef } from '@/metadatapp/openapi-types.ts'

type MetadataRecord = Record<string, unknown>

const textStyle = 'text-ink'
const preferredFrontIdKeys = ['externalId', 'projectId', 'publicId', 'code']

const humanizeKey = (value: string) =>
  value
    .replace(/[_-]+/g, ' ')
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/\b\w/g, (char) => char.toUpperCase())

const isPlainObject = (value: unknown): value is Record<string, unknown> =>
  Boolean(value) && typeof value === 'object' && !Array.isArray(value)

const isLikelyIri = (value: string) =>
  /^(https?:\/\/|urn:|\/|[a-z][a-z0-9+.-]*:\/\/)/i.test(value) || value.includes('://')
const isUuidLike = (value: string) =>
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value)
const shortenToken = (value: string) =>
  value.length <= 24 ? value : `${value.slice(0, 10)}…${value.slice(-6)}`

const compactIri = (value: string) => {
  const trimmed = value.trim()
  if (!trimmed) return trimmed
  if (!isLikelyIri(trimmed)) return trimmed

  if (trimmed.startsWith('urn:')) {
    const parts = trimmed.split(':')
    const tail = parts[parts.length - 1]
    if (tail && parts.length > 2) return `urn:...:${tail}`
  }

  try {
    if (/^[a-z][a-z0-9+.-]*:\/\//i.test(trimmed)) {
      const url = new URL(trimmed)
      const segments = url.pathname.split('/').filter(Boolean)
      const tail = segments[segments.length - 1]
      if (!tail) return url.origin
      return `${url.origin}/.../${tail}${url.search}${url.hash}`
    }
  } catch {
    // Fall through to the path-based handling below.
  }

  const clean = trimmed.replace(/[?#].*$/, '')
  const segments = clean.split('/').filter(Boolean)
  if (segments.length <= 1) return trimmed
  return `.../${segments[segments.length - 1]}`
}

const formatInlineValue = (value: unknown): string => {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'number') return String(value)
  if (typeof value === 'string') {
    if (isLikelyIri(value)) return compactIri(value)
    if (isUuidLike(value)) return shortenToken(value)
    return value
  }

  if (Array.isArray(value)) {
    const entries = value.map((item) => formatInlineValue(item)).filter((entry) => entry && entry !== '—')
    return entries.length ? entries.join(', ') : '—'
  }

  if (isPlainObject(value)) {
    const preferred = ['name', 'externalId', 'label', 'title', 'code', 'slug']
    for (const key of preferred) {
      const candidate = value[key]
      if (typeof candidate === 'string' && candidate.trim()) {
        return isLikelyIri(candidate) ? compactIri(candidate) : candidate
      }
      if (typeof candidate === 'number' || typeof candidate === 'boolean') {
        return String(candidate)
      }
    }

    const entries = Object.entries(value).filter(
      ([key, candidate]) => !['@context'].includes(key) && candidate !== null && candidate !== undefined
    )
    if (!entries.length) return '—'

    const reference = extractReference(value)
    if (reference && entries.length === 1) return compactIri(reference)

    const preview = entries
      .slice(0, 3)
      .map(([key, candidate]) => `${humanizeKey(key)}: ${formatInlineValue(candidate)}`)
      .join(' | ')

    return preview || '—'
  }

  return String(value)
}

const getStringField = (record: MetadataRecord, key: string) => {
  const value = record[key]
  if (typeof value === 'string' && value.trim()) return value
  if (typeof value === 'number' || typeof value === 'boolean') return String(value)
  return undefined
}

const getFrontIdentifier = (record: MetadataRecord) => {
  for (const key of preferredFrontIdKeys) {
    const candidate = getStringField(record, key)
    if (candidate) return candidate
  }

  for (const [key, value] of Object.entries(record)) {
    if (
      /externalid$/i.test(key) &&
      key !== 'id' &&
      key !== '@id' &&
      typeof value === 'string' &&
      value.trim()
    ) {
      return value.trim()
    }
  }

  return null
}

const getResourceLabel = (record: MetadataRecord, fallback: string) => {
  const label =
    getStringField(record, 'name') ??
    getStringField(record, 'externalId') ??
    getStringField(record, 'label') ??
    getStringField(record, 'title')
  if (label) return isLikelyIri(label) ? compactIri(label) : label

  const reference = extractReference(record)
  if (reference) return compactIri(reference)

  return compactIri(fallback)
}

const getRecordSubtitle = (record: MetadataRecord, fallback: string) => {
  const primary = getResourceLabel(record, fallback)
  const identifier = formatInlineValue(record['id'] ?? record['@id'] ?? fallback)
  return identifier && identifier !== primary ? `${primary} · ${identifier}` : primary
}

const renderValue = (value: unknown, depth = 0) => {
  if (value === null || value === undefined) return <span className="text-muted/70">—</span>

  if (typeof value === 'boolean') {
    return <span className="font-medium text-ink">{value ? 'Yes' : 'No'}</span>
  }

  if (typeof value === 'number') {
    return <span className="tabular-nums text-ink">{value}</span>
  }

  if (typeof value === 'string') {
    const inline = formatInlineValue(value)
    const monospace = isLikelyIri(value) || inline !== value
    return (
      <span className={monospace ? 'font-mono break-all text-ink' : 'break-words text-ink'}>
        {inline}
      </span>
    )
  }

  if (Array.isArray(value)) {
    if (!value.length) return <span className="text-muted/70">—</span>

    const scalarItems = value.every(
      (item) =>
        item === null || item === undefined || ['string', 'number', 'boolean'].includes(typeof item) || extractReference(item)
    )

    if (scalarItems) {
      return (
        <div className="flex flex-wrap gap-2">
          {value.map((item, index) => (
            <span
              key={`${index}-${formatInlineValue(item)}`}
              className="inline-flex max-w-full items-center rounded-full border border-line bg-surface-2 px-2.5 py-1 text-xs font-medium text-ink"
            >
              <span className="truncate">{formatInlineValue(item)}</span>
            </span>
          ))}
        </div>
      )
    }

    return (
      <div className="space-y-2">
        {value.slice(0, 4).map((item, index) => (
          <div key={index} className="rounded-lg border border-line bg-surface-2 p-3">
            <div className="text-sm">{renderValue(item, depth + 1)}</div>
          </div>
        ))}
        {value.length > 4 ? <div className="text-xs text-muted/70">+{value.length - 4} more items</div> : null}
      </div>
    )
  }

  if (isPlainObject(value)) {
    const entries = Object.entries(value).filter(
      ([key, candidate]) => !['@context'].includes(key) && candidate !== null && candidate !== undefined
    )

    if (!entries.length) return <span className="text-muted/70">—</span>

    const summary = formatInlineValue(value)

    return (
      <div className="space-y-2">
        {summary ? (
          <div className="rounded-lg border border-line bg-surface-2 px-3 py-2 text-sm text-ink">
            {summary}
          </div>
        ) : null}
        <div className="space-y-2">
          {entries.slice(0, 4).map(([key, candidate]) => (
            <div key={key} className="rounded-lg border border-line bg-surface p-3">
              <div className="text-[11px] font-medium uppercase tracking-[0.18em] text-muted">{humanizeKey(key)}</div>
              <div className={`mt-1 text-sm ${textStyle}`}>{renderValue(candidate, depth + 1)}</div>
            </div>
          ))}
        </div>
        {entries.length > 4 ? <div className="text-xs text-muted/70">+{entries.length - 4} more fields</div> : null}
      </div>
    )
  }

  return <span className="text-ink">{String(value)}</span>
}

const FieldCard = ({ label, value, helper, secondary }: { label: string; value: unknown; helper?: string; secondary?: string }) => (
  <div className="rounded-2xl border border-line bg-surface p-4 shadow-sm">
    <div className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted">{label}</div>
    <div className={`mt-2 ${textStyle}`}>{renderValue(value)}</div>
    {secondary ? <div className="mt-1 text-xs text-muted">{secondary}</div> : null}
    {helper ? <div className="mt-3 text-xs leading-5 text-muted/70">{helper}</div> : null}
  </div>
)

export function MappResourceDetailPage() {
  const { resource, id } = useParams()
  const navigate = useNavigate()
  const { toast } = useToast()
  const entry = resource ? getResourceEntry(resource) : undefined
  const encodedId = getRouteResourceId(id, id)

  const schemaRef = entry?.itemSchemaRef ?? entry?.listSchemaRef
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['metadatapp', resource, id],
    queryFn: () => getMetaItemByRef(`${entry?.collectionPath}/${encodedId}`, schemaRef as SchemaRef),
    enabled: Boolean(entry?.collectionPath && id && schemaRef),
  })

  const mutation = useMutation({
    mutationFn: () => deleteMetaItem(`${entry?.collectionPath}/${encodedId}`),
    onSuccess: () => {
      toast({ title: 'Deleted', description: 'Resource removed.' })
      navigate(`/metadata/${entry?.key}`)
    },
  })

  const fields = useMemo(
    () => getDetailFieldsForEntry(entry, schemaRef, data ?? undefined),
    [data, entry, schemaRef]
  )
  const formFields = useMemo(
    () => getFormFieldsForEntry(entry, entry?.updateSchemaRef ?? schemaRef),
    [entry, schemaRef]
  )

  if (!entry || !id) {
    return <EmptyState title="Resource not found" description="Unknown MAPP API resource." />
  }

  if (!schemaRef) {
    return <EmptyState title="Schema missing" description="No schema reference for this resource." />
  }

  if (!entry.supports.read) {
    return <EmptyState title="Read disabled" description="This resource does not support read." />
  }

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (isError || !data) {
    return (
      <EmptyState
        title="Resource unavailable"
        description={(error as Error).message}
        actionLabel="Back"
        onAction={() => navigate(`/metadata/${entry.key}`)}
      />
    )
  }

  const record = data as MetadataRecord
  const resourceId = record['id'] ?? record['@id'] ?? id
  const resourceDisplayLabel = getResourceDisplayLabel(entry, 'singular')
  const resourceLabel = getResourceLabel(record, `${resourceDisplayLabel} ${resourceId}`)
  const resourceSubtitle = getRecordSubtitle(record, `${resourceDisplayLabel} ${resourceId}`)
  const routeId = getRouteResourceId(record['id'] ?? record['@id'] ?? id, id)
  const frontIdentifier = getFrontIdentifier(record)
  const overviewFields = [
    { label: 'Primary identifier', value: resourceLabel },
    { label: 'Record reference', value: resourceId },
  ].filter((item) => item.value !== null && item.value !== undefined && String(item.value).trim() !== '')

  return (
    <div className="space-y-6">
      <PageHeader
        title={`${resourceDisplayLabel} Detail`}
        subtitle={resourceSubtitle}
        actions={
          <div className="flex flex-wrap gap-2">
            {entry.supports.update ? (
              <Button asChild variant="outline">
                <Link to={`/metadata/${entry.key}/${routeId}/edit`}>Edit</Link>
              </Button>
            ) : null}
            {entry.supports.delete ? (
              <Button variant="destructive" onClick={() => mutation.mutate()} disabled={mutation.isPending}>
                Delete
              </Button>
            ) : null}
          </div>
        }
      />

      <AiActionPanel
        title="AI review tools"
        description="Generate governed previews for missing metadata explanations and export-ready content. Nothing is written automatically."
      >
        <AiSuggestionDialog
          triggerLabel="Explain missing metadata"
          title={`Explain missing ${resourceDisplayLabel.toLowerCase()} metadata`}
          description="Review what appears incomplete on this record and why it matters before making any manual updates."
          action="explain_missing_metadata"
          contextType={`${entry.key}_detail`}
          buildContext={() => ({
            resourceType: entry.key,
            resourceId: String(resourceId),
            resourceLabel,
            fields: formFields
              .filter((field) => !['@id', '@context', '@type', 'id'].includes(field.key))
              .map((field) => ({
                key: field.key,
                label: field.label,
                value: record[field.key] ?? null,
                expectation: field.helper ?? `Review the ${field.label.toLowerCase()} field before finalizing this record.`,
              })),
          })}
        />
        <AiSuggestionDialog
          triggerLabel="Generate export draft"
          title={`Generate ${resourceDisplayLabel.toLowerCase()} export content`}
          description="Prepare structured export-oriented content from this record for human review."
          action="generate_export_content"
          contextType={`${entry.key}_detail`}
          buildContext={() => ({
            resourceType: entry.key,
            resourceId: String(resourceId),
            title: resourceLabel,
            description: fields
              .slice(0, 6)
              .map((field) => `${field.label}: ${formatInlineValue(record[field.key])}`)
              .join(' | '),
          })}
        />
      </AiActionPanel>

      <Card className="border-line/70 shadow-sm">
        <CardHeader className="space-y-1">
          <CardTitle>Overview</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {overviewFields.map((field) => (
            <FieldCard key={field.label} label={field.label} value={field.value} />
          ))}
          {record['externalId'] !== undefined && record['externalId'] !== null && String(record['externalId']).trim() !== '' ? (
            <FieldCard label="External ID" value={record['externalId']} />
          ) : null}
          {record['@type'] !== undefined && record['@type'] !== null ? <FieldCard label="Type" value={record['@type']} /> : null}
        </CardContent>
      </Card>

      <Card className="border-line/70 shadow-sm">
        <CardHeader>
          <CardTitle>Fields</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {fields.map((field) => (
            <FieldCard
              key={field.key}
              label={field.label}
              value={(field.key === 'id' || field.key === '@id') && frontIdentifier ? frontIdentifier : record[field.key]}
              secondary={(field.key === 'id' || field.key === '@id') && frontIdentifier
                ? `Technical ID: ${formatInlineValue(record[field.key])}`
                : undefined}
              helper={field.helper}
            />
          ))}
        </CardContent>
      </Card>
    </div>
  )
}
