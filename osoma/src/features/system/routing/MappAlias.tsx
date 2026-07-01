import { Navigate, useNavigate } from 'react-router-dom'
import { Feature } from '@/feature-flags/Feature.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'

type MappAliasProps = {
  to: string
  title: string
  description: string
}

export function MappAlias({ to, title, description }: MappAliasProps) {
  const navigate = useNavigate()

  return (
    <Feature
      flag="metadatapp-feat.enabled"
      fallback={
        <EmptyState
          title={title}
          description={description}
          actionLabel="Back to dashboard"
          onAction={() => navigate('/')}
        />
      }
    >
      <Navigate to={to} replace />
    </Feature>
  )
}

