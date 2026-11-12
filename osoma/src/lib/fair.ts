import type { FAIRCriteria, FAIRCriterion, FAIRScore, Dataset, Study } from '@/domain/resources.ts'

type InvestigationLike = {
  id: string
  name: string
  description?: string
  species?: string
  startDate?: string
  startAt?: string
  endDate?: string
  endAt?: string | null
  assignedOperators?: Array<{ id: string }>
  tags?: string[]
  createdAt?: string
  updatedAt?: string
}

type StudyLike = Omit<Study, 'fairScore'>
type DatasetLike = Omit<Dataset, 'fairScore'>

type FairAssessableResource = StudyLike | DatasetLike | InvestigationLike

const scoreCriteria = (criteria: FAIRCriterion[]): number => {
  if (criteria.length === 0) {
    return 0
  }

  return Math.round((criteria.filter((criterion) => criterion.pass).length / criteria.length) * 100)
}

export function computeFairScore(criteria: FAIRCriteria): FAIRScore {
  const findable = scoreCriteria(criteria.findable)
  const accessible = scoreCriteria(criteria.accessible)
  const interoperable = scoreCriteria(criteria.interoperable)
  const reusable = scoreCriteria(criteria.reusable)

  return {
    total: Math.round((findable + accessible + interoperable + reusable) / 4),
    findable,
    accessible,
    interoperable,
    reusable,
  }
}

const isStudy = (resource: FairAssessableResource): resource is StudyLike =>
  'runCount' in resource && 'sampleCount' in resource && 'assayId' in resource

const isDataset = (resource: FairAssessableResource): resource is DatasetLike =>
  'format' in resource && 'sizeGb' in resource && 'investigationId' in resource

/**
 * Derive individual FAIR criteria pass/fail checks from a resource record.
 * Each criterion maps to measurable signals available in the current domain object.
 */
export function computeFairCriteria(resource: FairAssessableResource): FAIRCriteria {
  if (isStudy(resource)) {
    const hasIdentifiers = resource.identifiers.length > 0
    const hasRichMetadata =
      Boolean(resource.name) && Boolean(resource.assayName) && Boolean(resource.investigationName)
    const hasReferences = Boolean(resource.investigationId) && Boolean(resource.assayId)
    const hasProvenance = resource.provenance.length > 0
    const isCompleted = resource.status === 'completed' || resource.isSigned === true
    const hasData = resource.sampleCount > 0 || resource.runCount > 0
    const hasPassedQc = resource.qcStatus === 'pass'

    return {
      findable: [
        {
          id: 'F1',
          label: 'Globally unique and persistent identifier',
          description: '(Meta)data are assigned a globally unique and persistent identifier.',
          pass: hasIdentifiers,
        },
        {
          id: 'F2',
          label: 'Rich metadata',
          description: 'Data are described with rich metadata.',
          pass: hasRichMetadata && hasData,
        },
        {
          id: 'F3',
          label: 'Identifier referenced in metadata',
          description: 'Metadata clearly and explicitly include the identifier of the data they describe.',
          pass: hasIdentifiers,
        },
        {
          id: 'F4',
          label: 'Registered in a searchable resource',
          description: '(Meta)data are registered or indexed in a searchable resource.',
          pass: hasProvenance || hasData,
        },
      ],
      accessible: [
        {
          id: 'A1',
          label: 'Data retrievable by identifier',
          description:
            '(Meta)data are retrievable by their identifier using a standardised communications protocol.',
          pass: hasIdentifiers,
        },
        {
          id: 'A1.1',
          label: 'Open, free, and universally implementable protocol',
          description: 'The protocol is open, free, and universally implementable.',
          pass: true,
        },
        {
          id: 'A1.2',
          label: 'Protocol supports authentication',
          description: 'The protocol allows for an authentication and authorisation procedure, where necessary.',
          pass: true,
        },
        {
          id: 'A2',
          label: 'Metadata accessible even when data is unavailable',
          description: 'Metadata are accessible, even when the data are no longer available.',
          pass: isCompleted,
        },
      ],
      interoperable: [
        {
          id: 'I1',
          label: 'Formal knowledge representation language',
          description:
            '(Meta)data use a formal, accessible, shared, and broadly applicable language for knowledge representation.',
          pass: true,
        },
        {
          id: 'I2',
          label: 'FAIR-compliant vocabularies',
          description: '(Meta)data use vocabularies that follow FAIR principles.',
          pass: Boolean(resource.assayId),
        },
        {
          id: 'I3',
          label: 'Qualified references to other metadata',
          description: '(Meta)data include qualified references to other (meta)data.',
          pass: hasReferences,
        },
      ],
      reusable: [
        {
          id: 'R1',
          label: 'Richly described with accurate attributes',
          description:
            '(Meta)data are richly described with a plurality of accurate and relevant attributes.',
          pass: hasRichMetadata && hasData,
        },
        {
          id: 'R1.1',
          label: 'Clear data usage license',
          description: '(Meta)data are released with a clear and accessible data usage license.',
          pass: Boolean(resource.isSigned),
        },
        {
          id: 'R1.2',
          label: 'Detailed provenance',
          description: '(Meta)data are associated with detailed provenance.',
          pass: hasProvenance,
        },
        {
          id: 'R1.3',
          label: 'Domain-relevant community standards',
          description: '(Meta)data meet domain-relevant community standards.',
          pass: hasPassedQc,
        },
      ],
    }
  }

  if (isDataset(resource)) {
    const hasPersistentIdentifier = Boolean(resource.doi || resource.id)
    const hasRichMetadata =
      Boolean(resource.title) && Boolean(resource.format) && Boolean(resource.investigationName)
    const hasQualifiedReferences = Boolean(resource.investigationId)
    const hasLifecycleMetadata = Boolean(resource.createdAt) && Boolean(resource.updatedAt)
    const isPublished = resource.status !== 'draft'
    const usesKnownFormat = resource.format.toLowerCase() !== 'unknown'
    const hasDataContent = resource.sizeGb > 0

    return {
      findable: [
        {
          id: 'F1',
          label: 'Globally unique and persistent identifier',
          description: '(Meta)data are assigned a globally unique and persistent identifier.',
          pass: hasPersistentIdentifier,
        },
        {
          id: 'F2',
          label: 'Rich metadata',
          description: 'Data are described with rich metadata.',
          pass: hasRichMetadata && hasDataContent,
        },
        {
          id: 'F3',
          label: 'Identifier referenced in metadata',
          description: 'Metadata clearly and explicitly include the identifier of the data they describe.',
          pass: hasPersistentIdentifier,
        },
        {
          id: 'F4',
          label: 'Registered in a searchable resource',
          description: '(Meta)data are registered or indexed in a searchable resource.',
          pass: hasQualifiedReferences || isPublished,
        },
      ],
      accessible: [
        {
          id: 'A1',
          label: 'Data retrievable by identifier',
          description:
            '(Meta)data are retrievable by their identifier using a standardised communications protocol.',
          pass: hasPersistentIdentifier,
        },
        {
          id: 'A1.1',
          label: 'Open, free, and universally implementable protocol',
          description: 'The protocol is open, free, and universally implementable.',
          pass: true,
        },
        {
          id: 'A1.2',
          label: 'Protocol supports authentication',
          description: 'The protocol allows for an authentication and authorisation procedure, where necessary.',
          pass: true,
        },
        {
          id: 'A2',
          label: 'Metadata accessible even when data is unavailable',
          description: 'Metadata are accessible, even when the data are no longer available.',
          pass: hasLifecycleMetadata,
        },
      ],
      interoperable: [
        {
          id: 'I1',
          label: 'Formal knowledge representation language',
          description:
            '(Meta)data use a formal, accessible, shared, and broadly applicable language for knowledge representation.',
          pass: true,
        },
        {
          id: 'I2',
          label: 'FAIR-compliant vocabularies',
          description: '(Meta)data use vocabularies that follow FAIR principles.',
          pass: usesKnownFormat,
        },
        {
          id: 'I3',
          label: 'Qualified references to other metadata',
          description: '(Meta)data include qualified references to other (meta)data.',
          pass: hasQualifiedReferences,
        },
      ],
      reusable: [
        {
          id: 'R1',
          label: 'Richly described with accurate attributes',
          description:
            '(Meta)data are richly described with a plurality of accurate and relevant attributes.',
          pass: hasRichMetadata && hasDataContent,
        },
        {
          id: 'R1.1',
          label: 'Clear data usage license',
          description: '(Meta)data are released with a clear and accessible data usage license.',
          pass: isPublished,
        },
        {
          id: 'R1.2',
          label: 'Detailed provenance',
          description: '(Meta)data are associated with detailed provenance.',
          pass: hasLifecycleMetadata,
        },
        {
          id: 'R1.3',
          label: 'Domain-relevant community standards',
          description: '(Meta)data meet domain-relevant community standards.',
          pass: usesKnownFormat,
        },
      ],
    }
  }

  const assignedOperators = resource.assignedOperators ?? []
  const hasPersistentIdentifier = Boolean(resource.id)
  const hasRichMetadata =
    Boolean(resource.name) && Boolean(resource.description) && Boolean(resource.species)
  const hasQualifiedReferences = assignedOperators.length > 0 || Boolean(resource.tags?.length)
  const hasLifecycleMetadata =
    Boolean(resource.startDate ?? resource.startAt ?? resource.createdAt) &&
    Boolean(resource.updatedAt ?? resource.endAt)
  const hasEndDate = Boolean(resource.endDate ?? resource.endAt)
  const hasGovernanceSignals = assignedOperators.length > 0

  return {
    findable: [
      {
        id: 'F1',
        label: 'Globally unique and persistent identifier',
        description: '(Meta)data are assigned a globally unique and persistent identifier.',
        pass: hasPersistentIdentifier,
      },
      {
        id: 'F2',
        label: 'Rich metadata',
        description: 'Data are described with rich metadata.',
        pass: hasRichMetadata && hasGovernanceSignals,
      },
      {
        id: 'F3',
        label: 'Identifier referenced in metadata',
        description: 'Metadata clearly and explicitly include the identifier of the data they describe.',
        pass: hasPersistentIdentifier,
      },
      {
        id: 'F4',
        label: 'Registered in a searchable resource',
        description: '(Meta)data are registered or indexed in a searchable resource.',
        pass: hasQualifiedReferences || hasLifecycleMetadata,
      },
    ],
    accessible: [
      {
        id: 'A1',
        label: 'Data retrievable by identifier',
        description:
          '(Meta)data are retrievable by their identifier using a standardised communications protocol.',
        pass: hasPersistentIdentifier,
      },
      {
        id: 'A1.1',
        label: 'Open, free, and universally implementable protocol',
        description: 'The protocol is open, free, and universally implementable.',
        pass: true,
      },
      {
        id: 'A1.2',
        label: 'Protocol supports authentication',
        description: 'The protocol allows for an authentication and authorisation procedure, where necessary.',
        pass: true,
      },
      {
        id: 'A2',
        label: 'Metadata accessible even when data is unavailable',
        description: 'Metadata are accessible, even when the data are no longer available.',
        pass: hasLifecycleMetadata || hasEndDate,
      },
    ],
    interoperable: [
      {
        id: 'I1',
        label: 'Formal knowledge representation language',
        description:
          '(Meta)data use a formal, accessible, shared, and broadly applicable language for knowledge representation.',
        pass: true,
      },
      {
        id: 'I2',
        label: 'FAIR-compliant vocabularies',
        description: '(Meta)data use vocabularies that follow FAIR principles.',
        pass: Boolean(resource.species),
      },
      {
        id: 'I3',
        label: 'Qualified references to other metadata',
        description: '(Meta)data include qualified references to other (meta)data.',
        pass: hasQualifiedReferences,
      },
    ],
    reusable: [
      {
        id: 'R1',
        label: 'Richly described with accurate attributes',
        description:
          '(Meta)data are richly described with a plurality of accurate and relevant attributes.',
        pass: hasRichMetadata && hasGovernanceSignals,
      },
      {
        id: 'R1.1',
        label: 'Clear data usage license',
        description: '(Meta)data are released with a clear and accessible data usage license.',
        pass: hasGovernanceSignals,
      },
      {
        id: 'R1.2',
        label: 'Detailed provenance',
        description: '(Meta)data are associated with detailed provenance.',
        pass: hasLifecycleMetadata,
      },
      {
        id: 'R1.3',
        label: 'Domain-relevant community standards',
        description: '(Meta)data meet domain-relevant community standards.',
        pass: Boolean(resource.species),
      },
    ],
  }
}

/** Thresholds that define FAIR score quality tiers (shared across all pages). */
export const FAIR_SCORE_EXCELLENT_THRESHOLD = 85
export const FAIR_SCORE_GOOD_THRESHOLD = 70

/**
 * Maps a numeric FAIR total score to a Badge variant.
 *
 * - ≥ 85 → 'success'  (green)
 * - ≥ 70 → 'warning'  (amber)
 * - < 70 → 'outline'  (neutral)
 */
export function fairVariantFromScore(total: number): 'success' | 'warning' | 'outline' {
  if (total >= FAIR_SCORE_EXCELLENT_THRESHOLD) return 'success'
  if (total >= FAIR_SCORE_GOOD_THRESHOLD) return 'warning'
  return 'outline'
}
