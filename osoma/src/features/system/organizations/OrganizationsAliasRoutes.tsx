import { Navigate, useParams } from 'react-router-dom'
import { Feature } from '@/feature-flags/Feature.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'

export function OrganizationsAliasListRoute() {
  return (
    <Feature
      flag="metadatapp-feat.enabled"
      fallback={<EmptyState title="Organizations disabled" description="Enable the Metadatapp feature to manage laboratories." />}
    >
      <Navigate to="/metadata/laboratories" replace />
    </Feature>
  )
}

export function OrganizationsAliasDetailRoute() {
  const { organizationId } = useParams()
  if (!organizationId) {
    return <OrganizationsAliasListRoute />
  }
  return (
    <Feature
      flag="metadatapp-feat.enabled"
      fallback={<EmptyState title="Organizations disabled" description="Enable the Metadatapp feature to manage laboratories." />}
    >
      <Navigate to={`/metadata/laboratories/${organizationId}`} replace />
    </Feature>
  )
}

export function OrganizationsAliasEditRoute() {
  const { organizationId } = useParams()
  if (!organizationId) {
    return <OrganizationsAliasListRoute />
  }
  return (
    <Feature
      flag="metadatapp-feat.enabled"
      fallback={<EmptyState title="Organizations disabled" description="Enable the Metadatapp feature to manage laboratories." />}
    >
      <Navigate to={`/metadata/laboratories/${organizationId}/edit`} replace />
    </Feature>
  )
}
