import { http, HttpResponse } from 'msw'
import { subjects } from '@/mocks/data/subjects.ts'
import { subjectCurationSuggestions } from '@/mocks/data/subjectCurationSuggestions.ts'

const now = () => new Date().toISOString()

const buildSuggestions = (subjectId: string) => {
  const subject = subjects.find((record) => record.id === subjectId)
  if (!subject) return []

  return [
    {
      id: `cur-${subjectId}-name`,
      recordId: subjectId,
      resourceType: 'subject',
      resourceId: subjectId,
      taskType: 'value_normalization' as const,
      status: 'pending_review' as const,
      fieldName: 'name',
      currentValue: ` ${subject.label} `,
      proposedValue: subject.label,
      confidence: 0.97,
      reason: 'Trim redundant whitespace in the subject label.',
      warningCode: 'normalized_free_text',
      providerName: 'curate_gpt',
      createdAt: now(),
      updatedAt: now(),
    },
    {
      id: `cur-${subjectId}-species`,
      recordId: subjectId,
      resourceType: 'subject',
      resourceId: subjectId,
      taskType: 'ontology_candidate' as const,
      status: 'pending_review' as const,
      fieldName: 'species',
      currentValue: subject.species,
      proposedValue: subject.species,
      ontologyId: subject.species === 'mouse' ? 'NCBITaxon:10090' : undefined,
      confidence: 0.9,
      reason: 'Attach the best matching species ontology candidate for review.',
      warningCode: 'ontology_candidate',
      providerName: 'curate_gpt',
      providerMetadata: {
        candidateLabel: subject.species === 'mouse' ? 'Mus musculus' : subject.species,
      },
      createdAt: now(),
      updatedAt: now(),
    },
  ]
}

export const subjectCurationHandlers = [
  http.get('/api/curation/suggestions/:subjectId', async ({ params }) => {
    const recordId = String(params.subjectId)
    return HttpResponse.json({
      recordId,
      suggestions: subjectCurationSuggestions[recordId] ?? [],
    })
  }),
  http.post('/api/curation/suggest/:subjectId', async ({ params }) => {
    const recordId = String(params.subjectId)
    const suggestions = buildSuggestions(recordId)
    subjectCurationSuggestions[recordId] = suggestions
    return HttpResponse.json({
      recordId,
      suggestions,
    })
  }),
  http.post('/api/curation/suggestions/:suggestionId/accept', async ({ params }) => {
    const suggestionId = String(params.suggestionId)
    for (const [subjectId, suggestions] of Object.entries(subjectCurationSuggestions)) {
      const suggestion = suggestions.find((item) => item.id === suggestionId)
      if (!suggestion) continue

      suggestion.status = 'accepted'
      suggestion.reviewedAt = now()
      suggestion.reviewedBy = 'Mock Curator'
      suggestion.updatedAt = now()

      const subject = subjects.find((item) => item.id === subjectId)
      if (subject && suggestion.fieldName === 'name' && typeof suggestion.proposedValue === 'string') {
        subject.label = suggestion.proposedValue
        subject.updatedAt = now()
      }

      return HttpResponse.json({ suggestion })
    }

    return HttpResponse.json({ message: 'Suggestion not found.' }, { status: 404 })
  }),
  http.post('/api/curation/suggestions/:suggestionId/reject', async ({ params }) => {
    const suggestionId = String(params.suggestionId)
    for (const suggestions of Object.values(subjectCurationSuggestions)) {
      const suggestion = suggestions.find((item) => item.id === suggestionId)
      if (!suggestion) continue

      suggestion.status = 'rejected'
      suggestion.reviewedAt = now()
      suggestion.reviewedBy = 'Mock Curator'
      suggestion.updatedAt = now()

      return HttpResponse.json({ suggestion })
    }

    return HttpResponse.json({ message: 'Suggestion not found.' }, { status: 404 })
  }),
]
