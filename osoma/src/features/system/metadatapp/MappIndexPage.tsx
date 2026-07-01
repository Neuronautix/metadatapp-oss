import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { BookOpenCheck, Database, FileSearch, FolderTree } from 'lucide-react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Input } from '@/components/ui/input.tsx'
import { resourceRegistry } from '@/metadatapp/registry.ts'
import { getResourceDisplayLabel } from '@/metadatapp/ui-config.ts'

const resourceDescriptions: Record<string, string> = {
  investigations: 'Track research programs, objectives, dates, and related studies.',
  studies: 'Organize experimental workflows, assay links, and protocol-level metadata.',
  subjects: 'Manage subject-level records such as species, strain, and housing links.',
  users: 'Review user profiles, accounts, roles, and connected app access.',
  laboratories: 'Maintain laboratory records and institution-facing metadata.',
  connected_apps: 'Configure integrations and sync settings for external platforms.',
  accounts: 'Manage tenant-level accounts, users, and linked applications.',
  cages: 'Review cage setup, housing metadata, and related subject assignments.',
}

const capabilityLabel = (supported: boolean, label: string) => (supported ? label : null)

export function MappIndexPage() {
  const [query, setQuery] = useState('')
  const stats = useMemo(
    () => ({
      total: resourceRegistry.length,
      listable: resourceRegistry.filter((entry) => entry.supports.list).length,
      creatable: resourceRegistry.filter((entry) => entry.supports.create).length,
    }),
    []
  )
  const filtered = useMemo(() => {
    const keyword = query.trim().toLowerCase()
    if (!keyword) return resourceRegistry
    return resourceRegistry.filter((entry) => {
      const displayLabel = getResourceDisplayLabel(entry)
      return displayLabel.toLowerCase().includes(keyword) || entry.key.toLowerCase().includes(keyword)
    })
  }, [query])

  return (
    <div className="space-y-6">
      <PageHeader
        title="Metadata Catalog"
        subtitle="Browse the metadata collections exposed by the platform, then open a collection to review records, linked entities, and available actions."
      />

      <div className="flex flex-wrap items-center gap-3">
        <Input
          placeholder="Search collections by name or API key"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
        />
      </div>

      <div className="grid gap-4 lg:grid-cols-[1.5fr_1fr]">
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-ink">
              <FolderTree className="h-5 w-5 text-brand" />
              What this workspace is for
            </CardTitle>
            <CardDescription>
              Use the catalog to inspect each metadata collection, check which operations are supported, and jump into the records behind investigations, studies, subjects, integrations, and admin data.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-3 text-sm text-muted md:grid-cols-2">
            <div className="rounded-2xl border border-line bg-surface-2 p-4">
              <p className="flex items-center gap-2 font-medium text-ink">
                <FileSearch className="h-4 w-4 text-brand" />
                Browse collections
              </p>
              <p className="mt-2">Open a collection to list records, inspect detail pages, and follow linked resources without digging through raw API responses.</p>
            </div>
            <div className="rounded-2xl border border-line bg-surface-2 p-4">
              <p className="flex items-center gap-2 font-medium text-ink">
                <BookOpenCheck className="h-4 w-4 text-brand" />
                Check capabilities
              </p>
              <p className="mt-2">Each card shows whether the collection supports listing, creation, read-only review, or record updates before you open it.</p>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2 text-ink">
              <Database className="h-5 w-5 text-brand" />
              Catalog overview
            </CardTitle>
          </CardHeader>
          <CardContent className="grid gap-3 text-sm">
            <div className="rounded-2xl border border-line bg-surface-2 p-4">
              <p className="text-xs uppercase tracking-[0.2em] text-muted">Collections</p>
              <p className="mt-1 text-2xl font-display text-ink">{stats.total}</p>
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="rounded-2xl border border-line bg-surface-2 p-4">
                <p className="text-xs uppercase tracking-[0.2em] text-muted">Listable</p>
                <p className="mt-1 text-xl font-display text-ink">{stats.listable}</p>
              </div>
              <div className="rounded-2xl border border-line bg-surface-2 p-4">
                <p className="text-xs uppercase tracking-[0.2em] text-muted">Creatable</p>
                <p className="mt-1 text-xl font-display text-ink">{stats.creatable}</p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        {filtered.map((entry) => {
          const displayLabel = getResourceDisplayLabel(entry)

          return (
            <Card key={entry.key} className="p-5">
              <div className="space-y-3">
                <div className="space-y-1">
                  <p className="text-xs uppercase tracking-[0.2em] text-muted">{entry.key}</p>
                  <h3 className="font-display text-lg text-ink">{displayLabel}</h3>
                </div>
                <p className="text-sm text-muted">
                  {resourceDescriptions[entry.key] ??
                    `Browse and manage ${displayLabel.toLowerCase()} records from the metadata API.`}
                </p>
                <div className="flex flex-wrap gap-2 text-xs text-muted">
                  {[
                    capabilityLabel(entry.supports.list, 'Browse'),
                    capabilityLabel(entry.supports.create, 'Create'),
                    capabilityLabel(entry.supports.read, 'Detail'),
                    capabilityLabel(entry.supports.update, 'Edit'),
                  ]
                    .filter(Boolean)
                    .map((capability) => (
                      <span key={capability} className="rounded-full border border-line bg-surface-2 px-3 py-1">
                        {capability}
                      </span>
                    ))}
                </div>
                <p className="text-xs text-muted">{entry.collectionPath}</p>
                <Link
                  to={`/metadata/${entry.key}`}
                  className="inline-flex items-center gap-2 text-sm font-semibold text-ink"
                >
                  Open collection →
                </Link>
              </div>
            </Card>
          )
        })}
      </div>
    </div>
  )
}
