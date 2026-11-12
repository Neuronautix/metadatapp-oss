import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, vi } from 'vitest'
import { ToastProvider } from '@/components/ui/toast.tsx'
import { MetaResourceForm } from './MetaResourceForm.tsx'
import type { UiField } from '@/metadatapp/ui-config.ts'

const apiFetchMock = vi.fn()
const toastMock = vi.fn()

vi.mock('@/lib/api.ts', () => ({
  apiFetch: (...args: any[]) => apiFetchMock(...args),
}))

vi.mock('@/components/ui/toast.tsx', async () => {
  const actual = await vi.importActual<typeof import('@/components/ui/toast.tsx')>('@/components/ui/toast.tsx')

  return {
    ...actual,
    useToast: () => ({
      toasts: [],
      dismiss: vi.fn(),
      toast: toastMock,
    }),
  }
})

const renderForm = (fields: UiField[], initialData?: Record<string, any>, onSubmit = vi.fn()) =>
  render(
    <ToastProvider>
      <MetaResourceForm fields={fields} initialData={initialData} submitLabel="Save" onSubmit={onSubmit} />
    </ToastProvider>
  )

describe('MetaResourceForm lookups', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  beforeEach(() => {
    apiFetchMock.mockReset()
    apiFetchMock.mockResolvedValue({ items: [] })
    toastMock.mockReset()
  })

  it('fills ORCID from lookup suggestions', async () => {
    apiFetchMock.mockImplementation(async (path: string) => {
      if (path === '/lookups/orcid?q=Ada%20Lovelace') {
        return {
          items: [
            {
              label: 'Ada Lovelace',
              sublabel: 'Analytical Engine Institute',
              value: '0000-0001-2345-6789',
              externalId: 'https://orcid.org/0000-0001-2345-6789',
              scheme: 'ORCID',
            },
          ],
        }
      }

      return { items: [] }
    })

    const user = userEvent.setup()
    renderForm([
      {
        key: 'orcid',
        label: 'ORCID',
        input: 'text',
        kind: 'string',
        lookup: { source: 'orcid', searchWithFormValues: ['firstName', 'lastName'] },
      } as UiField,
    ], { firstName: 'Ada', lastName: 'Lovelace' })

    await user.click(screen.getByLabelText('ORCID'))

    await waitFor(() => {
      expect(apiFetchMock).toHaveBeenCalledWith(
        '/lookups/orcid?q=Ada%20Lovelace',
        expect.objectContaining({ signal: expect.any(AbortSignal) })
      )
    })

    await user.click(await screen.findByRole('option', { name: /Ada Lovelace/i }))

    expect(screen.getByLabelText('ORCID')).toHaveValue('0000-0001-2345-6789')
  })

  it('resolves strain selections before submit', async () => {
    apiFetchMock.mockImplementation(async (path: string) => {
      if (path === '/lookups/strain/resolve') {
        return {
          value: '/strains/b769ca5f-86dd-4e8b-bbd2-7f698a4a47ef',
        }
      }

      return { items: [] }
    })

    const onSubmit = vi.fn()
    const user = userEvent.setup()

    renderForm([
      {
        key: 'strain',
        label: 'Strain',
        input: 'text',
        kind: 'string',
        lookup: { source: 'ols_efo', resolve: 'strain' },
      } as UiField,
    ], undefined, onSubmit)

    await user.type(screen.getByLabelText('Strain'), 'C57BL/6J')
    await user.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => {
      expect(apiFetchMock).toHaveBeenCalledWith('/lookups/strain/resolve', {
        method: 'POST',
        body: JSON.stringify({
          label: 'C57BL/6J',
          externalId: null,
        }),
      })
    })

    expect(onSubmit).toHaveBeenCalledWith({
      strain: '/strains/b769ca5f-86dd-4e8b-bbd2-7f698a4a47ef',
    })
  })

  it('blocks submit for unsupported species values', async () => {
    const onSubmit = vi.fn()
    const user = userEvent.setup()

    renderForm([
      {
        key: 'species',
        label: 'Species',
        input: 'text',
        kind: 'string',
        enumValues: ['mouse', 'rat', 'zebrafish'],
        lookup: { source: 'ols_ncbitaxon', requireEnumValue: true },
      } as UiField,
    ], undefined, onSubmit)

    const speciesInput = screen.getByLabelText('Species')

    await user.type(speciesInput, 'human')
    await waitFor(() => {
      expect(apiFetchMock).toHaveBeenCalledWith(
        '/lookups/ols_ncbitaxon?q=human',
        expect.objectContaining({ signal: expect.any(AbortSignal) })
      )
    })
    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSubmit).not.toHaveBeenCalled()
    expect(toastMock).toHaveBeenCalledWith({
      title: 'Unsupported species',
      description: 'Please choose one of the supported taxonomy suggestions.',
    })
    expect(speciesInput).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText('Please choose one of the supported taxonomy suggestions.')).toBeInTheDocument()
  })

  it('does not block lookup-backed species fields when the schema has no enum values', async () => {
    const onSubmit = vi.fn()
    const user = userEvent.setup()

    renderForm([
      {
        key: 'species',
        label: 'Species',
        input: 'text',
        kind: 'iri',
        lookup: { source: 'ols_ncbitaxon', requireEnumValue: true },
      } as UiField,
    ], undefined, onSubmit)

    await user.type(screen.getByLabelText('Species'), '/species/mouse')
    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSubmit).toHaveBeenCalledWith({ species: '/species/mouse' })
    expect(toastMock).not.toHaveBeenCalledWith(expect.objectContaining({ title: 'Unsupported species' }))
  })

  it('blocks submit and shows an inline error for invalid JSON fields', async () => {
    const onSubmit = vi.fn()
    const user = userEvent.setup()

    renderForm([
      {
        key: 'elabftwMetadata',
        label: 'eLabFTW Extra Fields',
        input: 'json',
        kind: 'object',
      } as UiField,
    ], undefined, onSubmit)

    fireEvent.change(screen.getByLabelText('eLabFTW Extra Fields'), { target: { value: '{invalid' } })
    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSubmit).not.toHaveBeenCalled()
    expect(screen.getByText('eLabFTW Extra Fields must be valid JSON.')).toBeInTheDocument()
    expect(toastMock).toHaveBeenCalledWith({
      title: 'Invalid field value',
      description: 'eLabFTW Extra Fields must be valid JSON.',
    })
  })

  it('omits read-only fields while preserving editable false boolean values', async () => {
    const onSubmit = vi.fn()
    const user = userEvent.setup()

    renderForm([
      {
        key: 'name',
        label: 'Name',
        input: 'text',
        kind: 'string',
        readOnly: true,
      } as UiField,
      {
        key: 'active',
        label: 'Active',
        input: 'boolean',
        kind: 'boolean',
      } as UiField,
    ], { name: 'Managed name', active: true }, onSubmit)

    expect(screen.getByLabelText('Name')).toBeDisabled()

    await user.selectOptions(screen.getByLabelText('Active'), 'false')
    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSubmit).toHaveBeenCalledWith({ active: false })
  })

  it('debounces lookup requests and aborts stale in-flight calls', async () => {
    vi.useFakeTimers()

    apiFetchMock.mockImplementation(
      (_path: string, options?: { signal?: AbortSignal }) =>
        new Promise((resolve) => {
          const timeoutId = window.setTimeout(() => resolve({ items: [] }), 1000)
          options?.signal?.addEventListener('abort', () => resolve({ items: [] }), { once: true })
          options?.signal?.addEventListener('abort', () => window.clearTimeout(timeoutId), { once: true })
        })
    )

    renderForm([
      {
        key: 'orcid',
        label: 'ORCID',
        input: 'text',
        kind: 'string',
        lookup: { source: 'orcid' },
      } as UiField,
    ])

    const input = screen.getByLabelText('ORCID')

    await act(async () => {
      fireEvent.focus(input)
      fireEvent.change(input, { target: { value: 'Ad' } })
    })

    expect(apiFetchMock).not.toHaveBeenCalled()

    await act(async () => {
      await vi.advanceTimersByTimeAsync(250)
    })

    expect(apiFetchMock).toHaveBeenCalledTimes(1)
    const firstSignal = apiFetchMock.mock.calls[0]?.[1]?.signal as AbortSignal
    expect(firstSignal).toBeInstanceOf(AbortSignal)

    await act(async () => {
      fireEvent.change(input, { target: { value: 'Ada' } })
    })

    expect(firstSignal.aborted).toBe(true)

    await act(async () => {
      await vi.advanceTimersByTimeAsync(250)
    })

    expect(apiFetchMock).toHaveBeenCalledTimes(2)
    expect(apiFetchMock.mock.calls[1]?.[0]).toBe('/lookups/orcid?q=Ada')
  })
})

describe('MetaResourceForm payload shaping', () => {
  beforeEach(() => {
    apiFetchMock.mockReset()
    apiFetchMock.mockResolvedValue({ items: [] })
    toastMock.mockReset()
  })

  it('submits only editable non-system fields', async () => {
    const onSubmit = vi.fn()
    const user = userEvent.setup()

    renderForm([
      { key: '@id', label: 'IRI', input: 'text', kind: 'string', required: false } as UiField,
      { key: 'id', label: 'ID', input: 'text', kind: 'string', required: false } as UiField,
      { key: 'name', label: 'Name', input: 'text', kind: 'string', required: false } as UiField,
      { key: 'lastSyncAt', label: 'Last Sync', input: 'datetime', kind: 'string', readOnly: true, required: false } as UiField,
    ], {
      '@id': '/connected_apps/123',
      id: '123',
      name: 'Lab sync',
      lastSyncAt: '2026-05-29T10:00:00+02:00',
    }, onSubmit)

    expect(screen.queryByLabelText('IRI')).not.toBeInTheDocument()
    expect(screen.queryByLabelText('ID')).not.toBeInTheDocument()
    expect(screen.getByLabelText('Last Sync')).toBeDisabled()

    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSubmit).toHaveBeenCalledWith({
      name: 'Lab sync',
    })
  })

  it('shows JSON parse errors and blocks submit', async () => {
    const onSubmit = vi.fn()
    const user = userEvent.setup()

    renderForm([
      { key: 'metadata', label: 'Metadata', input: 'json', kind: 'object', required: false } as UiField,
    ], undefined, onSubmit)

    await user.type(screen.getByLabelText('Metadata'), '{{')
    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSubmit).not.toHaveBeenCalled()
    expect(toastMock).toHaveBeenCalledWith({
      title: 'Invalid field value',
      description: 'Metadata must be valid JSON.',
    })
    expect(screen.getByLabelText('Metadata')).toHaveAttribute('aria-invalid', 'true')
    expect(screen.getByText('Metadata must be valid JSON.')).toBeInTheDocument()
  })

  it('keeps unchanged JSON objects typed in the payload', async () => {
    const onSubmit = vi.fn()
    const user = userEvent.setup()

    renderForm([
      { key: 'metadata', label: 'Metadata', input: 'json', kind: 'object', required: false } as UiField,
    ], {
      metadata: { source: 'elabftw', flags: ['synced'] },
    }, onSubmit)

    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSubmit).toHaveBeenCalledWith({
      metadata: { source: 'elabftw', flags: ['synced'] },
    })
  })

  it('preserves boolean false values', async () => {
    const onSubmit = vi.fn()
    const user = userEvent.setup()

    renderForm([
      { key: 'isPregnant', label: 'Pregnant', input: 'boolean', kind: 'boolean', required: false } as UiField,
    ], {
      isPregnant: false,
    }, onSubmit)

    expect(screen.getByLabelText('Pregnant')).toHaveValue('false')

    await user.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSubmit).toHaveBeenCalledWith({
      isPregnant: false,
    })
  })

  it('normalizes local date-time input without shifting time zones', async () => {
    const onSubmit = vi.fn()

    renderForm([
      { key: 'measuredAt', label: 'Measured At', input: 'datetime', kind: 'string', required: false } as UiField,
    ], undefined, onSubmit)

    fireEvent.change(screen.getByLabelText('Measured At'), { target: { value: '2026-05-29T08:15' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    expect(onSubmit).toHaveBeenCalledWith({
      measuredAt: '2026-05-29T08:15:00',
    })
  })
})
