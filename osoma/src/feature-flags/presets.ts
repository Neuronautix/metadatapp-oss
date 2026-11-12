import { featureFlagRegistry } from './flags.ts'
import type { FeatureFlagKey } from './featureFlag.types.ts'

export type FeatureFlagPresetKey = 'professional' | 'demo' | 'studyal' | 'zefix' | 'tecniplast' | 'god'

export interface FeatureFlagPreset {
  key: FeatureFlagPresetKey
  label: string
  description: string
  tone: 'professional' | 'scientific' | 'demo'
  overrides: Record<FeatureFlagKey, boolean>
}

const FLAG_KEYS = Object.keys(featureFlagRegistry) as FeatureFlagKey[]

const buildOverrides = (valueMap: Partial<Record<FeatureFlagKey, boolean>>) => {
  return FLAG_KEYS.reduce((acc, key) => {
    acc[key] = valueMap[key] ?? featureFlagRegistry[key].enabled
    return acc
  }, {} as Record<FeatureFlagKey, boolean>)
}

const buildAllEnabled = () => {
  return FLAG_KEYS.reduce((acc, key) => {
    acc[key] = true
    return acc
  }, {} as Record<FeatureFlagKey, boolean>)
}

export const featureFlagPresets: Record<FeatureFlagPresetKey, FeatureFlagPreset> = {
  professional: {
    key: 'professional',
    label: 'Professional / Executive',
    description: 'Conservative tone with studyal features off and compliance only features on demand.',
    tone: 'professional',
    overrides: buildOverrides({
      'metadatapp-feat.enabled': true,
      'non-metadatapp-feat.enabled': true,
      'atmp.enabled': true,
      'dvc.observatory.enabled': true,
      'dvc.namVcg.enabled': false,
      'dvc.aiInsights.enabled': false,
      'dvc.studyal.welfare.enabled': false,
      'dvc.export.enabled': false,
      'dvc.circadian.enabled': true,
      'namVcg.meanTrace.enabled': true,
      'cages.enabled': true,
      'studyal.agentic': false,
      'studyal.explainability': false,
      'studyal.virtualControl': false,
      'studyal.narrative': false,
      'studyal.timeTravelLab': false,
      'feature.observatory': false,
      'feature.auditLogs': false,
      'feature.future': false,
      'zefix.enabled': true,
    }),
  },
  demo: {
    key: 'demo',
    label: 'Demo / Wow',
    description: 'Everything on display so the product can dazzle.',
    tone: 'demo',
    overrides: buildOverrides({
      'metadatapp-feat.enabled': true,
      'non-metadatapp-feat.enabled': true,
      'atmp.enabled': true,
      'dvc.observatory.enabled': true,
      'dvc.namVcg.enabled': true,
      'dvc.aiInsights.enabled': true,
      'dvc.studyal.welfare.enabled': true,
      'dvc.export.enabled': true,
      'dvc.circadian.enabled': true,
      'namVcg.meanTrace.enabled': true,
      'cages.enabled': true,
      'studyal.agentic': true,
      'studyal.explainability': true,
      'studyal.virtualControl': true,
      'studyal.narrative': true,
      'studyal.timeTravelLab': true,
      'feature.observatory': true,
      'feature.auditLogs': true,
      'feature.future': true,
      'zefix.enabled': true,
    }),
  },
  studyal: {
    key: 'studyal',
    label: 'Studyal / Internal',
    description: 'All flags on for internal iteration and future ATMP studies.',
    tone: 'scientific',
    overrides: buildAllEnabled(),
  },
  zefix: {
    key: 'zefix',
    label: 'Zefix / Zebrafish',
    description: 'Zebrafish-focused profile: lines, batches, rooms, systems, housing management, and zefix dashboards only.',
    tone: 'scientific',
    overrides: buildOverrides({
      'metadatapp-feat.enabled': false,
      'non-metadatapp-feat.enabled': false,
      'zefix.enabled': true,
      'atmp.enabled': false,
      'dvc.observatory.enabled': false,
      'dvc.namVcg.enabled': false,
      'dvc.aiInsights.enabled': false,
      'dvc.studyal.welfare.enabled': false,
      'dvc.export.enabled': false,
      'dvc.circadian.enabled': false,
      'namVcg.meanTrace.enabled': false,
      'cages.enabled': false,
      'studyal.agentic': false,
      'studyal.explainability': false,
      'studyal.virtualControl': false,
      'studyal.narrative': false,
      'studyal.timeTravelLab': false,
      'feature.observatory': false,
      'feature.auditLogs': false,
      'feature.future': false,
      'feature.connectedApps': false,
      'feature.connectedApps.deepDive': false,
      'import.enabled': false,
      'dataEntry.enabled': false,
      'feature.curationWorkflow.enabled': false,
      'feature.curateGpt.enabled': false,
    }),
  },
  tecniplast: {
    key: 'tecniplast',
    label: 'Tecniplast / Mice',
    description: 'Mice and rodent facility profile: subjects, cages, TP operations, DVC sensors, and analytics.',
    tone: 'professional',
    overrides: buildOverrides({
      'metadatapp-feat.enabled': true,
      'non-metadatapp-feat.enabled': true,
      'zefix.enabled': false,
      'atmp.enabled': false,
      'dvc.observatory.enabled': false,
      'dvc.namVcg.enabled': false,
      'dvc.aiInsights.enabled': false,
      'dvc.studyal.welfare.enabled': false,
      'dvc.export.enabled': false,
      'dvc.circadian.enabled': true,
      'namVcg.meanTrace.enabled': false,
      'cages.enabled': true,
      'studyal.agentic': false,
      'studyal.explainability': false,
      'studyal.virtualControl': false,
      'studyal.narrative': false,
      'studyal.timeTravelLab': false,
      'feature.observatory': false,
      'feature.auditLogs': false,
      'feature.future': false,
      'feature.connectedApps': true,
      'feature.connectedApps.deepDive': true,
      'import.enabled': true,
      'dataEntry.enabled': false,
      'feature.curationWorkflow.enabled': false,
      'feature.curateGpt.enabled': false,
    }),
  },
  god: {
    key: 'god',
    label: 'God / All Access',
    description: 'Full access to every feature and surface. No restrictions.',
    tone: 'demo',
    overrides: buildAllEnabled(),
  },
}
