import type { FeatureFlagKey } from './featureFlag.types.ts'
import { useFeatureFlag } from './useFeatureFlag.ts'
import type { ReactNode } from 'react'

interface FeatureProps {
  flag: FeatureFlagKey
  children: ReactNode
  fallback?: ReactNode
}

export function Feature({ flag, children, fallback }: FeatureProps) {
  const enabled = useFeatureFlag(flag)
  if (!enabled) {
    return <>{fallback ?? null}</>
  }
  return <>{children}</>
}
