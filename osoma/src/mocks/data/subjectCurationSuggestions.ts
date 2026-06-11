import type { SubjectCurationSuggestion } from '@/features/core/subjects/subject.api.ts'
import { subjects } from './subjects.ts'

const now = () => new Date().toISOString()

const seededSubject = subjects.find((subject) => subject.id === 'SUB-014')

export const subjectCurationSuggestions: Record<string, SubjectCurationSuggestion[]> = seededSubject
  ? {
      [seededSubject.id]: [
        {
          id: 'cur-suggestion-1',
          recordId: seededSubject.id,
          resourceType: 'subject',
          resourceId: seededSubject.id,
          taskType: 'value_normalization',
          status: 'pending_review',
          fieldName: 'name',
          currentValue: '  Participant 014  ',
          proposedValue: 'Participant 014',
          confidence: 0.98,
          reason: 'Trim redundant whitespace in the subject label.',
          warningCode: 'normalized_free_text',
          providerName: 'curate_gpt',
          createdAt: now(),
          updatedAt: now(),
        },
        {
          id: 'cur-suggestion-2',
          recordId: seededSubject.id,
          resourceType: 'subject',
          resourceId: seededSubject.id,
          taskType: 'ontology_candidate',
          status: 'pending_review',
          fieldName: 'species',
          currentValue: seededSubject.species,
          proposedValue: seededSubject.species,
          ontologyId: 'NCBITaxon:10090',
          confidence: 0.91,
          reason: 'Mouse subject mapped to the expected species ontology.',
          warningCode: 'ontology_candidate',
          providerName: 'curate_gpt',
          providerMetadata: {
            candidateLabel: 'Mus musculus',
          },
          createdAt: now(),
          updatedAt: now(),
        },
      ],
    }
  : {}
