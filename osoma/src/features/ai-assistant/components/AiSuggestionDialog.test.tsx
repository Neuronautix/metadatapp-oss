import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AiSuggestionDialog } from './AiSuggestionDialog'

const { getAiStatusMock, requestAiSuggestionMock } = vi.hoisted(() => ({
  getAiStatusMock: vi.fn(),
  requestAiSuggestionMock: vi.fn(),
}))

vi.mock('../assistant.api', () => ({
  getAiStatus: getAiStatusMock,
  requestAiSuggestion: requestAiSuggestionMock,
}))

function renderDialog(onApply = vi.fn()) {
  const client = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })

  return render(
    <QueryClientProvider client={client}>
      <AiSuggestionDialog
        triggerLabel="AI filter draft"
        title="Draft filters"
        description="Review before applying."
        action="draft_query_filters"
        contextType="subjects_list"
        promptLabel="Describe subjects"
        buildContext={() => ({
          resourceType: 'subject',
          resourceId: 'list',
          filters: { search: '', species: '', consent: '' },
        })}
        onApply={onApply}
        applyLabel="Apply filters"
      />
    </QueryClientProvider>
  )
}

describe('AiSuggestionDialog', () => {
  beforeEach(() => {
    getAiStatusMock.mockReset()
    requestAiSuggestionMock.mockReset()

    getAiStatusMock.mockResolvedValue({
      status: {
        enabled: true,
        available: true,
        configured: true,
        realProvider: false,
        provider: 'mock',
        message: 'Using mock AI gateway.',
        capabilities: ['draft_query_filters'],
      },
      trace: { traceId: 'trace-status', action: 'status', route: '/ai/status', recordedAt: '2026-03-24T00:00:00Z' },
    })

    requestAiSuggestionMock.mockResolvedValue({
      action: 'draft_query_filters',
      summary: 'Drafted subject list filters from the free-text request.',
      status: {
        enabled: true,
        available: true,
        configured: true,
        realProvider: false,
        provider: 'mock',
        message: 'Using mock AI gateway.',
        capabilities: ['draft_query_filters'],
      },
      trace: { traceId: 'trace-suggest', action: 'suggest', route: '/ai/suggest', recordedAt: '2026-03-24T00:00:00Z' },
      suggestions: [
        {
          field: 'species',
          label: 'Species',
          preview: 'rat',
          reason: 'Detected a species hint from the prompt.',
          value: 'rat',
        },
      ],
      structuredOutput: {
        filters: {
          search: '',
          species: 'rat',
          consent: 'restricted',
        },
      },
      provenance: [],
      warnings: [],
      reviewRequired: true,
      canApplyDirectly: false,
    })
  })

  it('requests an AI suggestion and applies the reviewed draft', async () => {
    const user = userEvent.setup()
    const onApply = vi.fn()
    renderDialog(onApply)

    await user.click(screen.getByRole('button', { name: /AI filter draft/i }))

    expect(await screen.findByText('Provider status')).toBeInTheDocument()

    await user.type(screen.getByLabelText('Describe subjects'), 'restricted rat subjects')
    await user.click(screen.getByRole('button', { name: /Generate AI preview/i }))

    expect(await screen.findByText(/Drafted subject list filters/i)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Apply filters/i }))

    await waitFor(() => {
      expect(onApply).toHaveBeenCalledWith(expect.objectContaining({
        structuredOutput: expect.objectContaining({
          filters: expect.objectContaining({ species: 'rat' }),
        }),
      }))
    })
  })
})
