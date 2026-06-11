import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { AssistantChat } from './AssistantChat'

const { getAiStatusMock, sendAiChatMock, callMcpToolMock } = vi.hoisted(() => ({
  getAiStatusMock: vi.fn(),
  sendAiChatMock: vi.fn(),
  callMcpToolMock: vi.fn(),
}))

vi.mock('../assistant.api', () => ({
  getAiStatus: getAiStatusMock,
  sendAiChat: sendAiChatMock,
}))

vi.mock('../api/mcpApi', () => ({
  callMcpTool: callMcpToolMock,
}))

function renderChat() {
  const client = new QueryClient({
    defaultOptions: {
      queries: { retry: false },
      mutations: { retry: false },
    },
  })

  return render(
    <QueryClientProvider client={client}>
      <AssistantChat />
    </QueryClientProvider>
  )
}

describe('AssistantChat', () => {
  beforeEach(() => {
    getAiStatusMock.mockReset()
    sendAiChatMock.mockReset()
    callMcpToolMock.mockReset()

    getAiStatusMock.mockResolvedValue({
      status: {
        enabled: true,
        available: true,
        configured: true,
        realProvider: false,
        provider: 'mock',
        message: 'Using mock AI gateway.',
        capabilities: ['chat'],
      },
      trace: { traceId: 'trace-status', action: 'status', route: '/ai/status', recordedAt: '2026-03-24T00:00:00Z' },
    })

    sendAiChatMock.mockResolvedValue({
      reply: 'Describe the records you want to find, and I can draft reviewable search filters that you can apply manually.',
      status: {
        enabled: true,
        available: true,
        configured: true,
        realProvider: false,
        provider: 'mock',
        message: 'Using mock AI gateway.',
        capabilities: ['chat'],
      },
      trace: { traceId: 'trace-chat', action: 'chat', route: '/ai/chat', recordedAt: '2026-03-24T00:00:00Z' },
      provenance: [],
      warnings: [],
      structuredOutput: {
        nextActions: ['Draft filters'],
      },
    })
  })

  it('shows provider status and sends chat messages through the AI gateway', async () => {
    const user = userEvent.setup()
    renderChat()

    expect(await screen.findByText('Provider status')).toBeInTheDocument()
    expect(screen.getByText(/Using mock AI gateway/i)).toBeInTheDocument()

    await user.type(screen.getByPlaceholderText(/Ask how to use AI-assisted review/i), 'Can you help with search filters?')
    await user.click(screen.getByRole('button', { name: /send/i }))

    await waitFor(() => {
      expect(sendAiChatMock).toHaveBeenCalled()
    })

    expect(await screen.findByText(/draft reviewable search filters/i)).toBeInTheDocument()
  })

  it('extracts content and suggestions from fenced JSON assistant replies', async () => {
    const user = userEvent.setup()
    sendAiChatMock.mockResolvedValueOnce({
      reply: '```json\n{"content":"I can help streamline your metadata review process.\\n\\n**Review Guidance & Templates**\\n- Draft customized review checklists\\n- Generate review criteria aligned with FAIR principles","suggestions":["Create a review checklist","Analyze metadata gaps"],"structured_output":{}}\n```',
      status: {
        enabled: true,
        available: true,
        configured: true,
        realProvider: false,
        provider: 'mock',
        message: 'Using mock AI gateway.',
        capabilities: ['chat'],
      },
      trace: { traceId: 'trace-chat', action: 'chat', route: '/ai/chat', recordedAt: '2026-03-24T00:00:00Z' },
      provenance: [],
      warnings: [],
      structuredOutput: {},
    })

    renderChat()

    await user.type(screen.getByPlaceholderText(/Ask how to use AI-assisted review/i), 'How can you help?')
    await user.click(screen.getByRole('button', { name: /send/i }))

    expect(await screen.findByText(/streamline your metadata review process/i)).toBeInTheDocument()
    expect(screen.getByText('Review Guidance & Templates')).toBeInTheDocument()
    expect(screen.getByText('Draft customized review checklists')).toBeInTheDocument()
    expect(screen.getByText('Suggested next steps:')).toBeInTheDocument()
    expect(screen.getByText('Create a review checklist')).toBeInTheDocument()
    expect(screen.queryByText(/structured_output/i)).not.toBeInTheDocument()
  })

  it('routes FAIR assessment requests through the MCP tool bridge', async () => {
    const user = userEvent.setup()
    callMcpToolMock.mockResolvedValue({
      result: {
        content: [
          {
            text: JSON.stringify({
              studyName: 'Study ABC',
              score: {
                total: 80,
                findable: 90,
                accessible: 70,
                interoperable: 75,
                reusable: 85,
              },
              criteria: {
                findable: [
                  {
                    id: 'f1',
                    label: 'Persistent identifier',
                    description: 'A persistent identifier is available.',
                    pass: true,
                  },
                ],
              },
            }),
          },
        ],
      },
    })

    renderChat()

    await user.type(
      screen.getByPlaceholderText(/Ask how to use AI-assisted review/i),
      'Give me the FAIR score for study 123e4567-e89b-12d3-a456-426614174000'
    )
    await user.click(screen.getByRole('button', { name: /send/i }))

    await waitFor(() => {
      expect(callMcpToolMock).toHaveBeenCalledWith('get_fair_assessment', {
        id: '123e4567-e89b-12d3-a456-426614174000',
      })
    })

    expect(sendAiChatMock).not.toHaveBeenCalled()
    expect(await screen.findByText(/FAIR Assessment for "Study ABC"/i)).toBeInTheDocument()
  })

  it('routes SSA sensor observation requests through the MCP tool bridge', async () => {
    const user = userEvent.setup()
    callMcpToolMock.mockResolvedValue({
      result: {
        content: [
          {
            text: JSON.stringify({
              ok: true,
              enabled: true,
              upstreamReachable: true,
              sensorId: 'ssa-rpi-demo',
              observedAt: '2026-06-04T10:15:00Z',
              temperatureC: 22.4,
              humidityPct: 51.2,
              pressureHpa: 1008.6,
              message: null,
            }),
          },
        ],
      },
    })

    renderChat()

    await user.type(
      screen.getByPlaceholderText(/Ask how to use AI-assisted review/i),
      'Show the latest SSA sensor reading'
    )
    await user.click(screen.getByRole('button', { name: /send/i }))

    await waitFor(() => {
      expect(callMcpToolMock).toHaveBeenCalledWith('get_live_sensor_observation', {})
    })

    expect(sendAiChatMock).not.toHaveBeenCalled()
    expect(await screen.findByText(/Latest ssa-rpi-demo reading/i)).toBeInTheDocument()
    expect(screen.getByText(/22.4 °C/i)).toBeInTheDocument()
  })

  it('renders mouse subjects returned by the MCP backend collection tool', async () => {
    const user = userEvent.setup()
    callMcpToolMock.mockResolvedValue({
      result: {
        content: [
          {
            text: JSON.stringify({
              '@type': 'hydra:Collection',
              'hydra:totalItems': 1,
              'hydra:member': [
                {
                  '@id': '/subjects/123e4567-e89b-12d3-a456-426614174000',
                  name: 'MCP-MOUSE-001',
                  species: 'mouse',
                },
              ],
            }),
          },
        ],
        structuredContent: {
          '@type': 'hydra:Collection',
          'hydra:totalItems': 1,
          'hydra:member': [
            {
              '@id': '/subjects/123e4567-e89b-12d3-a456-426614174000',
              name: 'MCP-MOUSE-001',
              species: 'mouse',
            },
          ],
        },
      },
    })

    renderChat()

    await user.type(
      screen.getByPlaceholderText(/Ask how to use AI-assisted review/i),
      'Show me my mice'
    )
    await user.click(screen.getByRole('button', { name: /send/i }))

    await waitFor(() => {
      expect(callMcpToolMock).toHaveBeenCalledWith('list_mice', { species: 'mouse' })
    })

    expect(sendAiChatMock).not.toHaveBeenCalled()
    expect(await screen.findByText(/I found 1 mice/i)).toBeInTheDocument()
    expect(screen.getByText(/MCP-MOUSE-001/i)).toBeInTheDocument()
  })

  it('routes study list requests through the MCP backend collection tool', async () => {
    const user = userEvent.setup()
    callMcpToolMock.mockResolvedValue({
      result: {
        content: [
          {
            text: JSON.stringify({
              '@type': 'hydra:Collection',
              'hydra:totalItems': 1,
              'hydra:member': [
                {
                  '@id': '/experiments/123e4567-e89b-12d3-a456-426614174000',
                  name: 'Circadian rhythm baseline study',
                },
              ],
            }),
          },
        ],
        structuredContent: {
          '@type': 'hydra:Collection',
          'hydra:totalItems': 1,
          'hydra:member': [
            {
              '@id': '/experiments/123e4567-e89b-12d3-a456-426614174000',
              name: 'Circadian rhythm baseline study',
            },
          ],
        },
      },
    })

    renderChat()

    await user.type(
      screen.getByPlaceholderText(/Ask how to use AI-assisted review/i),
      'List my studies'
    )
    await user.click(screen.getByRole('button', { name: /send/i }))

    await waitFor(() => {
      expect(callMcpToolMock).toHaveBeenCalledWith('list_studies', {})
    })

    expect(sendAiChatMock).not.toHaveBeenCalled()
    expect(await screen.findByText(/I found 1 studies/i)).toBeInTheDocument()
    expect(screen.getByText(/Circadian rhythm baseline study/i)).toBeInTheDocument()
  })

  it('routes Raspberry Pi health requests to the live sensor health MCP tool', async () => {
    const user = userEvent.setup()
    callMcpToolMock.mockResolvedValue({
      result: {
        content: [
          {
            text: JSON.stringify({
              ok: true,
              enabled: true,
              upstreamReachable: true,
              status: 'ready',
              isFresh: true,
              message: null,
            }),
          },
        ],
      },
    })

    renderChat()

    await user.type(
      screen.getByPlaceholderText(/Ask how to use AI-assisted review/i),
      'Is the Raspberry Pi sensor healthy?'
    )
    await user.click(screen.getByRole('button', { name: /send/i }))

    await waitFor(() => {
      expect(callMcpToolMock).toHaveBeenCalledWith('get_live_sensor_health', {})
    })

    expect(sendAiChatMock).not.toHaveBeenCalled()
    expect(await screen.findByText(/SSA sensor health is ready/i)).toBeInTheDocument()
  })
})
