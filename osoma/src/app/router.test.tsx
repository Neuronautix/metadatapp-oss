import { describe, expect, it } from 'vitest'
import { router } from './router.tsx'
import { AssaysPage } from '@/features/core/assays/AssaysPage.tsx'
import { AssayEditPage } from '@/features/core/assays/AssayEditPage.tsx'
import { AssayViewPage } from '@/features/core/assays/AssayViewPage.tsx'
import { StudiesPage } from '@/features/core/studies/StudiesPage.tsx'
import { StudyViewPage } from '@/features/core/studies/StudyViewPage.tsx'
import { StudyEditPage } from '@/features/core/studies/StudyEditPage.tsx'
import { SamplesPage } from '@/features/core/samples/SamplesPage.tsx'
import { SampleViewPage } from '@/features/core/samples/SampleViewPage.tsx'
import { SampleEditPage } from '@/features/core/samples/SampleEditPage.tsx'
import { SubjectsPage } from '@/features/core/subjects/SubjectsPage.tsx'
import { SubjectViewPage } from '@/features/core/subjects/SubjectViewPage.tsx'
import { SubjectEditPage } from '@/features/core/subjects/SubjectEditPage.tsx'
import { InvestigationsPage } from '@/features/core/investigations/InvestigationsPage.tsx'
import { NewInvestigationPage } from '@/features/core/investigations/NewInvestigationPage.tsx'
import { InvestigationViewPage } from '@/features/core/investigations/InvestigationViewPage.tsx'
import { InvestigationEditPage } from '@/features/core/investigations/InvestigationEditPage.tsx'
import { InvestigationAssignAnimalsPage } from '@/features/core/investigations/InvestigationAssignAnimalsPage.tsx'
import { AnimalDetailPage } from '@/features/core/animals/AnimalDetailPage.tsx'
import {
  ProjectsAliasListRoute,
  ProjectsAliasNewRoute,
  ProjectsAliasDetailRoute,
  ProjectsAliasEditRoute,
  ProjectsAliasAnimalsRoute,
  ProjectsAliasAnimalDetailRoute,
} from '@/features/core/investigations/ProjectsAliasRoutes.tsx'
import { DatasetsAliasRoute } from '@/features/system/routing/LegacyMetaAliases.tsx'
import { Feature } from '@/feature-flags/Feature.tsx'

const findRoute = (path: string, routes = router.routes): (typeof router.routes)[number] | undefined => {
  for (const route of routes) {
    if (route.path === path) return route
    if (route.children) {
      const match = findRoute(path, route.children)
      if (match) return match
    }
  }

  return undefined
}

describe('app router', () => {
  it('routes investigations, studies, and samples to the dedicated frontend screens', () => {
    expect(findRoute('investigations')?.element?.type).toBe(InvestigationsPage)
    expect(findRoute('investigations/new')?.element?.type).toBe(NewInvestigationPage)
    expect(findRoute('investigations/:investigationId')?.element?.type).toBe(InvestigationViewPage)
    expect(findRoute('investigations/:investigationId/edit')?.element?.type).toBe(InvestigationEditPage)
    expect(findRoute('investigations/:investigationId/animals')?.element?.type).toBe(InvestigationAssignAnimalsPage)
    expect(findRoute('investigations/:investigationId/animals/:animalId')?.element?.type).toBe(AnimalDetailPage)

    expect(findRoute('projects')?.element?.type).toBe(ProjectsAliasListRoute)
    expect(findRoute('projects/new')?.element?.type).toBe(ProjectsAliasNewRoute)
    expect(findRoute('projects/:projectId')?.element?.type).toBe(ProjectsAliasDetailRoute)
    expect(findRoute('projects/:projectId/edit')?.element?.type).toBe(ProjectsAliasEditRoute)
    expect(findRoute('projects/:projectId/animals')?.element?.type).toBe(ProjectsAliasAnimalsRoute)
    expect(findRoute('projects/:projectId/animals/:animalId')?.element?.type).toBe(ProjectsAliasAnimalDetailRoute)

    expect(findRoute('studies')?.element?.type).toBe(StudiesPage)
    expect(findRoute('studies/new')?.element?.props.to).toBe('/metadata/experiments/new')
    expect(findRoute('studies/:studyId')?.element?.type).toBe(StudyViewPage)
    expect(findRoute('studies/:studyId/edit')?.element?.type).toBe(StudyEditPage)

    expect(findRoute('samples/new')?.element?.props.to).toBe('/metadata/weight_measurements/new')
    expect(findRoute('samples')?.element?.type).toBe(SamplesPage)
    expect(findRoute('samples/:sampleId')?.element?.type).toBe(SampleViewPage)
    expect(findRoute('samples/:sampleId/edit')?.element?.type).toBe(SampleEditPage)

    expect(findRoute('subjects')?.element?.type).toBe(SubjectsPage)
    expect(findRoute('subjects/new')?.element?.props.to).toBe('/metadata/subjects/new')
    expect(findRoute('subjects/:subjectId')?.element?.type).toBe(SubjectViewPage)
    expect(findRoute('subjects/:subjectId/edit')?.element?.type).toBe(SubjectEditPage)
  })

  it('routes assays pages to the assay feature screens', () => {
    expect(findRoute('assays')?.element?.type).toBe(AssaysPage)
    expect(findRoute('assays/new')?.element?.props.to).toBe('/metadata/procedures/new')
    expect(findRoute('assays/:assayId')?.element?.type).toBe(AssayViewPage)
    expect(findRoute('assays/:assayId/edit')?.element?.type).toBe(AssayEditPage)
  })

  it('routes dedicated create aliases to generic MAPP create pages where needed', () => {
    expect(findRoute('cages/new')?.element?.type).toBe(Feature)
    expect(findRoute('cages/new')?.element?.props.flag).toBe('cages.enabled')
    expect(findRoute('cages/new')?.element?.props.children.props.to).toBe('/metadata/cages/new')
    expect(findRoute('datasets/new')).toBeUndefined()
  })

  it('redirects the legacy AI provider settings route to the current providers page', () => {
    expect(findRoute('AI-providers')).toBeDefined()
    expect(findRoute('AI-providers')?.caseSensitive).toBe(true)
    expect(findRoute('ai-providers')?.element?.props.to).toBe('/settings/AI-providers')
    expect(findRoute('ai-providers')?.element?.props.replace).toBe(true)
    expect(findRoute('ai-providers')?.caseSensitive).toBe(true)
    expect(findRoute('ai-provider')?.element?.props.to).toBe('/settings/AI-providers')
    expect(findRoute('ai-provider')?.element?.props.replace).toBe(true)
    expect(findRoute('ai-provider')?.caseSensitive).toBe(true)
    expect(findRoute('ai-proveider')?.element?.props.to).toBe('/settings/AI-providers')
    expect(findRoute('ai-proveider')?.element?.props.replace).toBe(true)
    expect(findRoute('ai-proveider')?.caseSensitive).toBe(true)
  })

  it('routes datasets pages to the MAPP alias because no dataset API resource exists', () => {
    expect(findRoute('datasets')?.element?.type).toBe(DatasetsAliasRoute)
    expect(findRoute('datasets/:datasetId')?.element?.type).toBe(DatasetsAliasRoute)
    expect(findRoute('datasets/:datasetId/edit')?.element?.type).toBe(DatasetsAliasRoute)
  })
})
