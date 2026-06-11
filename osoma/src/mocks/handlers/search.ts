import { http, HttpResponse } from 'msw'
import { investigations } from '@/mocks/data/investigations.ts'
import { studies } from '@/mocks/data/studies.ts'
import { samples } from '@/mocks/data/samples.ts'
import { subjects } from '@/mocks/data/subjects.ts'
import { assays } from '@/mocks/data/assays.ts'
import { datasets } from '@/mocks/data/datasets.ts'
import { users } from '@/mocks/data/users.ts'
import { organizations } from '@/mocks/data/organizations.ts'
import { matches, withLatency } from './utils.ts'
import type { SearchResult } from '@/features/system/search/search.types.ts'
import type { InvestigationOperator } from '@/features/core/investigations/investigation.types.ts'

const buildResults = (query: string) => {
  const results: SearchResult[] = []

  investigations.forEach((investigation) => {
    if (matches(investigation.name, query) || matches(investigation.description ?? '', query)) {
      const operatorNames = investigation.assignedOperators.map((op: InvestigationOperator) => op.name).join(', ')
      results.push({
        type: 'investigation',
        id: investigation.id,
        title: investigation.name,
        subtitle: operatorNames ? `${investigation.species} · ${operatorNames}` : investigation.species,
      })
    }
  })

  studies.forEach((study) => {
    if (
      matches(study.name, query) ||
      matches(study.id, query) ||
      matches(study.investigationName, query) ||
      matches(study.assayName, query)
    ) {
      results.push({
        type: 'study',
        id: study.id,
        title: study.name,
        subtitle: `${study.id} · ${study.investigationName}`,
        status: study.status,
      })
    }
  })

  samples.forEach((sample) => {
    if (
      matches(sample.label, query) ||
      matches(sample.id, query) ||
      matches(sample.subjectLabel, query) ||
      matches(sample.investigationName, query)
    ) {
      results.push({
        type: 'sample',
        id: sample.id,
        title: sample.label,
        subtitle: `${sample.id} · ${sample.subjectLabel}`,
        status: sample.status,
      })
    }
  })

  subjects.forEach((subject) => {
    if (matches(subject.label, query) || matches(subject.id, query) || matches(subject.species, query)) {
      results.push({
        type: 'subject',
        id: subject.id,
        title: subject.label,
        subtitle: `${subject.id} · ${subject.species}`,
        status: subject.status,
      })
    }
  })

  assays.forEach((assay) => {
    if (matches(assay.name, query) || matches(assay.id, query) || matches(assay.method, query)) {
      results.push({
        type: 'assay',
        id: assay.id,
        title: assay.name,
        subtitle: `${assay.id} · ${assay.method}`,
        status: assay.reviewStatus,
      })
    }
  })

  datasets.forEach((dataset) => {
    if (
      matches(dataset.title, query) ||
      matches(dataset.id, query) ||
      matches(dataset.investigationName, query)
    ) {
      results.push({
        type: 'dataset',
        id: dataset.id,
        title: dataset.title,
        subtitle: `${dataset.id} · ${dataset.investigationName}`,
        status: dataset.status,
      })
    }
  })

  users.forEach((user) => {
    if (matches(user.name, query) || matches(user.id, query) || matches(user.lab, query)) {
      results.push({
        type: 'user',
        id: user.id,
        title: user.name,
        subtitle: `${user.id} · ${user.role}`,
        status: user.status,
      })
    }
  })

  organizations.forEach((organization) => {
    if (
      matches(organization.name, query) ||
      matches(organization.id, query) ||
      matches(organization.location, query)
    ) {
      results.push({
        type: 'organization',
        id: organization.id,
        title: organization.name,
        subtitle: `${organization.id} · ${organization.location}`,
        status: organization.type,
      })
    }
  })

  return results.slice(0, 32)
}

export const searchHandlers = [
  http.get('/api/search', async ({ request }) => {
    await withLatency(180, 720)
    const url = new URL(request.url)
    const query = url.searchParams.get('query') ?? ''
    const error = url.searchParams.get('error')

    if (error === 'true' || (query.length > 1 && Math.random() < 0.08)) {
      return HttpResponse.json({ message: 'Search service degraded.' }, { status: 500 })
    }

    if (query.trim().length < 2) {
      return HttpResponse.json({ query, results: [] })
    }

    const results = buildResults(query)
    return HttpResponse.json({ query, results })
  }),
]
