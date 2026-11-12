import type { User } from '@/domain/resources.ts'

export type AccessArea =
  | 'investigations'
  | 'metadata'
  | 'subjects'
  | 'operations'
  | 'connected-apps'
  | 'administration'

export const accessAreaOptions: Array<{
  value: AccessArea
  label: string
  description: string
}> = [
  { value: 'investigations', label: 'Investigations', description: 'Plans, studies, and assay coordination.' },
  { value: 'metadata', label: 'Metadata admin', description: 'Schema-driven metadata administration and curation.' },
  { value: 'subjects', label: 'Subjects', description: 'Subject registry, housing, and sample-linked records.' },
  { value: 'operations', label: 'Operations', description: 'Facilities, sensors, alarms, and operations modules.' },
  { value: 'connected-apps', label: 'Connected apps', description: 'Third-party integrations and app directory access.' },
  { value: 'administration', label: 'Administration', description: 'Users, organizations, audits, and workspace controls.' },
]

const accessLevelDefaults: Record<User['accessLevel'], AccessArea[]> = {
  viewer: ['investigations', 'subjects'],
  editor: ['investigations', 'metadata', 'subjects', 'connected-apps'],
  admin: ['investigations', 'metadata', 'subjects', 'operations', 'connected-apps', 'administration'],
}

export const getDefaultAccessAreas = (accessLevel: User['accessLevel']): AccessArea[] => accessLevelDefaults[accessLevel]

export const normalizeAccessAreas = (areas: AccessArea[] | undefined, accessLevel: User['accessLevel']): AccessArea[] => {
  const source = areas?.length ? areas : getDefaultAccessAreas(accessLevel)
  return Array.from(new Set(source))
}

export const summarizeAccessAreas = (areas: AccessArea[] | undefined, accessLevel: User['accessLevel']): string => {
  const normalized = normalizeAccessAreas(areas, accessLevel)
  if (normalized.length === accessAreaOptions.length) {
    return 'Full workspace'
  }

  if (normalized.length <= 2) {
    return normalized
      .map((value) => accessAreaOptions.find((option) => option.value === value)?.label ?? value)
      .join(', ')
  }

  return `${normalized.length} areas`
}
