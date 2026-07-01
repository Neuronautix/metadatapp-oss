import { apiFetch, apiFetchHydraCollection } from '@/lib/api.ts'

/**
 * A single normalized reference result returned by the federated Reference Hub
 * search (`GET /reference-search`). Mirrors the backend `ReferenceResult` DTO.
 */
export type ReferenceResult = {
    id: string
    /** AppCode of the producing connected app, e.g. "jax_phenome". */
    source: string
    sourceName: string
    /** strain | measure | procedure | pipeline | cde_element | protocol */
    type: string
    title: string
    subtitle?: string | null
    description?: string | null
    externalUrl?: string | null
    identifiers?: Record<string, unknown>
    raw?: Record<string, unknown>
}

/**
 * Fan a query out across the selected connected apps. Empty `appCodes` searches
 * all of the account's active apps.
 */
export async function searchReferences(query: string, appCodes: string[], limit = 20): Promise<ReferenceResult[]> {
    const params = new URLSearchParams({ q: query, limit: String(limit) })
    if (appCodes.length > 0) {
        params.set('apps', appCodes.join(','))
    }
    const collection = await apiFetchHydraCollection<ReferenceResult>(`/reference-search?${params.toString()}`)
    return collection.data
}

/** A reference result imported into the account's library (mirrors the backend entity). */
export type ImportedReference = ReferenceResult & {
    importedAt: string
    /** AI standardization enrichment, present once the reference has been standardized + imported. */
    canonicalTermIri?: string | null
    canonicalTermLabel?: string | null
    canonicalTermOntology?: string | null
    standardizationConfidence?: number | null
    clusterId?: string | null
    standardizedAt?: string | null
}

/** Import the selected results into the account's library (idempotent per referenceId). */
export async function importReferences(items: ReferenceResult[]): Promise<{ count: number }> {
    return apiFetch<{ count: number }>('/imported_references/import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items }),
    })
}

/** The account's imported reference library. */
export async function listImportedReferences(): Promise<ImportedReference[]> {
    const collection = await apiFetchHydraCollection<ImportedReference>('/imported_references')
    return collection.data
}

export async function deleteImportedReference(id: string): Promise<void> {
    await apiFetch<void>(`/imported_references/${id}`, { method: 'DELETE', responseType: 'none' })
}

/** A canonical ontology term a cluster of equivalent results is mapped to. */
export type CanonicalTerm = {
    iri: string
    label: string
    /** Ontology short name, e.g. "vt", "mp", "efo". */
    ontology: string
    confidence: number
}

/** One metadata field reconciled across the sources in a cluster (mirrors the backend DTO). */
export type HarmonizedField = {
    field: string
    /** The chosen, reconciled value. */
    value: string
    /** True when the sources genuinely disagreed on this field. */
    conflict: boolean
    /** Every distinct per-source value, so nothing is hidden. */
    sourceValues: Array<{ source: string; value: string }>
    /** Optional model rationale for the chosen value. */
    rationale?: string | null
}

/** A group of cross-source equivalents mapped to one canonical term (mirrors the backend DTO). */
export type ReferenceCluster = {
    clusterId: string
    canonicalTerm: CanonicalTerm | null
    /** Federated ids of the results in this cluster. */
    memberRefIds: string[]
    evidence: string[]
    proposedRecord: Record<string, unknown>
    confidence: number
    /** Per-field reconciliation across the sources. */
    harmonizedFields: HarmonizedField[]
}

/** Outcome of "AI Compare & Link" over a set of selected results. */
export type StandardizationResult = {
    /** False when the model is unavailable — `clusters` is then a heuristic grouping. */
    aiAvailable: boolean
    clusters: ReferenceCluster[]
    warnings: string[]
    provenance: Array<Record<string, unknown>>
    usage: Record<string, unknown>
}

/**
 * Cluster the selected results across sources and propose one canonical, importable
 * record per concept (`POST /references/standardize`). Degrades to a deterministic
 * grouping when AI is off (`aiAvailable: false`).
 */
export async function standardizeReferences(items: ReferenceResult[]): Promise<StandardizationResult> {
    return apiFetch<StandardizationResult>('/references/standardize', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items }),
    })
}

/** Promote a standardized library reference into a reusable Common Data Element. */
export async function promoteReferenceToCde(importedReferenceId: string): Promise<{ id: string; label: string }> {
    return apiFetch<{ id: string; label: string }>(`/imported_references/${importedReferenceId}/promote-to-cde`, {
        method: 'POST',
    })
}

/** A library item ranked by semantic similarity to a query/reference (mirrors the backend DTO). */
export type SimilarReference = {
    id: string
    source: string
    sourceName: string
    type: string
    title: string
    description?: string | null
    externalUrl?: string | null
    /** Cosine similarity in [0, 1] (higher = more similar). */
    score: number
    identifiers?: Record<string, unknown>
}

/**
 * Find library items semantically similar to an imported reference
 * (`GET /similar-references?referenceId=…`). Ranked by cosine similarity over the
 * deterministic embedding; always available regardless of the semantic flag.
 */
export async function findSimilarReferences(referenceId: string, limit = 5): Promise<SimilarReference[]> {
    const params = new URLSearchParams({ referenceId, limit: String(limit) })
    const collection = await apiFetchHydraCollection<SimilarReference>(`/similar-references?${params.toString()}`)
    return collection.data
}

/** A search result ranked by relevance to the query (mirrors the backend). */
export type BestMatch = {
    id: string
    /** Similarity in [0, 1]. */
    score: number
    reason: string
}

/**
 * Rank the current search results by relevance to the query (the "best match"
 * hint). Deterministic embedding similarity — no AI provider required.
 */
export async function bestMatchReferences(query: string, items: ReferenceResult[], limit = 3): Promise<BestMatch[]> {
    const res = await apiFetch<{ matches: BestMatch[] }>('/references/best-match', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ query, items, limit }),
    })
    return res.matches
}

// ---------------------------------------------------------------------------
// Schema comparison (AI-assisted field harmonization across templates/schemas)
// ---------------------------------------------------------------------------

export type FieldGroupMember = {
    sourceRef: string
    mappingType: string
    label: string
    evidence: string
}

export type SchemaFieldConflict = {
    field: string
    values: string[]
    recommendation?: string | null
}

export type SchemaFieldGroup = {
    groupId: string
    canonicalLabel: string
    canonicalDatatype: string | null
    canonicalUnit: string | null
    confidence: number
    members: FieldGroupMember[]
    conflicts: SchemaFieldConflict[]
}

export type SchemaComparisonResult = {
    fieldGroups: SchemaFieldGroup[]
    aiAvailable: boolean
    warnings: string[]
    usage: Record<string, unknown>
}

/**
 * Compare selected schema/template/guideline results field-by-field and produce
 * a harmonized mapping (`POST /references/compare-schemas`).
 */
export async function compareSchemas(items: ReferenceResult[]): Promise<SchemaComparisonResult> {
    return apiFetch<SchemaComparisonResult>('/references/compare-schemas', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ items }),
    })
}
