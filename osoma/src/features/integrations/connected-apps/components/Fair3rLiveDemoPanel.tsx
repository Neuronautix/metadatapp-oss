import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { fetchFair3rDataset } from '../connected-apps.api.ts'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { formatDate, formatDateTime } from '@/lib/format.ts'
import { ExternalLink } from 'lucide-react'

const LIVE_DEMO_DATASET = 'my-top-project-to-share'

interface Fair3rLiveDemoPanelProps {
    appId: string
    externalUrl?: string
    datasetName?: string
    label?: string
}

// ─── helpers ─────────────────────────────────────────────────────────────────

type Rec = Record<string, unknown>

const asRec = (v: unknown): Rec =>
    v && typeof v === 'object' && !Array.isArray(v) ? v as Rec : {}

const asArr = <T,>(v: unknown): T[] => (Array.isArray(v) ? v as T[] : [])

const str = (v: unknown): string | null =>
    typeof v === 'string' && v.trim() ? v : null

const isUrl = (v: string | null | undefined): v is string =>
    !!v && (v.startsWith('http://') || v.startsWith('https://'))

const formatMaybeDateTime = (value: string | null): string | null => {
    if (!value) return null
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value
    return formatDateTime(value)
}

const firstTitle = (fdf: Rec | null, fallback: string | null, name: string): string =>
    str(asRec(asArr<Rec>(fdf?.titles)[0])?.title) ?? fallback ?? name

const fullName = (c: Rec): string => {
    const given = str(c.givenName)
    const family = str(c.familyName)
    if (given || family) return [given, family].filter(Boolean).join(' ')
    return str(c.name) ?? 'Unknown'
}

const SUBJECT_PREFIXES: Record<string, string> = {
    geneAccessionId:        'Gene: ',
    geneChromosomeLocation: 'Gene locus: ',
    alleleAccessionId:      'Allele: ',
    geneMutationType:       '',
    TransgeneOrigin:        'Transgene origin: ',
    speciesBackground:      'Strain: ',
}

const subjectText = (s: Rec, scheme: string): string => {
    const raw = str(s.subject) ?? '—'
    const prefix = SUBJECT_PREFIXES[scheme]
    if (prefix !== undefined && raw.startsWith(prefix)) return raw.slice(prefix.length)
    return raw
}

const GENE_FIELDS: [string, string][] = [
    ['geneAccessionId',        'Gene'],
    ['geneChromosomeLocation', 'Gene Chromosome Location'],
    ['alleleAccessionId',      'Allele'],
    ['geneMutationType',       'Mutation Type'],
    ['TransgeneOrigin',        'Transgene Origin'],
]

const KNOWN_SUBJECT_SCHEMES = new Set([
    'NCBITaxon',
    'speciesBackground',
    ...GENE_FIELDS.map(([scheme]) => scheme),
    'ChEBI',
    'EFO',
    'DOID',
    'UBERON',
])

const labelize = (key: string): string => (
    key
        .replace(/^_+/, '')
        .replace(/[_-]+/g, ' ')
        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
        .replace(/\b\w/g, (char) => char.toUpperCase())
)

const compactValue = (value: unknown): string | null => {
    if (value === null || value === undefined || value === '') return null
    if (typeof value === 'boolean') return value ? 'true' : 'false'
    if (typeof value === 'number') return String(value)
    if (typeof value === 'string') return value.trim() || null
    if (Array.isArray(value)) {
        const parts = value
            .map((item) => compactValue(item))
            .filter((item): item is string => !!item)
        return parts.length > 0 ? parts.join(' • ') : null
    }
    if (typeof value === 'object') {
        const record = value as Rec
        const preferred = str(record.title)
            ?? str(record.name)
            ?? str(record.display_name)
            ?? str(record.label)
            ?? str(record.value)
            ?? str(record.identifier)
            ?? str(record.subject)
        if (preferred) return preferred

        const entries = Object.entries(record)
            .map(([key, item]) => {
                const formatted = compactValue(item)
                return formatted ? `${labelize(key)}: ${formatted}` : null
            })
            .filter((item): item is string => !!item)

        return entries.length > 0 ? entries.join(' • ') : null
    }

    return null
}

const jsonPreview = (value: unknown): string => {
    try {
        return JSON.stringify(value, null, 2)
    } catch {
        return String(value)
    }
}

// ─── sub-components ──────────────────────────────────────────────────────────

function Section({ title, icon, color, children }: {
    title: string; icon: string; color: string; children: ReactNode
}) {
    return (
        <div className="border-l-[3px] pl-4 py-0.5" style={{ borderLeftColor: color }}>
            <div className="flex items-center gap-2 mb-3">
                <span className="text-base leading-none">{icon}</span>
                <h4 className="text-xs font-bold uppercase tracking-widest text-muted">{title}</h4>
            </div>
            {children}
        </div>
    )
}

function Field({ label, value, href }: { label: string; value: string; href?: string | null }) {
    return (
        <div className="flex items-start gap-4 py-1.5 border-b border-line/40 last:border-0 text-sm">
            <span className="w-44 shrink-0 text-xs text-muted leading-5">{label}</span>
            <span className="min-w-0 flex-1 text-ink leading-5 break-all">
                {href ? (
                    <a href={href} target="_blank" rel="noopener noreferrer"
                       className="inline-flex items-center gap-1 text-link hover:underline">
                        {value}
                        <ExternalLink className="h-3 w-3 shrink-0" />
                    </a>
                ) : value}
            </span>
        </div>
    )
}

function CkanSummary({ dataset, ckan }: {
    dataset: { author?: string | null; authorEmail?: string | null; metadataCreated?: string | null; metadataModified?: string | null } | undefined
    ckan: Rec
}) {
    const author = dataset?.author ?? str(ckan.author)
    const state = str(ckan.state)
    const created = formatMaybeDateTime(dataset?.metadataCreated ?? str(ckan.metadata_created))
    const modified = formatMaybeDateTime(dataset?.metadataModified ?? str(ckan.metadata_modified))
    const org = asRec(ckan.organization)
    const orgName = str(org.title) ?? str(org.name)

    if (!author && !state && !created && !modified && !orgName) return null

    return (
        <Section title="FAIR3R Package" icon="📦" color="#0f766e">
            {author && <Field label="Author" value={author} />}
            {state && <Field label="State" value={state} />}
            {modified && <Field label="Last Updated" value={modified} />}
            {created && <Field label="Created" value={created} />}
            {orgName && <Field label="Organization" value={orgName} />}
        </Section>
    )
}

function CreatorCard({ creator, roles }: { creator: Rec; roles: string[] }) {
    const name = fullName(creator)
    const affiliations = asArr<Rec>(creator.affiliation)
    const nameIds = asArr<Rec>(creator.nameIdentifiers)
    const orcid = nameIds.find(n => str(n.nameIdentifierScheme)?.toUpperCase() === 'ORCID')
    const orcidUrl = str(orcid?.nameIdentifier)

    return (
        <div className="rounded-xl border border-line bg-surface p-3 space-y-2">
            <div className="flex items-start justify-between gap-2">
                <span className="font-semibold text-sm text-ink">{name}</span>
                {str(creator.nameType) && (
                    <Badge variant="outline" className="text-[10px] border-line text-muted shrink-0">
                        {str(creator.nameType)}
                    </Badge>
                )}
            </div>
            {affiliations.map((a, i) => {
                const aff = str(a.affiliation)
                const affUrl = str(a.affiliationIdentifier)
                if (!aff) return null
                return (
                    <p key={i} className="text-xs text-muted">
                        {isUrl(affUrl) ? (
                            <a href={affUrl} target="_blank" rel="noopener noreferrer"
                               className="inline-flex items-center gap-1 text-link hover:underline">
                                {aff}<ExternalLink className="h-3 w-3" />
                            </a>
                        ) : aff}
                    </p>
                )
            })}
            {orcidUrl && (
                <p className="text-xs">
                    <a href={isUrl(orcidUrl) ? orcidUrl : `https://orcid.org/${orcidUrl}`}
                       target="_blank" rel="noopener noreferrer"
                       className="inline-flex items-center gap-1 text-link hover:underline font-mono">
                        ORCID <ExternalLink className="h-3 w-3" />
                    </a>
                </p>
            )}
            {roles.length > 0 && (
                <div className="flex flex-wrap items-center gap-1 pt-0.5">
                    <span className="text-[10px] text-muted mr-0.5 leading-5">CRediT:</span>
                    {roles.map(role => (
                        <Badge key={role} variant="outline"
                               className="text-[10px] border-line/60 bg-surface-2 text-muted">
                            {role}
                        </Badge>
                    ))}
                </div>
            )}
        </div>
    )
}

function RawMetadataSection({ title, icon, metadata, skipKeys = [] }: { title: string; icon: string; metadata: Rec; skipKeys?: string[] }) {
    const skipped = new Set(skipKeys)
    const entries = Object.entries(metadata)
        .filter(([key, value]) => !skipped.has(key) && compactValue(value))
        .sort(([a], [b]) => a.localeCompare(b))

    if (entries.length === 0) return null

    return (
        <Section title={title} icon={icon} color="#64748b">
            <div className="space-y-2">
                {entries.map(([key, value]) => {
                    const formatted = compactValue(value)
                    if (!formatted) return null

                    const showJson = typeof value === 'object' && value !== null
                    return (
                        <details key={key} className="rounded-lg border border-line bg-surface px-3 py-2">
                            <summary className="cursor-pointer text-xs font-semibold uppercase tracking-widest text-muted">
                                {labelize(key)}
                            </summary>
                            {showJson ? (
                                <pre className="mt-2 max-h-56 overflow-auto whitespace-pre-wrap text-[10px] leading-4 text-ink/80">
                                    {jsonPreview(value)}
                                </pre>
                            ) : (
                                <p className="mt-2 break-words text-sm text-ink">{formatted}</p>
                            )}
                        </details>
                    )
                })}
            </div>
        </Section>
    )
}

// CkanFallback — renders when fdf is null (dataset not pushed by Metadatapp)
function CkanFallback({
    dataset,
    ckan,
}: {
    dataset: { author?: string | null; authorEmail?: string | null; licenseId?: string | null; metadataCreated?: string | null; metadataModified?: string | null; resources: { id: string | null; name: string | null; url: string | null; format: string | null; mimetype: string | null; description: string | null }[] } | undefined
    ckan: Rec
}) {
    const extras = asArr<Rec>(ckan.extras).filter(e => {
        const k = str(e.key)
        const v = str(e.value)
        // Skip internal/empty extras and raw JSON blobs
        if (!k || !v) return false
        if (v.startsWith('{') || v.startsWith('[')) return false
        return true
    })

    const tags = asArr<Rec>(ckan.tags)
        .map(t => str(t.display_name) ?? str(t.name))
        .filter((t): t is string => !!t)

    const org = asRec(ckan.organization)
    const orgName = str(org.title) ?? str(org.name)
    const orgUrl = str(org.description)

    const licenseTitle = str(ckan.license_title)
    const licenseUrl = str(ckan.license_url)
    const resources = dataset?.resources ?? []
    const state = str(ckan.state)

    return (
        <div className="space-y-5">
            <div className="rounded-lg border border-amber-200 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-900/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                No FAIR3R metadata JSON found for this dataset. Showing available CKAN fields below.
            </div>

            {/* Basic info */}
            <Section title="Basic Information" icon="📄" color="#1f6feb">
                {dataset?.author && <Field label="Author" value={dataset.author} />}
                {state && <Field label="State" value={state} />}
                {licenseTitle && (
                    <Field label="License" value={licenseTitle} href={isUrl(licenseUrl) ? licenseUrl : null} />
                )}
                {!licenseTitle && dataset?.licenseId && (
                    <Field label="License" value={dataset.licenseId} />
                )}
                {dataset?.metadataCreated && (
                    <Field label="Created" value={formatMaybeDateTime(dataset.metadataCreated) ?? dataset.metadataCreated} />
                )}
                {dataset?.metadataModified && (
                    <Field label="Last updated" value={formatMaybeDateTime(dataset.metadataModified) ?? dataset.metadataModified} />
                )}
            </Section>

            {/* Organization */}
            {orgName && (
                <Section title="Organization" icon="🏛️" color="#b7791f">
                    <Field label="Name" value={orgName} href={null} />
                    {orgUrl && isUrl(orgUrl) && <Field label="URL" value={orgUrl} href={orgUrl} />}
                </Section>
            )}

            {/* Tags */}
            {tags.length > 0 && (
                <Section title="Tags" icon="🏷️" color="#718096">
                    <div className="flex flex-wrap gap-1.5 pt-1">
                        {tags.map(t => (
                            <Badge key={t} variant="outline" className="border-line text-ink text-xs">{t}</Badge>
                        ))}
                    </div>
                </Section>
            )}

            {/* Extras */}
            {extras.length > 0 && (
                <Section title="Additional Metadata" icon="📋" color="#6f42c1">
                    {extras.map((e, i) => (
                        <Field key={i}
                               label={str(e.key)!}
                               value={str(e.value)!}
                               href={isUrl(str(e.value)) ? str(e.value)! : null} />
                    ))}
                </Section>
            )}

            {/* Files */}
            {resources.length > 0 && (
                <Section title={`Files & Resources (${resources.length})`} icon="📁" color="#2d3748">
                    {resources.map((res, i) => {
                        const name = res.name ?? `Resource ${i + 1}`
                        const fmt  = [res.format, res.mimetype].filter(Boolean).join(' / ')
                        return (
                            <div key={res.id ?? `r-${i}`}
                                 className="flex items-start justify-between gap-3 py-1.5 border-b border-line/40 last:border-0">
                                <div className="min-w-0 flex-1">
                                    {res.url ? (
                                        <a href={res.url} target="_blank" rel="noopener noreferrer"
                                           className="inline-flex items-center gap-1 text-sm text-link hover:underline">
                                            {name}<ExternalLink className="h-3 w-3 shrink-0" />
                                        </a>
                                    ) : (
                                        <span className="text-sm text-ink">{name}</span>
                                    )}
                                    {res.description && (
                                        <p className="text-xs text-muted mt-0.5 line-clamp-1">{res.description}</p>
                                    )}
                                </div>
                                {fmt && <span className="text-[10px] text-muted shrink-0 font-mono">{fmt}</span>}
                            </div>
                        )
                    })}
                </Section>
            )}

            <RawMetadataSection
                title="Raw FAIR3R / CKAN Package"
                icon="🧰"
                metadata={ckan}
                skipKeys={['resources']}
            />
        </div>
    )
}

function FairBar({ label, score, color }: { label: string; score: number; color: string }) {
    const pct = Math.max(0, Math.min(100, score))
    return (
        <div className="flex items-center gap-3">
            <span className="w-24 shrink-0 text-xs text-muted">{label}</span>
            <div className="flex-1 h-2 rounded-full bg-line overflow-hidden">
                <div className="h-full rounded-full transition-all"
                     style={{ width: `${pct}%`, backgroundColor: color }} />
            </div>
            <span className="w-8 text-right text-xs font-semibold text-ink tabular-nums">{score}</span>
        </div>
    )
}

// ─── main export ─────────────────────────────────────────────────────────────

export function Fair3rLiveDemoPanel({
    appId,
    externalUrl,
    datasetName = LIVE_DEMO_DATASET,
    label = 'FAIR3R live demo',
}: Fair3rLiveDemoPanelProps) {
    const { data, isLoading, isError, error } = useQuery({
        queryKey: ['fair3r-dataset-details', appId, datasetName],
        queryFn: () => fetchFair3rDataset(appId, datasetName),
        retry: false,
    })

    const fdf       = data?.fdf ?? null
    const dataset   = data?.dataset
    const title     = firstTitle(fdf, dataset?.title ?? null, datasetName)
    const resources = dataset?.resources ?? []
    const demoUrl   = `${(externalUrl ?? 'https://validation.fair3r.fr').replace(/\/$/, '')}/dataset/${datasetName}`

    // CRediT lookup: "givenName familyName" → roles[]
    // Source 1: Metadatapp-push format — separate contributors array with source="creator"
    const creditByKey: Record<string, string[]> = {}
    asArr<Rec>(fdf?.contributors).forEach(c => {
        if (str(c.source) !== 'creator') return
        const roles = asArr<Rec>(c.contributorRoles)
        if (!roles.length) return
        const key = [str(c.givenName), str(c.familyName)].filter(Boolean).join(' ') || str(c.name) || ''
        if (!key) return
        roles.forEach(r => {
            const role = str(r.contributorRole)
            if (!role) return
            if (!creditByKey[key]) creditByKey[key] = []
            if (!creditByKey[key].includes(role)) creditByKey[key].push(role)
        })
    })
    // Source 2: FAIR3R wizard format — contributorRoles embedded directly on each creator
    asArr<Rec>(fdf?.creators).forEach(c => {
        const key = [str(c.givenName), str(c.familyName)].filter(Boolean).join(' ') || fullName(c)
        if (!key) return
        asArr<Rec>(c.contributorRoles).forEach(r => {
            const role = str(r.contributorRole)
            if (!role) return
            if (!creditByKey[key]) creditByKey[key] = []
            if (!creditByKey[key].includes(role)) creditByKey[key].push(role)
        })
    })

    // Subjects grouped by scheme
    const byScheme = new Map<string, Rec[]>()
    asArr<Rec>(fdf?.subjects).forEach(s => {
        const scheme = str(s.subjectScheme) ?? '__none__'
        if (!byScheme.has(scheme)) byScheme.set(scheme, [])
        byScheme.get(scheme)!.push(s)
    })
    const schemed = (...schemes: string[]) => schemes.flatMap(k => byScheme.get(k) ?? [])

    const creators     = asArr<Rec>(fdf?.creators)
    const rights       = asRec(asArr<Rec>(fdf?.rightsList)[0])
    const fairScore    = asRec(fdf?.fair_score)
    const fairCriteria = asArr<Rec>(fdf?.fair_criteria)
    const descriptions = asArr<Rec>(fdf?.descriptions).filter(d => !!str(d.description))
    const relatedIds   = asArr<Rec>(fdf?.relatedIdentifiers).filter(r => !!str(r.relatedIdentifier))
    const type0        = asRec(asArr<Rec>(fdf?.types)[0])
    const resourceTypes = [str(type0.resourceTypeGeneral), str(type0.resourceType)].filter(Boolean)
    const resourceType = resourceTypes.length > 0 ? resourceTypes.join(' • ') : null
    const identifier   = str(fdf?.identifier)
    const publisher    = str(fdf?.publisher)
    const publisherId  = str(fdf?.publisherIdentifier)

    return (
        <section className="space-y-4 rounded-lg border border-line bg-surface-2 p-4">

            {/* Header */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="mb-2 flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className="border-brand/30 bg-brand/10 text-brand">
                            {label}
                        </Badge>
                        <Badge variant="outline" className="border-line text-muted">
                            {datasetName}
                        </Badge>
                    </div>
                    <h3 className="text-base font-semibold text-ink">{title}</h3>
                    {dataset?.notes && (
                        <p className="mt-1 line-clamp-2 text-xs text-muted">{dataset.notes}</p>
                    )}
                </div>
                <Button variant="outline" size="sm" asChild className="shrink-0">
                    <a href={demoUrl} target="_blank" rel="noopener noreferrer">
                        <ExternalLink className="mr-2 h-4 w-4" />
                        Open in FAIR3R
                    </a>
                </Button>
            </div>

            {isLoading && <p className="text-xs text-muted">Loading live metadata…</p>}

            {isError && (
                <div className="rounded-lg border border-error/30 bg-error/10 p-3 text-xs text-error">
                    <p>{error instanceof Error ? error.message : 'Unable to read FAIR3R live demo metadata.'}</p>
                    <p className="mt-2 text-error/75">
                        Private FAIR3R datasets only load here when the connected app token can read that dataset.
                    </p>
                </div>
            )}

            {/* CKAN fallback — shown when no FDF JSON was found (dataset not pushed via Metadatapp) */}
            {!isLoading && !isError && !fdf && data && (
                <CkanFallback dataset={dataset} ckan={data.ckan} />
            )}

            {!isLoading && !isError && fdf && (
                <div className="space-y-5">
                    {data && <CkanSummary dataset={dataset} ckan={data.ckan} />}

                    {/* 1 — Resource Description */}
                    <Section title="Resource Description" icon="📄" color="#1f6feb">
                        {identifier && (() => {
                            const href = isUrl(identifier) ? identifier : null
                            return <Field label="Identifier" value={identifier} href={href} />
                        })()}
                        {fdf.publicationYear !== undefined && (
                            <Field label="Publication Year" value={String(fdf.publicationYear)} />
                        )}
                        {resourceType && <Field label="Resource Type" value={resourceType} />}
                        {descriptions.map((d, i) => (
                            <Field key={i}
                                   label={str(d.descriptionType) ?? 'Description'}
                                   value={str(d.description)!} />
                        ))}
                    </Section>

                    {/* 2 — Publisher */}
                    {(publisher || publisherId) && (
                        <Section title="Publisher" icon="🏛️" color="#b7791f">
                            {publisher && <Field label="Publisher" value={publisher} />}
                            {publisherId && (
                                <Field label="Publisher Identifier" value={publisherId}
                                       href={isUrl(publisherId) ? publisherId : null} />
                            )}
                        </Section>
                    )}

                    {/* 3 — Creators */}
                    {creators.length > 0 && (
                        <Section title="Creators" icon="👥" color="#2563eb">
                            <div className="grid gap-2 sm:grid-cols-2">
                                {creators.map((c, i) => {
                                    const key = [str(c.givenName), str(c.familyName)].filter(Boolean).join(' ') || fullName(c)
                                    return <CreatorCard key={i} creator={c} roles={creditByKey[key] ?? []} />
                                })}
                            </div>
                        </Section>
                    )}

                    {/* 4 — Biological Model */}
                    {schemed('NCBITaxon').length > 0 && (
                        <Section title="Biological Model" icon="🐭" color="#2f855a">
                            {schemed('NCBITaxon').map((s, i) => {
                                const uri = str(s.valueURI)
                                return (
                                    <Field key={i} label="Species"
                                           value={subjectText(s, 'NCBITaxon')}
                                           href={isUrl(uri) ? uri : null} />
                                )
                            })}
                        </Section>
                    )}

                    {/* 5 — Genetic Background / Strain */}
                    {schemed('speciesBackground').length > 0 && (
                        <Section title="Genetic Background / Strain" icon="🧬" color="#718096">
                            {schemed('speciesBackground').map((s, i) => {
                                const uri = str(s.valueURI)
                                return (
                                    <Field key={i} label="Strain"
                                           value={subjectText(s, 'speciesBackground')}
                                           href={isUrl(uri) ? uri : null} />
                                )
                            })}
                        </Section>
                    )}

                    {/* 6 — Involved Genes */}
                    {schemed(...GENE_FIELDS.map(([k]) => k)).length > 0 && (
                        <Section title="Involved Genes" icon="🔬" color="#7c3aed">
                            {GENE_FIELDS.flatMap(([scheme, fieldLabel]) =>
                                schemed(scheme).map((s, i) => {
                                    const uri = str(s.valueURI)
                                    return (
                                        <Field key={`${scheme}-${i}`}
                                               label={fieldLabel}
                                               value={subjectText(s, scheme)}
                                               href={isUrl(uri) ? uri : null} />
                                    )
                                })
                            )}
                        </Section>
                    )}

                    {/* 7 — Molecules & Treatments */}
                    {schemed('ChEBI').length > 0 && (
                        <Section title="Molecules & Treatments" icon="💊" color="#dd6b20">
                            {schemed('ChEBI').map((s, i) => {
                                const uri = str(s.valueURI)
                                return (
                                    <Field key={i} label="Molecule"
                                           value={subjectText(s, 'ChEBI')}
                                           href={isUrl(uri) ? uri : null} />
                                )
                            })}
                        </Section>
                    )}

                    {/* 8 — Diet / Feeding Regiment */}
                    {schemed('EFO').length > 0 && (
                        <Section title="Diet / Feeding Regiment" icon="🥗" color="#38a169">
                            {schemed('EFO').map((s, i) => {
                                const uri = str(s.valueURI)
                                return (
                                    <Field key={i} label="Diet"
                                           value={subjectText(s, 'EFO')}
                                           href={isUrl(uri) ? uri : null} />
                                )
                            })}
                        </Section>
                    )}

                    {/* 9 — Disease Model */}
                    {schemed('DOID').length > 0 && (
                        <Section title="Disease Model" icon="🏥" color="#e53e3e">
                            {schemed('DOID').map((s, i) => {
                                const uri = str(s.valueURI)
                                return (
                                    <Field key={i} label="Disease"
                                           value={subjectText(s, 'DOID')}
                                           href={isUrl(uri) ? uri : null} />
                                )
                            })}
                        </Section>
                    )}

                    {/* 10 — Tissue / Organ of Interest */}
                    {schemed('UBERON').length > 0 && (
                        <Section title="Tissue / Organ of Interest" icon="🫀" color="#0e7490">
                            {schemed('UBERON').map((s, i) => {
                                const uri = str(s.valueURI)
                                return (
                                    <Field key={i} label="Tissue"
                                           value={subjectText(s, 'UBERON')}
                                           href={isUrl(uri) ? uri : null} />
                                )
                            })}
                        </Section>
                    )}

                    {Array.from(byScheme.entries()).some(([scheme, subjects]) => !KNOWN_SUBJECT_SCHEMES.has(scheme) && subjects.length > 0) && (
                        <Section title="Additional FAIR3R Subjects" icon="🏷️" color="#475569">
                            {Array.from(byScheme.entries())
                                .filter(([scheme, subjects]) => !KNOWN_SUBJECT_SCHEMES.has(scheme) && subjects.length > 0)
                                .flatMap(([scheme, subjects]) => subjects.map((subject, index) => {
                                    const uri = str(subject.valueURI)
                                    return (
                                        <Field
                                            key={`${scheme}-${index}`}
                                            label={scheme === '__none__' ? 'Subject' : scheme}
                                            value={subjectText(subject, scheme)}
                                            href={isUrl(uri) ? uri : null}
                                        />
                                    )
                                }))}
                        </Section>
                    )}

                    {/* 11 — Related Resources & References */}
                    {relatedIds.length > 0 && (
                        <Section title="Related Resources & References" icon="🔗" color="#6f42c1">
                            {relatedIds.map((r, i) => {
                                const id   = str(r.relatedIdentifier)!
                                const type = str(r.relatedIdentifierType)
                                const rel  = str(r.relationType)
                                const href = isUrl(id) ? id
                                    : type === 'DOI' ? `https://doi.org/${id}`
                                    : null
                                const fieldLabel = [rel, type ? `(${type})` : null].filter(Boolean).join(' ') || 'Related'
                                return <Field key={i} label={fieldLabel} value={id} href={href} />
                            })}
                        </Section>
                    )}

                    {/* 12 — Rights / License */}
                    {(str(rights.rights) || str(rights.rightsURI) || str(rights.rightsIdentifier)) && (
                        <Section title="Rights / License" icon="⚖️" color="#805ad5">
                            {str(rights.rights) && (
                                <Field label="License" value={str(rights.rights)!} />
                            )}
                            {str(rights.rightsIdentifier) && (
                                <Field label="Identifier" value={str(rights.rightsIdentifier)!} />
                            )}
                            {(() => {
                                const uri = str(rights.rightsURI)
                                return uri ? <Field label="License URL" value={uri} href={isUrl(uri) ? uri : null} /> : null
                            })()}
                        </Section>
                    )}

                    {/* 13 — FAIR Score */}
                    {fairScore.total !== undefined && (
                        <Section title="FAIR Score" icon="🎯" color="#553c9a">
                            <div className="space-y-2.5 mt-1">
                                <FairBar label="Findable"      score={Number(fairScore.findable      ?? 0)} color="#f6ad55" />
                                <FairBar label="Accessible"    score={Number(fairScore.accessible    ?? 0)} color="#63b3ed" />
                                <FairBar label="Interoperable" score={Number(fairScore.interoperable ?? 0)} color="#9f7aea" />
                                <FairBar label="Reusable"      score={Number(fairScore.reusable      ?? 0)} color="#68d391" />
                                <div className="flex items-center justify-between pt-3 border-t border-line">
                                    <span className="text-xs font-bold uppercase tracking-widest text-muted">Total Score</span>
                                    <span className="text-2xl font-display font-bold text-ink">
                                        {String(fairScore.total)}
                                        <span className="text-sm font-medium text-muted ml-1">/ 100</span>
                                    </span>
                                </div>
                                {fairCriteria.length > 0 && (
                                    <div className="flex flex-wrap gap-1 pt-1">
                                        {fairCriteria.map((c, i) => {
                                            const key = str(c.key) ?? String(i)
                                            const pass = str(c.value) === 'pass'
                                            return (
                                                <span key={key}
                                                      className={`text-[10px] px-1.5 py-0.5 rounded font-mono ${
                                                          pass
                                                              ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                              : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                                                      }`}>
                                                    {key}
                                                </span>
                                            )
                                        })}
                                    </div>
                                )}
                            </div>
                        </Section>
                    )}

                    {/* 14 — Files & Resources */}
                    {resources.length > 0 && (
                        <Section title={`Files & Resources (${resources.length})`} icon="📁" color="#2d3748">
                            <div>
                                {resources.map((res, i) => {
                                    const name = res.name ?? `Resource ${i + 1}`
                                    const fmt  = [res.format, res.mimetype].filter(Boolean).join(' / ')
                                    return (
                                        <div key={res.id ?? `r-${i}`}
                                             className="flex items-start justify-between gap-3 py-1.5 border-b border-line/40 last:border-0">
                                            <div className="min-w-0 flex-1">
                                                {res.url ? (
                                                    <a href={res.url} target="_blank" rel="noopener noreferrer"
                                                       className="inline-flex items-center gap-1 text-sm text-link hover:underline">
                                                        {name}<ExternalLink className="h-3 w-3 shrink-0" />
                                                    </a>
                                                ) : (
                                                    <span className="text-sm text-ink">{name}</span>
                                                )}
                                                {res.description && (
                                                    <p className="text-xs text-muted mt-0.5 line-clamp-1">{res.description}</p>
                                                )}
                                            </div>
                                            {fmt && (
                                                <span className="text-[10px] text-muted shrink-0 font-mono">{fmt}</span>
                                            )}
                                        </div>
                                    )
                                })}
                            </div>
                            {dataset?.metadataModified && (
                                <p className="text-xs text-muted mt-3">
                                    Last updated: {formatDate(dataset.metadataModified)}
                                </p>
                            )}
                        </Section>
                    )}

                    <RawMetadataSection
                        title="Raw FDF Metadata"
                        icon="🧾"
                        metadata={fdf}
                        skipKeys={[
                            'titles',
                            'creators',
                            'contributors',
                            'subjects',
                            'rightsList',
                            'fair_score',
                            'fair_criteria',
                            'descriptions',
                            'relatedIdentifiers',
                            'types',
                            'identifier',
                            'publisher',
                            'publisherIdentifier',
                            'publicationYear',
                        ]}
                    />
                    {data && (
                        <RawMetadataSection
                            title="Raw FAIR3R / CKAN Package"
                            icon="🧰"
                            metadata={data.ckan}
                            skipKeys={['resources']}
                        />
                    )}

                </div>
            )}
        </section>
    )
}
