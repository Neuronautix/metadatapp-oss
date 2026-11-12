import { EmptyState } from '@/components/EmptyState.tsx'

export function CagesFeatureDisabledPage() {
  return (
    <EmptyState
      title="Cages disabled"
      description="Enable the cages.enabled feature flag to access cage listings and details."
    />
  )
}
