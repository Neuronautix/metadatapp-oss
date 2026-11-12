import { Navigate, useParams } from 'react-router-dom'

export function ProjectsAliasListRoute() {
  return <Navigate to="/investigations" replace />
}

export function ProjectsAliasNewRoute() {
  return <Navigate to="/investigations/new" replace />
}

export function ProjectsAliasDetailRoute() {
  const { projectId } = useParams()
  if (!projectId) return <ProjectsAliasListRoute />
  return <Navigate to={`/investigations/${projectId}`} replace />
}

export function ProjectsAliasEditRoute() {
  const { projectId } = useParams()
  if (!projectId) return <ProjectsAliasListRoute />
  return <Navigate to={`/investigations/${projectId}/edit`} replace />
}

export function ProjectsAliasAnimalsRoute() {
  const { projectId } = useParams()
  if (!projectId) return <ProjectsAliasListRoute />
  return <Navigate to={`/investigations/${projectId}/animals`} replace />
}

export function ProjectsAliasAnimalDetailRoute() {
  const { projectId, animalId } = useParams()
  if (!projectId) return <ProjectsAliasListRoute />
  if (!animalId) return <ProjectsAliasAnimalsRoute />
  return <Navigate to={`/investigations/${projectId}/animals/${animalId}`} replace />
}
