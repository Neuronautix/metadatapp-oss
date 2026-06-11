import { useMemo, type ReactNode } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { ElabftwSyncBadge } from '@/components/ElabftwSyncBadge.tsx'
import { AiActionPanel } from '@/features/ai-assistant/components/AiActionPanel'
import { AiSuggestionDialog } from '@/features/ai-assistant/components/AiSuggestionDialog'
import { getMetaCollectionByRef } from '@/metadatapp/api.ts'
import { getRouteResourceId } from '@/metadatapp/display.ts'
import { getResourceEntry } from '@/metadatapp/registry.ts'
import { extractReference, getListFieldsForEntry, getResourceDisplayLabel } from '@/metadatapp/ui-config.ts'
import type { SchemaRef } from '@/metadatapp/openapi-types.ts'

const getItemId = (item: Record<string, unknown>) => {
  const raw = item['id'] ?? item['@id']
  if (!raw) return null
  return getRouteResourceId(raw)
}

const preferredObjectLabelKeys = ['name', 'label', 'title', 'code', 'slug', 'shortName']
const preferredFrontIdKeys = ['externalId', 'projectId', 'publicId', 'code']
const primaryLabelPriorityKeys = ['name', 'title', 'label', 'externalId', 'code', 'projectId', 'publicId']
const ELABFTW_SYNCABLE_RESOURCES = ['experiments', 'procedures', 'projects'] as const
const PRIMARY_LABEL_COLUMN_KEY = '__primaryLabel'
const primaryLabelColumnTitles: Record<string, string> = {
  datasets: 'Title',
}

const isPrimitive = (value: unknown): value is string | number | boolean => {
  const valueType = typeof value
  return value === null ? false : valueType === 'string' || valueType === 'number' || valueType === 'boolean'
}

const isProbablyReference = (value: string) => /^\/|^https?:\/\/|^urn:/i.test(value)
const isUuidLike = (value: string) =>
  /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value)

const shortenToken = (token: string) => {
  if (token.length <= 24) return token
  return `${token.slice(0, 10)}…${token.slice(-6)}`
}

const formatReferenceLabel = (reference: string) => {
  const cleaned = reference.trim().replace(/[?#].*$/, '').replace(/\/+$/, '')
  if (!cleaned) return reference
  const segments = cleaned.split('/').filter(Boolean)
  const token = segments.at(-1) ?? cleaned
  return shortenToken(token)
}

const formatObjectLabel = (value: Record<string, unknown>) => {
  for (const key of preferredObjectLabelKeys) {
    const candidate = value[key]
    if (typeof candidate === 'string' && candidate.trim()) {
      return candidate.trim()
    }
  }

  const ref = extractReference(value)
  if (ref) {
    return formatReferenceLabel(ref)
  }

  const summary = Object.entries(value)
    .filter(([key, entryValue]) => !['@context', '@type', '@id', 'id'].includes(key) && isPrimitive(entryValue))
    .slice(0, 2)
    .map(([key, entryValue]) => `${key}: ${String(entryValue)}`)

  return summary.length > 0 ? summary.join(' · ') : 'Object'
}

const formatScalarLabel = (value: string | number | boolean) => {
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'number') return String(value)

  const trimmed = value.trim()
  if (!trimmed) return '—'
  if (isUuidLike(trimmed)) return shortenToken(trimmed)
  if (isProbablyReference(trimmed)) return formatReferenceLabel(trimmed)
  return trimmed
}

const getFrontIdentifier = (record: Record<string, unknown>) => {
  for (const key of preferredFrontIdKeys) {
    const value = record[key]
    if (typeof value === 'string' && value.trim()) return value.trim()
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

const getPrimaryRecordLabel = (record: Record<string, unknown>) => {
  for (const key of primaryLabelPriorityKeys) {
    const value = record[key]
    if (typeof value === 'string' && value.trim()) return formatScalarLabel(value)
    if (typeof value === 'number' || typeof value === 'boolean') return formatScalarLabel(value)
  }

  const frontIdentifier = getFrontIdentifier(record)
  if (frontIdentifier) return formatScalarLabel(frontIdentifier)

  const reference = extractReference(record)
  if (reference) return formatReferenceLabel(reference)

  const firstReadableEntry = Object.entries(record).find(
    ([key, value]) => !['@context', '@type', '@id', 'id'].includes(key) && value !== null && value !== undefined
  )
  return firstReadableEntry ? renderScalarValue(firstReadableEntry[1]) : '—'
}

const getPrimaryLabelColumnTitle = (entryKey?: string) =>
  (entryKey && primaryLabelColumnTitles[entryKey]) || 'Name'

const renderScalarValue = (value: unknown) => {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'object') {
    const ref = extractReference(value)
    return ref ? formatReferenceLabel(ref) : formatObjectLabel(value as Record<string, unknown>)
  }
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return formatScalarLabel(value)
  }
  return String(value)
}

const renderValue = (value: unknown): ReactNode => {
  if (value === null || value === undefined) {
    return <span className="text-muted/70">—</span>
  }

  if (Array.isArray(value)) {
    if (value.length === 0) {
      return <span className="text-muted/70">—</span>
    }

    const visibleValues = value.slice(0, 3).map((entry, index) => (
      <Badge
        key={`${index}-${typeof entry === 'object' ? extractReference(entry) ?? index : String(entry)}`}
        variant="outline"
        className="max-w-full truncate"
      >
        {renderScalarValue(entry)}
      </Badge>
    ))

    const remaining = value.length - visibleValues.length

    return (
      <div className="flex flex-wrap gap-1.5">
        {visibleValues}
        {remaining > 0 ? (
          <Badge variant="outline" className="border-dashed">
            +{remaining}
          </Badge>
        ) : null}
      </div>
    )
  }

  if (typeof value === 'object') {
    return (
      <Badge variant="outline" className="max-w-full truncate">
        {formatObjectLabel(value as Record<string, unknown>)}
      </Badge>
    )
  }

  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return <span className="break-words text-ink">{formatScalarLabel(value)}</span>
  }

  return <span className="break-words text-ink">{String(value)}</span>
}

const shouldShowElabFTWBadge = (entryKey: string, item: Record<string, unknown>, fieldKey: string) =>
  ELABFTW_SYNCABLE_RESOURCES.includes(entryKey as (typeof ELABFTW_SYNCABLE_RESOURCES)[number]) &&
  fieldKey === 'name' &&
  typeof item.elaftwExternalId === 'string' &&
  item.elaftwExternalId.length > 0

const renderFieldValue = (
  entryKey: string,
  fieldKey: string,
  value: unknown,
  record: Record<string, unknown>
): ReactNode => {
  const content =
    fieldKey !== 'id' && fieldKey !== '@id'
      ? renderValue(value)
      : (() => {
          const frontIdentifier = getFrontIdentifier(record)
          if (!frontIdentifier) return renderValue(value)

          return (
            <div className="space-y-1">
              <div className="font-medium text-slate-900">{frontIdentifier}</div>
              <div className="text-xs text-muted">Technical ID: {renderScalarValue(value)}</div>
            </div>
          )
        })()

  if (!shouldShowElabFTWBadge(entryKey, record, fieldKey)) {
    return content
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      {content}
      <ElabftwSyncBadge
        elaftwExternalId={typeof record.elaftwExternalId === 'string' ? record.elaftwExternalId : undefined}
      />
    </div>
  )
}

export function MappResourceListPage() {
  const { resource } = useParams()
  const [searchParams, setSearchParams] = useSearchParams()
  const entry = resource ? getResourceEntry(resource) : undefined
  const page = Number(searchParams.get('page') ?? '1')
  const search = searchParams.get('search') ?? ''
  const filterField = searchParams.get('filterField') ?? ''
  const filterValue = searchParams.get('filterValue') ?? ''
  const sortField = searchParams.get('sortField') ?? ''
  const sortDirection = searchParams.get('sortDirection') === 'desc' ? 'desc' : 'asc'

  const schemaRef = entry?.listSchemaRef ?? entry?.itemSchemaRef
  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['metadatapp', resource, schemaRef],
    queryFn: () => getMetaCollectionByRef(entry?.collectionPath ?? '', schemaRef as SchemaRef, { page: 1, itemsPerPage: 500 }),
    enabled: Boolean(entry?.collectionPath && schemaRef),
  })

  const fields = useMemo(() => getListFieldsForEntry(entry), [entry])
  const resourceLabel = getResourceDisplayLabel(entry)
  const resourceSingularLabel = getResourceDisplayLabel(entry, 'singular')
  const displayFields = useMemo(() => {
    const hasPrimaryField = fields.some((field) => primaryLabelPriorityKeys.includes(field.key))
    if (hasPrimaryField) return fields

    return [
      {
        key: PRIMARY_LABEL_COLUMN_KEY,
        label: getPrimaryLabelColumnTitle(entry?.key),
        kind: 'string',
        required: false,
        input: 'text',
      },
      ...fields,
    ]
  }, [entry?.key, fields])
  const processedData = useMemo(() => {
    const rows = data?.data ?? []
    const searchableFields = displayFields.map((field) => field.key)
    const normalizedSearch = search.trim().toLowerCase()
    const normalizedFilter = filterValue.trim().toLowerCase()

    const filtered = rows.filter((item) => {
      const record = item as Record<string, unknown>
      if (normalizedSearch) {
        const haystack = searchableFields
          .map((field) => field === PRIMARY_LABEL_COLUMN_KEY ? getPrimaryRecordLabel(record) : renderScalarValue(record[field]))
          .join(' ')
          .toLowerCase()
        if (!haystack.includes(normalizedSearch)) return false
      }

      if (filterField && normalizedFilter) {
        const value = filterField === PRIMARY_LABEL_COLUMN_KEY ? getPrimaryRecordLabel(record) : renderScalarValue(record[filterField])
        if (!String(value).toLowerCase().includes(normalizedFilter)) return false
      }

      return true
    })

    if (!sortField) return filtered

    return [...filtered].sort((left, right) => {
      const leftRecord = left as Record<string, unknown>
      const rightRecord = right as Record<string, unknown>
      const leftValue = sortField === PRIMARY_LABEL_COLUMN_KEY ? getPrimaryRecordLabel(leftRecord) : renderScalarValue(leftRecord[sortField])
      const rightValue = sortField === PRIMARY_LABEL_COLUMN_KEY ? getPrimaryRecordLabel(rightRecord) : renderScalarValue(rightRecord[sortField])
      const result = String(leftValue).localeCompare(String(rightValue), undefined, { numeric: true, sensitivity: 'base' })
      return sortDirection === 'asc' ? result : -result
    })
  }, [data?.data, displayFields, filterField, filterValue, search, sortDirection, sortField])
  const pageSize = 30
  const totalPages = Math.max(1, Math.ceil(processedData.length / pageSize))
  const visibleData = processedData.slice((page - 1) * pageSize, page * pageSize)
  const updateListParams = (updates: Record<string, string | undefined>) => {
    const next = new URLSearchParams(searchParams)
    Object.entries(updates).forEach(([key, value]) => {
      if (!value) {
        next.delete(key)
      } else {
        next.set(key, value)
      }
    })
    setSearchParams(next)
  }
  const setPageParam = (nextPage: number) => updateListParams({ page: String(nextPage) })
  const toggleSort = (field: string) => {
    updateListParams({
      sortField: field,
      sortDirection: sortField === field && sortDirection === 'asc' ? 'desc' : 'asc',
      page: '1',
    })
  }

  if (!entry) {
    return <EmptyState title="Resource not found" description="Unknown MAPP API resource." />
  }

  if (!schemaRef) {
    return <EmptyState title="Schema missing" description="No schema reference for this resource." />
  }

  if (!entry.supports.list) {
    return <EmptyState title="Listing disabled" description="This resource does not support listing." />
  }

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-10 w-52" />
        <Skeleton className="h-64 w-full" />
      </div>
    )
  }

  if (isError) {
    return (
      <EmptyState
        title="Resource unavailable"
        description={(error as Error).message}
        actionLabel="Retry"
        onAction={() => setPageParam(page)}
      />
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={resourceLabel}
        subtitle={`MAPP API collection: ${entry.collectionPath}`}
        actions={
          entry.supports.create ? (
            <Button asChild>
              <Link to={`/metadata/${entry.key}/new`}>New {resourceSingularLabel}</Link>
            </Button>
          ) : null
        }
      />

      <AiActionPanel
        title="AI review filters"
        description="Draft preview-only review filters and list focus prompts for this collection. The AI output is advisory and does not change the list automatically."
      >
        <AiSuggestionDialog
          triggerLabel="Draft review filters"
          title={`Draft ${resourceLabel} review filters`}
          description="Generate a preview-only filter draft or review prompt for this collection."
          action="draft_query_filters"
          contextType={`${entry.key}_list`}
          promptLabel="What do you want to review?"
          promptPlaceholder={`Example: find ${resourceLabel.toLowerCase()} with missing identifiers or stale updates`}
          buildContext={() => ({
            resourceType: entry.key,
            resourceId: 'list',
            resourceLabel,
            collectionPath: entry.collectionPath,
            page,
            totalItems: data?.total ?? 0,
            visibleColumns: displayFields.map((field) => ({ key: field.key, label: field.label })),
          })}
        />
      </AiActionPanel>

      <Card className="overflow-hidden p-0">
        <div className="border-b border-line bg-surface/70 px-4 py-4 sm:px-6">
          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div className="space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant="outline">Collection</Badge>
                <Badge variant="outline">{visibleData.length} rows on this page</Badge>
                <Badge variant="outline">{processedData.length} matching rows</Badge>
                <Badge variant="outline">{displayFields.length} visible columns</Badge>
                <Badge variant={entry.supports.update ? 'success' : 'outline'}>
                  {entry.supports.update ? 'Editable' : 'Read only'}
                </Badge>
              </div>
              <p className="text-sm text-muted">
                Showing page {page} of {totalPages}. Values are normalized into compact labels so references stay readable.
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              {displayFields.map((field) => (
                <Badge key={field.key} variant="outline" className="max-w-full truncate">
                  {field.label}
                </Badge>
              ))}
            </div>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-3 border-b border-line bg-white px-4 py-3 sm:px-6">
          <input
            value={search}
            onChange={(event) => updateListParams({ search: event.target.value, page: '1' })}
            placeholder="Search visible columns"
            className="h-9 min-w-56 rounded-full border border-line px-3 text-sm"
          />
          <select
            value={filterField}
            onChange={(event) => updateListParams({ filterField: event.target.value, page: '1' })}
            className="h-9 rounded-full border border-line bg-white px-3 text-sm"
          >
            <option value="">Filter field</option>
            {displayFields.map((field) => (
              <option key={field.key} value={field.key}>{field.label}</option>
            ))}
          </select>
          <input
            value={filterValue}
            onChange={(event) => updateListParams({ filterValue: event.target.value, page: '1' })}
            placeholder="Filter value"
            className="h-9 min-w-44 rounded-full border border-line px-3 text-sm"
            disabled={!filterField}
          />
          <Button
            variant="ghost"
            size="sm"
            onClick={() => updateListParams({ search: undefined, filterField: undefined, filterValue: undefined, sortField: undefined, sortDirection: undefined, page: '1' })}
          >
            Reset
          </Button>
        </div>

        <div className="overflow-x-auto">
          <Table className="min-w-[760px]">
            <TableHeader>
              <TableRow>
                {displayFields.map((field) => (
                  <TableHead key={field.key} aria-label={field.label}>
                    <button type="button" className="inline-flex items-center gap-1 font-semibold" onClick={() => toggleSort(field.key)}>
                      {field.label}
                      <span className="text-xs text-slate-400">{sortField === field.key ? sortDirection : 'sort'}</span>
                    </button>
                  </TableHead>
                ))}
                <TableHead className="whitespace-nowrap">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {visibleData.map((item, index) => {
                const itemRecord = item as Record<string, unknown>
                const itemId = getItemId(itemRecord)
                return (
                  <TableRow key={itemId ?? index}>
                     {displayFields.map((field) => (
                       <TableCell key={field.key} className="align-top">
                         <div className="min-w-0 max-w-[18rem]">
                           {field.key === PRIMARY_LABEL_COLUMN_KEY
                             ? renderValue(getPrimaryRecordLabel(itemRecord))
                             : renderFieldValue(entry.key, field.key, itemRecord[field.key], itemRecord)}
                         </div>
                       </TableCell>
                     ))}
                    <TableCell className="align-top">
                      <div className="flex flex-wrap gap-2">
                        {itemId && (
                          <Button size="sm" variant="outline" asChild>
                            <Link to={`/metadata/${entry.key}/${itemId}`}>View</Link>
                          </Button>
                        )}
                        {itemId && entry.supports.update ? (
                          <Button size="sm" variant="ghost" asChild>
                            <Link to={`/metadata/${entry.key}/${itemId}/edit`}>Edit</Link>
                          </Button>
                        ) : null}
                      </div>
                    </TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </div>
      </Card>

      <div className="flex items-center justify-between text-sm text-muted">
        <span>
          Page {page} of {totalPages}
        </span>
        <div className="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            disabled={page <= 1}
            onClick={() => setPageParam(Math.max(1, page - 1))}
          >
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            disabled={page >= totalPages}
            onClick={() => setPageParam(Math.min(totalPages, page + 1))}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  )
}
