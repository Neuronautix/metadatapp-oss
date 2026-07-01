import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { describe, expect, it, vi, beforeEach } from 'vitest'
import { MappingStudio } from './MappingStudio.tsx'
import type { MappingDefinition } from '@/domain/curation/curation.types'
import type { MappingTemplate } from './curation.api.ts'

// ─── Schema mock ────────────────────────────────────────────────────────────
vi.mock('@/domain/graph/graph.schema', () => ({
  METADATAPP_GRAPH_SCHEMA: {
    entities: [
      {
        name: 'Subject',
        properties: [{ name: 'id' }, { name: 'name' }],
      },
    ],
  },
}))

// ─── Router mock ────────────────────────────────────────────────────────────
const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

// ─── API mocks ───────────────────────────────────────────────────────────────
const { listMock, saveMutationMock, deleteMutationMock, sessionSummaryMock } = vi.hoisted(() => ({
  listMock: vi.fn(),
  saveMutationMock: vi.fn(),
  deleteMutationMock: vi.fn(),
  sessionSummaryMock: vi.fn(),
}))

vi.mock('./curation.api.ts', () => ({
  useGetSessionSummary: () => ({ data: undefined }),
  useListMappingTemplates: listMock,
  useSaveMappingTemplate: saveMutationMock,
  useDeleteMappingTemplate: deleteMutationMock,
}))

// ─── Context mock ────────────────────────────────────────────────────────────
const setMappingsMock = vi.fn()
const defaultMappings: MappingDefinition[] = [
  { sourceColumn: 'mouse_id', targetEntity: 'Subject', targetProperty: 'id' },
]

vi.mock('./curation-context.tsx', () => ({
  useCuration: vi.fn(() => ({
    report: { headers: ['mouse_id', 'weight'], rowCount: 5, columnCount: 2, detectedTypes: {}, missingValueStats: {}, previewRows: [] },
    mappings: defaultMappings,
    setMappings: setMappingsMock,
    sessionId: null,
  })),
}))

const SAVED_TEMPLATES: MappingTemplate[] = [
  {
    id: 'tpl-1',
    name: 'Mouse Colony Standard',
    mappings: [{ sourceColumn: 'weight', targetEntity: 'Subject', targetProperty: 'name' }],
    createdAt: '2024-01-01T00:00:00Z',
  },
]

function renderStudio(templates: MappingTemplate[] = []) {
  listMock.mockReturnValue({ data: templates })
  const mutateSave = vi.fn()
  const mutateDelete = vi.fn()
  saveMutationMock.mockReturnValue({ mutate: mutateSave, isPending: false })
  deleteMutationMock.mockReturnValue({ mutate: mutateDelete })

  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
        <MappingStudio />
      </MemoryRouter>
    </QueryClientProvider>,
  )
  return { mutateSave, mutateDelete }
}

describe('MappingStudio — mapping templates', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders without templates without error', () => {
    renderStudio([])
    expect(screen.getByText('Semantic Mapping Studio')).toBeInTheDocument()
    expect(screen.queryByLabelText(/load a saved mapping template/i)).not.toBeInTheDocument()
  })

  it('shows a "Load template…" selector when templates are available', () => {
    renderStudio(SAVED_TEMPLATES)
    expect(screen.getByLabelText(/load a saved mapping template/i)).toBeInTheDocument()
    expect(screen.getByRole('option', { name: /Mouse Colony Standard/i })).toBeInTheDocument()
  })

  it('loads template mappings into context when a template is selected', async () => {
    const user = userEvent.setup()
    renderStudio(SAVED_TEMPLATES)

    const select = screen.getByLabelText(/load a saved mapping template/i)
    await user.selectOptions(select, 'tpl-1')

    expect(setMappingsMock).toHaveBeenCalledWith(SAVED_TEMPLATES[0].mappings)
  })

  it('shows inline save form when "Save as template" is clicked', async () => {
    const user = userEvent.setup()
    renderStudio([])

    await user.click(screen.getByRole('button', { name: /save as template/i }))

    expect(screen.getByPlaceholderText(/template name/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument()
  })

  it('calls saveMutation with name and current mappings on submit', async () => {
    const user = userEvent.setup()
    const { mutateSave } = renderStudio([])

    await user.click(screen.getByRole('button', { name: /save as template/i }))
    await user.type(screen.getByPlaceholderText(/template name/i), 'My New Template')
    await user.click(screen.getByRole('button', { name: /^save$/i }))

    expect(mutateSave).toHaveBeenCalledWith(
      { name: 'My New Template', mappings: defaultMappings },
      expect.anything(),
    )
  })

  it('shows template name chips and a delete button per template', () => {
    renderStudio(SAVED_TEMPLATES)
    expect(screen.getByText('Mouse Colony Standard')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /delete template Mouse Colony Standard/i })).toBeInTheDocument()
  })

  it('calls deleteMutation when delete button is clicked', async () => {
    const user = userEvent.setup()
    const { mutateDelete } = renderStudio(SAVED_TEMPLATES)

    await user.click(screen.getByRole('button', { name: /delete template Mouse Colony Standard/i }))

    expect(mutateDelete).toHaveBeenCalledWith('tpl-1')
  })
})
