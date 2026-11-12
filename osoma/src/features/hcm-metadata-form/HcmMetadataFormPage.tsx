import { Fragment, useState } from 'react'
import { Check, Copy, Download, Layers, Network, Ruler, Tag } from 'lucide-react'
import { PageHeader } from '@/components/PageHeader.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { cn } from '@/lib/utils.ts'
import { groupFieldsBySection, useHcmCanonicalForm } from './hcm-metadata-form.api.ts'
import type {
  CanonicalForm,
  CanonicalFormField,
  CanonicalFormSectionGroup,
} from './hcm-metadata-form.types.ts'

/** Small copy-to-clipboard button with transient confirmation; no toast needed. */
function CopyButton({ value, label }: { value: string; label: string }) {
  const [copied, setCopied] = useState(false)
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      onClick={() => {
        void navigator.clipboard?.writeText(value).then(() => {
          setCopied(true)
          window.setTimeout(() => setCopied(false), 1500)
        })
      }}
      className="inline-flex h-5 w-5 items-center justify-center rounded text-muted transition-colors hover:bg-surface-2 hover:text-ink"
    >
      {copied ? <Check className="h-3 w-3 text-success" /> : <Copy className="h-3 w-3" />}
    </button>
  )
}

function downloadFormJson(form: CanonicalForm) {
  const blob = new Blob([JSON.stringify(form, null, 2)], { type: 'application/json' })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = `${form.domain || 'canonical-form'}.json`
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}

function FieldRow({ field }: { field: CanonicalFormField }) {
  return (
    <div className="rounded-[var(--radius-xl)] border border-line bg-surface-2/40 p-4">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="flex flex-wrap items-center gap-2">
            <span className="font-medium text-ink">{field.label}</span>
            {field.required ? (
              <Badge variant="critical">Required</Badge>
            ) : (
              <Badge variant="outline">Optional</Badge>
            )}
          </div>
          {field.description ? (
            <p className="mt-1 text-sm text-muted">{field.description}</p>
          ) : null}
        </div>
        <code className="rounded-md bg-surface px-2 py-1 text-xs text-muted">{field.localKey}</code>
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        {field.sourceFieldReference ? (
          <span className="inline-flex items-center gap-1">
            <Badge variant="default" className="gap-1">
              <Tag className="h-3 w-3" />
              CDE: {field.sourceFieldReference}
            </Badge>
            <CopyButton
              value={field.sourceFieldReference}
              label={`Copy CDE reference ${field.sourceFieldReference}`}
            />
          </span>
        ) : null}
        {field.datatype ? (
          <Badge variant="outline">datatype: {field.datatype}</Badge>
        ) : null}
        {field.unitConstraint ? (
          <Badge variant="outline" className="gap-1">
            <Ruler className="h-3 w-3" />
            unit: {field.unitConstraint}
          </Badge>
        ) : null}
      </div>

      {field.allowedValues.length > 0 ? (
        <div className="mt-3">
          <p className="text-xs font-semibold uppercase tracking-wide text-muted">Permissible values</p>
          <div className="mt-1.5 flex flex-wrap gap-1.5">
            {field.allowedValues.map((allowed) => {
              const term = allowed.ontologyTerm
              return (
                <Badge key={allowed.value} variant="success" className="gap-1">
                  {allowed.value}
                  {term ? (
                    <a
                      href={term}
                      target="_blank"
                      rel="noreferrer"
                      className="underline decoration-dotted"
                      title={term}
                      aria-label={`Open ontology term for ${allowed.value} in a new tab`}
                    >
                      ↗
                    </a>
                  ) : null}
                </Badge>
              )
            })}
          </div>
        </div>
      ) : null}

      {field.ontologyMappings.length > 0 ? (
        <div className="mt-3">
          <p className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-muted">
            <Network className="h-3 w-3" />
            Ontology mappings
          </p>
          <div className="mt-1.5 flex flex-wrap gap-1.5">
            {field.ontologyMappings.map((mapping, index) => {
              const badge = (
                <Badge variant="warning" className="gap-1">
                  {mapping.source ? <span className="font-semibold">{mapping.source}</span> : null}
                  {mapping.label ?? mapping.iri ?? 'term'}
                </Badge>
              )
              const key = mapping.iri ?? `${mapping.source ?? ''}:${mapping.label ?? index}`
              return mapping.iri ? (
                <a
                  key={key}
                  href={mapping.iri}
                  target="_blank"
                  rel="noreferrer"
                  title={mapping.iri}
                  aria-label={`Open ontology mapping ${mapping.label ?? mapping.iri} in a new tab`}
                >
                  {badge}
                </a>
              ) : (
                <span key={key} title={mapping.label ?? undefined}>
                  {badge}
                </span>
              )
            })}
          </div>
        </div>
      ) : null}
    </div>
  )
}

function SectionCard({ group, index }: { group: CanonicalFormSectionGroup; index: number }) {
  const { section, fields, isaAnnotation } = group
  return (
    <Card>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 className="flex items-center gap-2 font-display text-lg">
            <span className="text-muted">{index + 1}.</span>
            {section.title}
          </h3>
          {section.description ? (
            <p className="mt-1 text-sm text-muted">{section.description}</p>
          ) : null}
        </div>
        {isaAnnotation ? (
          <Badge variant="default" className="gap-1">
            <Layers className="h-3 w-3" />
            ISA: {isaAnnotation}
          </Badge>
        ) : null}
      </div>

      <div className="mt-4 space-y-3">
        {fields.length === 0 ? (
          <p className="text-sm text-muted">No fields defined for this section.</p>
        ) : (
          fields.map((field) => <FieldRow key={field.id} field={field} />)
        )}
      </div>
    </Card>
  )
}

function LoadingState() {
  return (
    <div className="space-y-4">
      {[0, 1, 2].map((key) => (
        <Card key={key}>
          <Skeleton className="h-6 w-48" />
          <div className="mt-4 space-y-3">
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-20 w-full" />
          </div>
        </Card>
      ))}
    </div>
  )
}

export function HcmMetadataFormPage() {
  const { data: form, isLoading, isError, error } = useHcmCanonicalForm()

  return (
    <div className="space-y-6">
      <PageHeader
        title="HCM Minimal Metadata Form"
        subtitle="FAIR-aligned Home Cage Monitoring metadata, grouped along the ISA Investigation/Study/Assay hierarchy."
        actions={
          form ? (
            <div className="flex items-center gap-2">
              <Badge variant={form.status === 'active' ? 'success' : 'outline'}>{form.status}</Badge>
              <Badge variant="outline">v{form.version}</Badge>
              <Button variant="outline" size="sm" onClick={() => downloadFormJson(form)}>
                <Download className="mr-2 h-4 w-4" />
                Download JSON
              </Button>
            </div>
          ) : null
        }
      />

      {isLoading ? <LoadingState /> : null}

      {isError ? (
        <EmptyState
          title="Unable to load the HCM form"
          description={error instanceof Error ? error.message : 'The canonical form could not be retrieved.'}
        />
      ) : null}

      {!isLoading && !isError && form ? (
        (() => {
          const groups = groupFieldsBySection(form)
          if (groups.length === 0) {
            return (
              <EmptyState
                title="No sections defined"
                description="This canonical form does not declare any sections yet."
              />
            )
          }
          return (
            <Fragment>
              {form.description ? (
                <Card className={cn('bg-surface-2/40')}>
                  <p className="text-sm text-muted">{form.description}</p>
                </Card>
              ) : null}
              <div className="space-y-4">
                {groups.map((group, index) => (
                  <SectionCard key={group.section.id} group={group} index={index} />
                ))}
              </div>
            </Fragment>
          )
        })()
      ) : null}
    </div>
  )
}
