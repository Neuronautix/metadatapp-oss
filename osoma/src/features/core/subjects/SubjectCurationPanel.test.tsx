import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { SubjectCurationPanel } from './SubjectCurationPanel.tsx'

const {
  getSubjectCurationSuggestionsMock,
  requestSubjectCurationSuggestionsMock,
  acceptSubjectCurationSuggestionMock,
  rejectSubjectCurationSuggestionMock,
  toastMock,
} = vi.hoisted(() => ({
  getSubjectCurationSuggestionsMock: vi.fn(),
  requestSubjectCurationSuggestionsMock: vi.fn(),
  acceptSubjectCurationSuggestionMock: vi.fn(),
  rejectSubjectCurationSuggestionMock: vi.fn(),
  toastMock: vi.fn(),
}))

vi.mock('./subject.api.ts', () => ({
  getSubjectCurationSuggestions: getSubjectCurationSuggestionsMock,
  requestSubjectCurationSuggestions: requestSubjectCurationSuggestionsMock,
  acceptSubjectCurationSuggestion: acceptSubjectCurationSuggestionMock,
  rejectSubjectCurationSuggestion: rejectSubjectCurationSuggestionMock,
}))

vi.mock('@/components/ui/toast.tsx', () => ({
  useToast: () => ({ toast: toastMock, dismiss: vi.fn(), toasts: [] }),
}))

const suggestions = [
  {
    id: 'sug-1',
    recordId: 'subject-1',
    resourceType: 'subject',
    resourceId: 'subject-1',
    taskType: 'value_normalization' as const,
    status: 'pending_review' as const,
    fieldName: 'name',
    currentValue: '  Mouse 001  ',
    proposedValue: 'Mouse 001',
    confidence: 0.98,
    reason: 'Trim whitespace.',
    warningCode: 'normalized_free_text',
    providerName: 'curate_gpt',
    createdAt: '2026-03-23T10:00:00Z',
    updatedAt: '2026-03-23T10:00:00Z',
  },
]

function renderPanel() {
  const client = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })

  return render(
    <QueryClientProvider client={client}>
      <SubjectCurationPanel subjectId="subject-1" />
    </QueryClientProvider>
  )
}

describe('SubjectCurationPanel', () => {
  beforeEach(() => {
    getSubjectCurationSuggestionsMock.mockReset()
    requestSubjectCurationSuggestionsMock.mockReset()
    acceptSubjectCurationSuggestionMock.mockReset()
    rejectSubjectCurationSuggestionMock.mockReset()
    toastMock.mockReset()
    getSubjectCurationSuggestionsMock.mockResolvedValue(suggestions)
    requestSubjectCurationSuggestionsMock.mockResolvedValue(suggestions)
    acceptSubjectCurationSuggestionMock.mockResolvedValue({ ...suggestions[0], status: 'accepted' })
    rejectSubjectCurationSuggestionMock.mockResolvedValue({ ...suggestions[0], status: 'rejected' })
  })

  it('renders existing suggestions and accepts a pending item', async () => {
    const user = userEvent.setup()
    renderPanel()

    expect(await screen.findByText('Trim whitespace.')).toBeInTheDocument()
    expect(screen.getByText('Provider: curate_gpt')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Accept' }))

    await waitFor(() => {
      expect(acceptSubjectCurationSuggestionMock).toHaveBeenCalled()
    })
    expect(acceptSubjectCurationSuggestionMock.mock.calls[0]?.[0]).toBe('sug-1')
    expect(toastMock).toHaveBeenCalledWith(expect.objectContaining({ title: 'Suggestion accepted' }))
  })

  it('requests new suggestions from the review panel', async () => {
    const user = userEvent.setup()
    renderPanel()

    expect(await screen.findByText('Trim whitespace.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Generate suggestions/i }))

    await waitFor(() => {
      expect(requestSubjectCurationSuggestionsMock).toHaveBeenCalledWith('subject-1')
    })
  })
})
