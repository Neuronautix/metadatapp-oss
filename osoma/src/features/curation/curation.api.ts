import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { apiFetch } from '@/lib/api.ts'
import type { MappingDefinition } from '@/domain/curation/curation.types'

export type MappingTemplate = {
  id: string
  name: string
  mappings: MappingDefinition[]
  createdAt: string
}

export type ImportSessionSummary = {
  id: string
  status: string
  unresolved_row_count: number
  ambiguous_row_count: number
  next_allowed_actions: string[]
  schema_proposals: Array<{
    id: string
    column_id: string
    header: string
    target_field: string
    status: string
  }>
  timestamps: {
    profiled_at?: string
    schema_generated_at?: string
    patched_built_at?: string
    applied_at?: string
  }
}

export function useCreateImportSession() {
  return useMutation({
    mutationFn: async (file: File) => {
      const formData = new FormData()
      formData.append('file', file)
      
      // using apiFetch to call the backend upload endpoint
      return apiFetch<{ id: string, status: string }>('/curation/sessions', {
        method: 'POST',
        body: formData,
      })
    }
  })
}

export function useGetSessionSummary(sessionId?: string | null) {
  return useQuery({
    queryKey: ['curation-session-summary', sessionId],
    queryFn: async () => {
      return apiFetch<ImportSessionSummary>(`/curation/sessions/${sessionId}/summary`)
    },
    enabled: !!sessionId,
    refetchInterval: (query) => {
      const status = query.state.data?.status
      // poll if it is currently processing or just profiled (waiting for proposals)
      return status === 'uploaded' || status === 'profiling' || status === 'profiled' ? 2000 : false
    }
  })
}

export function useProfileSession() {
  return useMutation({
    mutationFn: async (sessionId: string) => {
      return apiFetch(`/curation/sessions/${sessionId}/profile`, { method: 'POST' })
    }
  })
}

export function useGenerateProposals() {
  return useMutation({
    mutationFn: async (sessionId: string) => {
      return apiFetch(`/curation/sessions/${sessionId}/generate`, { method: 'POST' })
    }
  })
}

export function useResolveIdentities() {
  return useMutation({
    mutationFn: async (sessionId: string) => {
      return apiFetch(`/curation/sessions/${sessionId}/resolution`, { method: 'POST' })
    }
  })
}

export function useGetResolutionSummary(sessionId?: string | null) {
  return useQuery({
    queryKey: ['curation-resolution-summary', sessionId],
    queryFn: async () => {
      return apiFetch<any>(`/curation/sessions/${sessionId}/resolution`)
    },
    enabled: !!sessionId
  })
}

export function useBuildPatches() {
  return useMutation({
    mutationFn: async (sessionId: string) => {
      return apiFetch(`/curation/sessions/${sessionId}/patch-build`, { method: 'POST' })
    }
  })
}

export function useGetPatchSummary(sessionId?: string | null) {
  return useQuery({
    queryKey: ['curation-patch-summary', sessionId],
    queryFn: async () => {
      return apiFetch<any>(`/curation/sessions/${sessionId}/patch-build`)
    },
    enabled: !!sessionId
  })
}

export function useApplyPatches() {
  return useMutation({
    mutationFn: async (sessionId: string) => {
      return apiFetch(`/curation/sessions/${sessionId}/apply`, { method: 'POST' })
    }
  })
}

export function useListMappingTemplates() {
  return useQuery({
    queryKey: ['mapping-templates'],
    queryFn: async () => {
      const result = await apiFetch<{ 'hydra:member': MappingTemplate[] } | MappingTemplate[]>(
        '/curation/mapping-templates',
      )
      return Array.isArray(result) ? result : (result['hydra:member'] ?? [])
    },
  })
}

export function useSaveMappingTemplate() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (payload: { name: string; mappings: MappingDefinition[] }) => {
      return apiFetch<MappingTemplate>('/curation/mapping-templates', {
        method: 'POST',
        body: JSON.stringify(payload),
      })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['mapping-templates'] })
    },
  })
}

export function useDeleteMappingTemplate() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async (id: string) => {
      return apiFetch(`/curation/mapping-templates/${id}`, { method: 'DELETE' })
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['mapping-templates'] })
    },
  })
}
