export const vcgMatchModes = ['Strict Match', 'Weighted Similarity', 'Sensitivity Set'] as const
export type VcgMatchMode = (typeof vcgMatchModes)[number]

export const welfareSeverityLevels = ['info', 'warning', 'critical'] as const
export type WelfareSeverity = (typeof welfareSeverityLevels)[number]

export const dvcEventTypes = [
  'move',
  'bedding_change',
  'enrichment_added',
  'cleaning',
  'feed_change',
] as const
export type DvcEventType = (typeof dvcEventTypes)[number]

export const aiSegmentLabels = ['sleep', 'active', 'anomaly', 'circadian'] as const
export type AiSegmentLabel = (typeof aiSegmentLabels)[number]
