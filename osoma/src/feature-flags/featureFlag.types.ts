export type FeatureFlagCategory =
  | 'atmp'
  | 'dvc'
  | 'studyal'
  | 'system'
  | 'feature'
  | 'metadatapp-feat'
  | 'non-metadatapp-feat'
  | 'zefix'
export type FeatureFlagEnvironment = 'demo' | 'prod' | 'local'

export type FeatureFlagKey =
  | 'metadatapp-feat.enabled'
  | 'non-metadatapp-feat.enabled'
  | 'atmp.enabled'
  | 'dvc.observatory.enabled'
  | 'dvc.namVcg.enabled'
  | 'dvc.aiInsights.enabled'
  | 'dvc.studyal.welfare.enabled'
  | 'dvc.export.enabled'
  | 'dvc.circadian.enabled'
  | 'namVcg.meanTrace.enabled'
  | 'cages.enabled'
  | 'studyal.agentic'
  | 'studyal.explainability'
  | 'studyal.virtualControl'
  | 'studyal.narrative'
  | 'studyal.timeTravelLab'
  | 'feature.observatory'
  | 'feature.auditLogs'
  | 'feature.future'
  | 'feature.connectedApps'
  | 'feature.connectedApps.deepDive'
  | 'feature.referenceHub'
  | 'feature.referenceHub.standardize'
  | 'feature.referenceHub.semantic'
  | 'feature.guidelinesReporting'
  | 'feature.aiAssistant.agentic'
  | 'timeline.enabled'
  | 'import.enabled'
  | 'dataEntry.enabled'
  | 'feature.curateGpt.enabled'
  | 'feature.curationWorkflow.enabled'
  | 'zefix.enabled'

export interface FeatureFlag {
  key: FeatureFlagKey
  label: string
  description: string
  category: FeatureFlagCategory
  environments?: FeatureFlagEnvironment[]
  enabled: boolean
  updatedAt: string
  updatedBy: string
}

export type FeatureFlagRecord = Record<FeatureFlagKey, FeatureFlag>
