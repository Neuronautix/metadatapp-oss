import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { afterEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { HcmMetadataFormPage } from './HcmMetadataFormPage.tsx'
import { apiFetch, apiFetchHydraCollection } from '@/lib/api.ts'
import type { CanonicalForm } from './hcm-metadata-form.types.ts'

// Mock the transport layer so the real feature hook, lookup, and grouping logic run.
vi.mock('@/lib/api.ts', () => ({
  apiFetch: vi.fn(),
  apiFetchHydraCollection: vi.fn(),
}))

const mockedApiFetch = vi.mocked(apiFetch)
const mockedHydra = vi.mocked(apiFetchHydraCollection)

const studySection = {
  id: 'sec-study',
  title: 'Study',
  description: 'Top-level study identification.',
  orderIndex: 0,
}
const animalsSection = {
  id: 'sec-animals',
  title: 'Animals',
  description: 'Biological characteristics of the animals.',
  orderIndex: 1,
}

const sampleForm: CanonicalForm = {
  id: 'form-hcm',
  title: 'HCM Minimal Metadata Form',
  description: 'Minimal FAIR-aligned HCM metadata.',
  domain: 'home-cage-monitoring',
  version: '1.0.0',
  status: 'active',
  sourceLinks: {
    isaMapping: {
      investigation: {
        study: {
          forms: [
            { section: 'study', isaForm: 'Study' },
            { section: 'animals', isaForm: 'Animal Form' },
          ],
        },
      },
    },
  },
  createdAt: '2026-01-15T09:00:00Z',
  updatedAt: '2026-01-15T09:00:00Z',
  sections: [animalsSection, studySection],
  fields: [
    {
      id: 'field-species',
      section: animalsSection,
      localKey: 'species',
      label: 'Species',
      description: 'The taxonomic species.',
      datatype: 'string',
      required: true,
      unitConstraint: null,
      allowedValues: [
        { value: 'Mus musculus', ontologyTerm: 'http://purl.obolibrary.org/obo/NCBITaxon_10090' },
      ],
      ontologyMappings: [
        { iri: 'http://purl.obolibrary.org/obo/OBI_0100026', label: 'organism', source: 'OBI' },
      ],
      jsonldPath: null,
      sourceFieldReference: 'cde:species',
      orderIndex: 0,
    },
    {
      id: 'field-study-identifier',
      section: studySection,
      localKey: 'study_identifier',
      label: 'Study identifier',
      description: 'A unique identifier.',
      datatype: 'string',
      required: true,
      unitConstraint: null,
      allowedValues: [],
      ontologyMappings: [
        { iri: 'http://purl.org/dc/terms/identifier', label: 'identifier', source: 'DCTERMS' },
      ],
      jsonldPath: null,
      sourceFieldReference: 'cde:study_identifier',
      orderIndex: 0,
    },
  ],
}

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  )
}

describe('HcmMetadataFormPage', () => {
  afterEach(() => {
    mockedApiFetch.mockReset()
    mockedHydra.mockReset()
  })

  it('renders sections (ordered), fields, CDE references and ontology chips', async () => {
    mockedHydra.mockResolvedValue({ data: [sampleForm], page: 1, pageSize: 1, total: 1 })
    mockedApiFetch.mockResolvedValue(sampleForm)

    render(<HcmMetadataFormPage />, { wrapper: createWrapper() })

    // Section titles render (ordered: Study before Animals despite source order)
    expect(await screen.findByText('Study')).toBeInTheDocument()
    expect(screen.getByText('Animals')).toBeInTheDocument()

    const headings = screen.getAllByRole('heading', { level: 3 })
    const sectionTitles = headings
      .map((node) => node.textContent ?? '')
      .filter((text) => text.includes('Study') || text.includes('Animals'))
    const studyIndex = sectionTitles.findIndex((t) => t.includes('Study'))
    const animalsIndex = sectionTitles.findIndex((t) => t.includes('Animals'))
    expect(studyIndex).toBeLessThan(animalsIndex)

    // Fields render
    expect(screen.getByText('Species')).toBeInTheDocument()
    expect(screen.getByText('Study identifier')).toBeInTheDocument()

    // CDE reference badge
    expect(screen.getByText(/CDE:\s*cde:species/)).toBeInTheDocument()

    // Permissible / allowed value
    expect(screen.getByText('Mus musculus')).toBeInTheDocument()

    // Ontology mapping chip
    expect(screen.getByText('organism')).toBeInTheDocument()

    // ISA hierarchy annotation per section
    expect(screen.getByText(/ISA:\s*Study/)).toBeInTheDocument()
    expect(screen.getByText(/ISA:\s*Animal Form/)).toBeInTheDocument()

    // Export affordances: Download JSON action + per-field Copy CDE reference.
    expect(screen.getByRole('button', { name: /download json/i })).toBeInTheDocument()
    expect(
      screen.getByRole('button', { name: /copy cde reference cde:species/i }),
    ).toBeInTheDocument()

    // Ontology links are labelled for assistive tech.
    expect(
      screen.getByRole('link', { name: /open ontology term for mus musculus/i }),
    ).toBeInTheDocument()
  })

  it('renders an error state when the HCM form is absent from the collection', async () => {
    mockedHydra.mockResolvedValue({ data: [], page: 1, pageSize: 0, total: 0 })

    render(<HcmMetadataFormPage />, { wrapper: createWrapper() })

    expect(await screen.findByText('Unable to load the HCM form')).toBeInTheDocument()
    expect(screen.getByText(/not available/i)).toBeInTheDocument()
  })
})
