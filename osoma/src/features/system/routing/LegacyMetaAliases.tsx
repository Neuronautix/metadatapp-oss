import { useParams } from 'react-router-dom'
import { MappAlias } from './MappAlias.tsx'

export function StudiesAliasListRoute() {
  return <MappAlias to="/metadata/experiments" title="Studies moved" description="This screen now uses the MAPP API studies resource." />
}

export function StudiesAliasDetailRoute() {
  const { studyId } = useParams()
  if (!studyId) return <StudiesAliasListRoute />
  return <MappAlias to={`/metadata/experiments/${studyId}`} title="Studies moved" description="This screen now uses the MAPP API studies resource." />
}

export function StudiesAliasEditRoute() {
  const { studyId } = useParams()
  if (!studyId) return <StudiesAliasListRoute />
  return <MappAlias to={`/metadata/experiments/${studyId}/edit`} title="Studies moved" description="This screen now uses the MAPP API studies resource." />
}

export function SubjectsAliasListRoute() {
  return <MappAlias to="/metadata/subjects" title="Subjects moved" description="This screen now uses the MAPP API subjects resource." />
}

export function SubjectsAliasDetailRoute() {
  const { subjectId } = useParams()
  if (!subjectId) return <SubjectsAliasListRoute />
  return <MappAlias to={`/metadata/subjects/${subjectId}`} title="Subjects moved" description="This screen now uses the MAPP API subjects resource." />
}

export function SubjectsAliasEditRoute() {
  const { subjectId } = useParams()
  if (!subjectId) return <SubjectsAliasListRoute />
  return <MappAlias to={`/metadata/subjects/${subjectId}/edit`} title="Subjects moved" description="This screen now uses the MAPP API subjects resource." />
}

export function SamplesAliasListRoute() {
  return <MappAlias to="/metadata/subjects" title="Samples moved" description="This screen now uses the MAPP API subjects resource." />
}

export function SamplesAliasDetailRoute() {
  const { sampleId } = useParams()
  if (!sampleId) return <SamplesAliasListRoute />
  return <MappAlias to={`/metadata/subjects/${sampleId}`} title="Samples moved" description="This screen now uses the MAPP API subjects resource." />
}

export function SamplesAliasEditRoute() {
  const { sampleId } = useParams()
  if (!sampleId) return <SamplesAliasListRoute />
  return <MappAlias to={`/metadata/subjects/${sampleId}/edit`} title="Samples moved" description="This screen now uses the MAPP API subjects resource." />
}

export function InvestigationsAliasListRoute() {
  return <MappAlias to="/metadata/experiments" title="Investigations moved" description="This screen now uses the MAPP API investigations resource." />
}

export function InvestigationsAliasNewRoute() {
  return <MappAlias to="/metadata/experiments/new" title="Investigations moved" description="This screen now uses the MAPP API investigations resource." />
}

export function InvestigationsAliasDetailRoute() {
  const { investigationId } = useParams()
  if (!investigationId) return <InvestigationsAliasListRoute />
  return <MappAlias to={`/metadata/experiments/${investigationId}`} title="Investigations moved" description="This screen now uses the MAPP API investigations resource." />
}

export function InvestigationsAliasEditRoute() {
  const { investigationId } = useParams()
  if (!investigationId) return <InvestigationsAliasListRoute />
  return <MappAlias to={`/metadata/experiments/${investigationId}/edit`} title="Investigations moved" description="This screen now uses the MAPP API investigations resource." />
}

export function InvestigationsAliasAnimalDetailRoute() {
  const { animalId } = useParams()
  if (!animalId) {
    return <MappAlias to="/metadata/subjects" title="Animals moved" description="This screen now uses the MAPP API subjects resource." />
  }
  return <MappAlias to={`/metadata/subjects/${animalId}`} title="Animals moved" description="This screen now uses the MAPP API subjects resource." />
}

export function AssaysAliasRoute() {
  return <MappAlias to="/metadata" title="Assays unavailable" description="This screen is not backed by a MAPP API resource. Use the MAPP API index instead." />
}

export function DatasetsAliasRoute() {
  return <MappAlias to="/metadata" title="Datasets unavailable" description="This screen is not backed by a MAPP API resource. Use the MAPP API index instead." />
}
