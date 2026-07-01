import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { TemplateCrosswalkStudioPage } from './TemplateCrosswalkStudioPage.tsx'
import type {
  CommonDataElement,
  TemplateFormCrosswalk,
  ValidationReport,
} from './template-crosswalks.api.ts'

// ---------------------------------------------------------------------------
// Toast mock
// ---------------------------------------------------------------------------

vi.mock('@/components/ui/toast.tsx', () => ({
  useToast: () => ({ toast: vi.fn(), dismiss: vi.fn(), toasts: [] }),
}))

// ---------------------------------------------------------------------------
// API mocks (mirror the FieldCrosswalk / CrosswalkStudio data flow)
// ---------------------------------------------------------------------------

const {
  listMock,
  getMock,
  searchCdesMock,
  suggestMutate,
  validateMutate,
  acceptMutate,
  rejectMutate,
  updateMutate,
  exportRoCrateMutate,
  exportJsonLdMutate,
  createCdeMutate,
  importElnMutate,
  importUrlMutate,
} = vi.hoisted(() => ({
  listMock: vi.fn(),
  getMock: vi.fn(),
  searchCdesMock: vi.fn(),
  suggestMutate: vi.fn(),
  validateMutate: vi.fn(),
  acceptMutate: vi.fn(),
  rejectMutate: vi.fn(),
  updateMutate: vi.fn(),
  exportRoCrateMutate: vi.fn(),
  exportJsonLdMutate: vi.fn(),
  createCdeMutate: vi.fn(),
  importElnMutate: vi.fn(),
  importUrlMutate: vi.fn(),
}))

const mutation = (mutate: ReturnType<typeof vi.fn>) => ({ mutate, isPending: false })

vi.mock('./template-crosswalks.api.ts', async () => {
  const actual = await vi.importActual<typeof import('./template-crosswalks.api.ts')>(
    './template-crosswalks.api.ts',
  )
  return {
    ...actual,
    useListCrosswalks: () => listMock(),
    useGetCrosswalk: () => getMock(),
    useSearchCdes: () => searchCdesMock(),
    useSuggestMappings: () => mutation(suggestMutate),
    useValidateCrosswalk: () => mutation(validateMutate),
    useAcceptFieldCrosswalk: () => mutation(acceptMutate),
    useRejectFieldCrosswalk: () => mutation(rejectMutate),
    useUpdateFieldCrosswalk: () => mutation(updateMutate),
    useExportRoCrate: () => mutation(exportRoCrateMutate),
    useExportJsonLd: () => mutation(exportJsonLdMutate),
    useCreateCde: () => mutation(createCdeMutate),
    useImportEln: () => mutation(importElnMutate),
    useImportElnUrl: () => mutation(importUrlMutate),
  }
})

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

const cde: CommonDataElement = {
  id: '/cdes/mw',
  source: 'metadatapp',
  label: 'Molecular weight',
  definition: 'The mass of a molecule.',
  datatype: 'decimal',
  unitConstraint: 'g/mol',
  permissibleValues: [],
  ontologyMappings: [{ iri: 'http://purl.obolibrary.org/obo/CHEBI_27594' }],
  provenance: {},
  status: 'recommended',
  localKey: 'molecular_weight',
}

const crosswalk: TemplateFormCrosswalk = {
  id: '/template-crosswalks/1',
  externalTemplate: {
    id: '/external-templates/1',
    source: 'eln_file',
    title: 'My ELN Template',
    externalUrl: 'https://eln.community/record/abc',
    format: 'eln_ro_crate',
    importedAt: '2026-06-17T00:00:00Z',
    extractedFields: [
      {
        id: '/extracted/1',
        label: 'molecularWeight',
        jsonldPath: '$.graph[2].variableMeasured',
        datatype: 'number',
        unit: 'g/mol',
        exampleValue: '128.12',
        ontologyTerms: [{ iri: 'http://example.org/mw', label: 'MW' }],
        rawNode: {},
        orderIndex: 0,
        mapped: true,
      },
    ],
  },
  canonicalForm: {
    id: '/canonical-forms/1',
    title: 'Chemistry Form',
    version: '1.0.0',
    status: 'active',
    sourceLinks: [],
    createdAt: '2026-06-17T00:00:00Z',
    updatedAt: '2026-06-17T00:00:00Z',
    sections: [],
    fields: [],
  },
  mappingType: 'related',
  mappingStatus: 'suggested',
  version: '1.0.0',
  createdAt: '2026-06-17T00:00:00Z',
  fieldCrosswalks: [
    {
      id: '/field-crosswalks/1',
      cde,
      elnField: {
        id: '/extracted/1',
        label: 'molecularWeight',
        ontologyTerms: [],
        rawNode: {},
        orderIndex: 0,
        mapped: true,
      },
      mappingType: 'exact',
      mappingStatus: 'suggested',
      confidence: 0.94,
      datatypeMapping: {},
      unitMapping: {},
      valueMapping: {},
      curatorNotes: '',
      valueCrosswalks: [],
    },
  ],
}

const report: ValidationReport = {
  fieldsExtracted: 1,
  fieldsMappedToCdes: 1,
  unmappedFields: 0,
  numericFields: 1,
  numericFieldsWithUnits: 1,
  categoricalFields: 0,
  categoricalFieldsWithControlledValues: 0,
  fieldsRequiringReview: 1,
  issues: [{ code: 'MAPPING_NOT_REVIEWED', severity: 'warning', message: 'Needs review' }],
  semanticCompletenessScore: 88,
}

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <MemoryRouter>
      <QueryClientProvider client={client}>
        <TemplateCrosswalkStudioPage />
      </QueryClientProvider>
    </MemoryRouter>,
  )
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('TemplateCrosswalkStudioPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    listMock.mockReturnValue({ data: [crosswalk], isLoading: false, refetch: vi.fn() })
    getMock.mockReturnValue({ data: crosswalk, isLoading: false })
    searchCdesMock.mockReturnValue({ data: [], isFetching: false })
  })

  it('renders the page heading', () => {
    renderPage()
    expect(screen.getByRole('heading', { name: /Template Crosswalk Studio/i })).toBeInTheDocument()
  })

  it('renders the three panels and extracted field', () => {
    renderPage()
    expect(screen.getByText('ELN Template')).toBeInTheDocument()
    expect(screen.getByText('Chemistry Form')).toBeInTheDocument()
    expect(screen.getByText('CDE Library')).toBeInTheDocument()
    expect(screen.getByText('molecularWeight')).toBeInTheDocument()
  })

  it('shows the empty state when there are no crosswalks', () => {
    listMock.mockReturnValue({ data: [], isLoading: false, refetch: vi.fn() })
    getMock.mockReturnValue({ data: undefined, isLoading: false })
    renderPage()
    expect(screen.getByText('Nothing to map yet')).toBeInTheDocument()
  })

  it('triggers validate and renders the report', async () => {
    validateMutate.mockImplementation((_id: string, opts?: { onSuccess?: (r: ValidationReport) => void }) =>
      opts?.onSuccess?.(report),
    )
    renderPage()
    await userEvent.click(screen.getByRole('button', { name: /Validate/i }))
    await waitFor(() => {
      expect(screen.getByText('Validation')).toBeInTheDocument()
      expect(screen.getByText('88/100')).toBeInTheDocument()
      expect(screen.getByText('MAPPING_NOT_REVIEWED')).toBeInTheDocument()
    })
  })

  it('calls suggest mappings', async () => {
    suggestMutate.mockImplementation((_id: string, opts?: { onSuccess?: (r: unknown) => void }) =>
      opts?.onSuccess?.({ suggestions: [] }),
    )
    renderPage()
    await userEvent.click(screen.getByRole('button', { name: 'Suggest' }))
    expect(suggestMutate).toHaveBeenCalledWith('/template-crosswalks/1', expect.anything())
  })

  it('accepts a field crosswalk after selecting it', async () => {
    renderPage()
    // The first field crosswalk is selected by default, so Accept is visible.
    await userEvent.click(screen.getByRole('button', { name: /Accept/i }))
    expect(acceptMutate).toHaveBeenCalledWith('/field-crosswalks/1', expect.anything())
  })

  it('rejects a field crosswalk', async () => {
    renderPage()
    await userEvent.click(screen.getByRole('button', { name: /Reject/i }))
    expect(rejectMutate).toHaveBeenCalledWith('/field-crosswalks/1', expect.anything())
  })

  it('changes the mapping type via the select', async () => {
    renderPage()
    const select = screen.getByLabelText(/Mapping type for molecularWeight/i)
    await userEvent.selectOptions(select, 'close')
    expect(updateMutate).toHaveBeenCalledWith({
      id: '/field-crosswalks/1',
      patch: { mappingType: 'close' },
    })
  })

  it('exports JSON-LD', async () => {
    renderPage()
    await userEvent.click(screen.getByRole('button', { name: /JSON-LD/i }))
    expect(exportJsonLdMutate).toHaveBeenCalledWith('/template-crosswalks/1', expect.anything())
  })

  it('opens the create-CDE form', async () => {
    renderPage()
    await userEvent.click(screen.getByRole('button', { name: /New CDE/i }))
    expect(screen.getByText('Create new Metadatapp CDE')).toBeInTheDocument()
  })
})
