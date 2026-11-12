import type { ReferenceResult, StandardizationResult, ReferenceCluster } from './reference-hub.api.ts'

/** Human-facing labels for the reference `type` field. */
export const TYPE_LABELS: Record<string, string> = {
    strain: 'Strain',
    measure: 'Measure',
    procedure: 'Procedure',
    pipeline: 'Pipeline',
    cde_element: 'CDE',
    protocol: 'Protocol',
    template: 'Template',
    schema: 'Schema',
    guideline: 'Guideline',
    ontology_class: 'Ontology term',
}

/** sessionStorage key holding the references handed to the Compare page. */
const COMPARE_ITEMS_KEY = 'osoma.referenceHub.compareItems'

/** Stash the selection so the Compare page (a separate route) can read it. */
export function writeCompareItems(items: ReferenceResult[]): void {
    if (typeof window === 'undefined') return
    try {
        window.sessionStorage.setItem(COMPARE_ITEMS_KEY, JSON.stringify(items))
    } catch {
        // ignore quota / serialization issues
    }
}

/** Read the references to compare (empty when navigated to directly / after a refresh). */
export function readCompareItems(): ReferenceResult[] {
    if (typeof window === 'undefined') return []
    try {
        const raw = window.sessionStorage.getItem(COMPARE_ITEMS_KEY)
        const parsed = raw ? JSON.parse(raw) : null
        return Array.isArray(parsed) ? (parsed as ReferenceResult[]) : []
    } catch {
        return []
    }
}

/**
 * Augment each selected result with its cluster's canonical term + cluster id +
 * evidence so the backend persists the standardization on import.
 */
export function applyStandardization(items: ReferenceResult[], result: StandardizationResult): ReferenceResult[] {
    const byRefId = new Map<string, ReferenceCluster>()
    for (const cluster of result.clusters) {
        for (const refId of cluster.memberRefIds) byRefId.set(refId, cluster)
    }
    return items.map((item) => {
        const cluster = byRefId.get(item.id)
        if (!cluster) return item
        return {
            ...item,
            canonicalTerm: cluster.canonicalTerm,
            clusterId: cluster.clusterId,
            standardizationEvidence: cluster.evidence,
        }
    })
}
