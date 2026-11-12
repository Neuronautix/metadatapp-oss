import type { FeatureFlagKey } from './featureFlag.types.ts'
import { useFeatureFlagContext } from './FeatureFlagProvider.tsx'

export function useFeatureFlag(key: FeatureFlagKey) {
  const { isEnabled } = useFeatureFlagContext()
  return isEnabled(key)
}
