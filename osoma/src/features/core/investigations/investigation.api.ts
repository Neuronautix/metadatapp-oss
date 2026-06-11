import { apiFetch, apiFetchHydraMapped } from '@/lib/api.ts'
import type {
  Investigation,
  InvestigationCreateInput,
  InvestigationDashboard,
  InvestigationExportStatus,
  InvestigationUpdateInput,
  InvestigationActivityResponse,
  KeyboardShortcutsResponse,
} from './investigation.types.ts'
import type { InvestigationOperator } from './investigation.types.ts'
import type { MetaInvestigation, MetaUser } from '@/metadatapp/types.ts'
import { mapMetaInvestigation, mapMetaUserToInvestigationOperator, mapInvestigationCreateInput, mapInvestigationUpdateInput } from '@/metadatapp/adapters.ts'

export function getInvestigations() {
  return apiFetchHydraMapped<MetaInvestigation, Investigation>('/projects', mapMetaInvestigation)
}

export function getInvestigationActivity() {
  return apiFetch<InvestigationActivityResponse>('/project-workspace/activity')
}

export function getInvestigation(investigationId: string) {
  return apiFetch<MetaInvestigation>(`/projects/${investigationId}`).then(mapMetaInvestigation)
}

export function createInvestigation(payload: InvestigationCreateInput) {
  return apiFetch<MetaInvestigation>('/projects', {
    method: 'POST',
    body: JSON.stringify(mapInvestigationCreateInput(payload)),
  }).then(mapMetaInvestigation)
}

export function updateInvestigation(investigationId: string, payload: InvestigationUpdateInput) {
  return apiFetch<MetaInvestigation>(`/projects/${investigationId}`, {
    method: 'PATCH',
    body: JSON.stringify(mapInvestigationUpdateInput(payload)),
  }).then(mapMetaInvestigation)
}

export function updateInvestigationTags(investigationId: string, tags: string[]) {
  return apiFetch<Investigation>(`/projects/${investigationId}/tags`, {
    method: 'PATCH',
    body: JSON.stringify({ tags }),
  })
}

export function getInvestigationOperators() {
  return apiFetchHydraMapped<MetaUser, InvestigationOperator>('/users', mapMetaUserToInvestigationOperator).then(
    (response) => ({ data: response.data })
  )
}

export function assignAnimalsToInvestigation(investigationId: string, animalIds: string[]) {
  return apiFetch<Investigation>(`/projects/${investigationId}/animals`, {
    method: 'POST',
    body: JSON.stringify({ animalIds }),
  })
}

export function getInvestigationDashboard(investigationId: string) {
  return apiFetch<InvestigationDashboard>(`/projects/${investigationId}/dashboard`)
}

export function exportInvestigationAnimalsCsv(investigationId: string) {
  return apiFetch<{ exportedAt: string; rowCount: number; csv: string }>(
    `/projects/${investigationId}/animals/export`,
    {
      method: 'POST',
    }
  )
}

export function getInvestigationExportStatus(investigationId: string) {
  return apiFetch<InvestigationExportStatus>(`/projects/${investigationId}/animals/export`)
}

export function exportInvestigationRoCrate(investigationId: string) {
  return apiFetch<Blob>(`/v1/export/ro-crate/${investigationId}`, {
    responseType: 'blob',
  })
}

export function exportInvestigationFair2JsonLd(investigationId: string) {
  return apiFetch<Blob>(`/investigations/${investigationId}/fair2.json`, {
    responseType: 'blob',
  })
}

export function exportInvestigationFairReportPdf(investigationId: string) {
  return apiFetch<Blob>(`/investigations/${investigationId}/fair-report.pdf`, {
    responseType: 'blob',
  })
}

export function exportInvestigationArriveReportPdf(investigationId: string) {
  return apiFetch<Blob>(`/investigations/${investigationId}/arrive-report.pdf`, {
    responseType: 'blob',
  })
}

export function getKeyboardShortcuts() {
  return apiFetch<KeyboardShortcutsResponse>('/keyboard-shortcuts')
}
