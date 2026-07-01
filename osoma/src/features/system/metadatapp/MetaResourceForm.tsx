import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Label } from '@/components/ui/label.tsx'
import { Textarea } from '@/components/ui/textarea.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { AiSuggestionDialog } from '@/features/ai-assistant/components/AiSuggestionDialog'
import type { UiField, UiLookupResolver } from '@/metadatapp/ui-config.ts'
import { extractReference } from '@/metadatapp/ui-config.ts'
import { apiFetch } from '@/lib/api.ts'

const isSystemField = (field: UiField) => ['@id', '@context', '@type', 'id'].includes(field.key)
const isVisibleField = (field: UiField) => !isSystemField(field) && !field.hidden
const isEditableField = (field: UiField) => isVisibleField(field) && !field.readOnly

const formatDateValue = (value: string) => value.split('T')[0] ?? value
const formatDateTimeValue = (value: string) => {
  const match = value.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/)
  return match ? match[1] : value
}

const normalizeDateTimeValue = (value: string) => {
  const trimmed = value.trim()
  if (!trimmed) return undefined
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(trimmed)) {
    return `${trimmed}:00`
  }
  return trimmed
}

const formatValue = (value: any, field: UiField) => {
  if (value === undefined || value === null) return ''
  if (field.input === 'tags') {
    if (Array.isArray(value)) {
      return value
        .map((entry) => extractReference(entry))
        .filter(Boolean)
        .join(', ')
    }
    const ref = extractReference(value)
    return ref ?? String(value)
  }
  if (field.input === 'iri') {
    const ref = extractReference(value)
    return ref ?? String(value)
  }
  if (field.input === 'json') {
    return typeof value === 'string' ? value : JSON.stringify(value, null, 2)
  }
  if (field.input === 'date' && typeof value === 'string') {
    return formatDateValue(value)
  }
  if (field.input === 'datetime' && typeof value === 'string') {
    return formatDateTimeValue(value)
  }
  return String(value)
}

type ParsedValue =
  | { ok: true; value: unknown }
  | { ok: false; error: string }

const parseValue = (value: unknown, field: UiField): ParsedValue => {
  if (value === undefined || value === null || value === '') return { ok: true, value: undefined }
  if (field.input === 'number') {
    if (typeof value === 'number') return { ok: true, value }
    const raw = String(value)
    const parsed = Number(value)
    return { ok: true, value: raw.trim() && Number.isFinite(parsed) ? parsed : undefined }
  }
  if (field.input === 'boolean') {
    if (typeof value === 'boolean') return { ok: true, value }
    return { ok: true, value: String(value) === 'true' }
  }
  if (field.input === 'tags') {
    if (Array.isArray(value)) {
      return {
        ok: true,
        value: value
          .map((entry) => extractReference(entry) ?? String(entry))
          .filter(Boolean),
      }
    }
    return {
      ok: true,
      value: String(value)
        .split(',')
        .map((entry) => entry.trim())
        .filter(Boolean),
    }
  }
  if (field.input === 'json') {
    if (typeof value !== 'string') return { ok: true, value }
    try {
      return { ok: true, value: JSON.parse(value) }
    } catch {
      return { ok: false, error: `${field.label} must be valid JSON.` }
    }
  }
  if (field.input === 'date') {
    return { ok: true, value: String(value).trim() || undefined }
  }
  if (field.input === 'datetime') {
    return { ok: true, value: normalizeDateTimeValue(String(value)) }
  }
  return { ok: true, value: String(value) }
}

type LookupItem = {
  label: string
  sublabel?: string | null
  value: string
  externalId?: string | null
  scheme: string
}

const LOOKUP_REQUEST_DEBOUNCE_MS = 250

const getLookupQuery = (field: UiField, value: string, formData: Record<string, any>) => {
  const minLength = field.lookup?.minLength ?? 2
  const current = value.trim()
  if (current.length >= minLength) return current

  const derived = (field.lookup?.searchWithFormValues ?? [])
    .map((key) => String(formData[key] ?? '').trim())
    .filter(Boolean)
    .join(' ')

  return derived.length >= minLength ? derived : ''
}

const isResolvedLookupValue = (resolver: UiLookupResolver, value: string) => {
  if (resolver === 'strain') {
    return value.startsWith('/strains/')
  }

  return false
}

type LookupInputProps = {
  field: UiField
  inputId: string
  value: string
  formData: Record<string, any>
  disabled: boolean
  invalid?: boolean
  placeholder?: string
  onChange: (value: string) => void
  onSelect: (item: LookupItem) => void
  onClearSelection: () => void
}

function LookupInput({ field, inputId, value, formData, disabled, invalid, placeholder, onChange, onSelect, onClearSelection }: LookupInputProps) {
  const containerRef = useRef<HTMLDivElement | null>(null)
  const [items, setItems] = useState<LookupItem[]>([])
  const [isPending, setIsPending] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [isFocused, setIsFocused] = useState(false)
  const [activeIndex, setActiveIndex] = useState(-1)
  const query = useMemo(() => getLookupQuery(field, value, formData), [field, formData, value])
  const listboxId = `${inputId}-suggestions`
  const popupOpen = isFocused && (isPending || isLoading || items.length > 0)

  const selectItem = (item: LookupItem) => {
    onSelect(item)
    onChange((field.lookup?.mapTo ?? 'value') === 'label' ? item.label : item.value)
    setItems([])
    setActiveIndex(-1)
    setIsFocused(false)
  }

  useEffect(() => {
    const lookupConfig = field.lookup

    if (!lookupConfig || !isFocused || disabled || query === '') {
      setIsPending(false)
      setItems([])
      setIsLoading(false)
      setActiveIndex(-1)
      return
    }

    let ignore = false
    const controller = new AbortController()
    setIsPending(true)

    const timeoutId = window.setTimeout(() => {
      if (ignore) {
        return
      }

      setIsPending(false)
      setIsLoading(true)

      void apiFetch<{ items: LookupItem[] }>(`/lookups/${lookupConfig.source}?q=${encodeURIComponent(query)}`, {
        signal: controller.signal,
      })
        .then((response) => {
          if (ignore) return
          setItems(response.items ?? [])
          setActiveIndex(-1)
        })
        .catch((error: unknown) => {
          if (ignore) return
          if (error instanceof DOMException && error.name === 'AbortError') {
            return
          }
          setItems([])
          setActiveIndex(-1)
        })
        .finally(() => {
          if (ignore) return
          setIsLoading(false)
        })
    }, LOOKUP_REQUEST_DEBOUNCE_MS)

    return () => {
      ignore = true
      window.clearTimeout(timeoutId)
      controller.abort()
      setIsPending(false)
    }
  }, [disabled, field.lookup, isFocused, query])

  return (
    <div
      ref={containerRef}
      className="relative"
      onBlur={(event) => {
        const nextTarget = event.relatedTarget
        if (containerRef.current && nextTarget instanceof Node && containerRef.current.contains(nextTarget)) {
          return
        }

        setIsFocused(false)
        setActiveIndex(-1)
      }}
    >
      <Input
        id={inputId}
        type="text"
        value={value}
        onFocus={() => setIsFocused(true)}
        onChange={(event) => {
          onClearSelection()
          onChange(event.target.value)
          setActiveIndex(-1)
          setIsFocused(true)
        }}
        onKeyDown={(event) => {
          if (!popupOpen || items.length === 0) {
            if (event.key === 'ArrowDown' && !disabled) {
              setIsFocused(true)
            }
            return
          }

          if (event.key === 'ArrowDown') {
            event.preventDefault()
            setActiveIndex((current) => (current + 1) % items.length)
            return
          }

          if (event.key === 'ArrowUp') {
            event.preventDefault()
            setActiveIndex((current) => (current <= 0 ? items.length - 1 : current - 1))
            return
          }

          if (event.key === 'Enter' && activeIndex >= 0 && activeIndex < items.length) {
            event.preventDefault()
            selectItem(items[activeIndex])
            return
          }

          if (event.key === 'Escape') {
            event.preventDefault()
            setIsFocused(false)
            setActiveIndex(-1)
          }
        }}
        placeholder={placeholder}
        disabled={disabled}
        aria-invalid={invalid}
        autoComplete="off"
        role="combobox"
        aria-autocomplete="list"
        aria-haspopup="listbox"
        aria-expanded={popupOpen}
        aria-controls={popupOpen ? listboxId : undefined}
        aria-activedescendant={activeIndex >= 0 ? `${listboxId}-option-${activeIndex}` : undefined}
      />
      {popupOpen ? (
        <div className="absolute z-10 mt-1 w-full rounded-2xl border border-line bg-surface p-2 shadow-subtle">
          {isLoading ? <p className="px-2 py-1 text-xs text-muted">Searching…</p> : null}
          {!isLoading && items.length > 0 ? (
            <ul id={listboxId} className="space-y-1" role="listbox" aria-label={`${field.label} suggestions`}>
              {items.map((item, index) => (
                <li key={`${item.scheme}:${item.externalId ?? item.value}`}>
                  <button
                    id={`${listboxId}-option-${index}`}
                    type="button"
                    role="option"
                    aria-selected={index === activeIndex}
                    className="w-full rounded-xl px-3 py-2 text-left hover:bg-surface-2"
                    onMouseDown={(event) => {
                      event.preventDefault()
                      selectItem(item)
                    }}
                    onMouseEnter={() => setActiveIndex(index)}
                    onFocus={() => setActiveIndex(index)}
                  >
                    <span className="block text-sm font-medium text-ink">{item.label}</span>
                    {item.sublabel ? <span className="block text-xs text-muted">{item.sublabel}</span> : null}
                  </button>
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}

export type MetaResourceFormProps = {
  fields: UiField[]
  initialData?: Record<string, any>
  submitLabel: string
  onSubmit: (payload: Record<string, any>) => void
  isSubmitting?: boolean
  assistantConfig?: {
    resourceType: string
    resourceId?: string
    resourceLabel: string
  }
  footerActions?: ReactNode
}

const isObjectRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value)

export function MetaResourceForm({
  fields,
  initialData,
  submitLabel,
  onSubmit,
  isSubmitting,
  assistantConfig,
  footerActions,
}: MetaResourceFormProps) {
  const { toast } = useToast()
  const visibleFields = useMemo(() => fields.filter(isVisibleField), [fields])
  const editableFields = useMemo(() => visibleFields.filter(isEditableField), [visibleFields])
  const [formData, setFormData] = useState<Record<string, any>>({})
  const [fieldErrors, setFieldErrors] = useState<Record<string, string | undefined>>({})
  const [selectedLookups, setSelectedLookups] = useState<Record<string, LookupItem | undefined>>({})
  const [isResolvingLookups, setIsResolvingLookups] = useState(false)

  useEffect(() => {
    setFormData(initialData ?? {})
    setFieldErrors({})
    setSelectedLookups({})
  }, [initialData])

  const updateField = (key: string, value: string) => {
    setFormData((prev) => ({ ...prev, [key]: value }))
    setFieldErrors((prev) => ({ ...prev, [key]: undefined }))
  }

  const handleSubmit = async () => {
    const payload: Record<string, any> = {}
    setIsResolvingLookups(true)
    try {
      for (const field of editableFields) {
        let valueToParse = formData[field.key]
        let raw = String(valueToParse ?? '')

        if (
          field.lookup?.requireEnumValue &&
          raw !== '' &&
          field.enumValues?.length &&
          !field.enumValues.includes(raw)
        ) {
          const errorMessage = 'Please choose one of the supported taxonomy suggestions.'
          setFieldErrors((prev) => ({ ...prev, [field.key]: errorMessage }))
          toast({ title: 'Unsupported species', description: errorMessage })
          return
        }

        if (field.lookup?.resolve && raw !== '' && !isResolvedLookupValue(field.lookup.resolve, raw)) {
          const resolved = await apiFetch<{ value: string }>('/lookups/strain/resolve', {
            method: 'POST',
            body: JSON.stringify({
              label: raw,
              externalId: selectedLookups[field.key]?.externalId ?? null,
            }),
          })
          raw = resolved.value
          valueToParse = raw
        }

        const parsed = parseValue(valueToParse, field)
        if (!parsed.ok) {
          setFieldErrors((prev) => ({ ...prev, [field.key]: parsed.error }))
          toast({ title: 'Invalid field value', description: parsed.error })
          return
        }

        const value = parsed.value
        if (field.required && (value === undefined || value === '')) {
          const errorMessage = `${field.key} is required.`
          setFieldErrors((prev) => ({ ...prev, [field.key]: errorMessage }))
          toast({ title: 'Missing fields', description: errorMessage })
          return
        }
        setFieldErrors((prev) => ({ ...prev, [field.key]: undefined }))
        if (value !== undefined) {
          payload[field.key] = value
        }
      }
      onSubmit(payload)
    } finally {
      setIsResolvingLookups(false)
    }
  }

  const buildAssistantContext = () => ({
    resourceType: assistantConfig?.resourceType ?? 'metadata',
    resourceId: assistantConfig?.resourceId ?? null,
    resourceLabel: assistantConfig?.resourceLabel ?? 'Metadata record',
    fields: Object.fromEntries(editableFields.map((field) => [field.key, formData[field.key] ?? ''])),
    fieldMeta: editableFields.map((field) => ({
      key: field.key,
      label: field.label,
      helper: field.helper ?? null,
      required: field.required,
      input: field.input,
    })),
  })

  return (
    <div className="space-y-5">
      {visibleFields.map((field) => {
        const placeholder =
          field.placeholder ??
          (field.input === 'tags'
            ? 'Comma separated values'
            : field.input === 'json'
              ? 'JSON object'
              : undefined)
        const disabled = Boolean(field.readOnly) || Boolean(isSubmitting) || isResolvingLookups
        const errorMessage = fieldErrors[field.key]
        return (
        <div key={field.key}>
          <Label htmlFor={`meta-field-${field.key}`}>
            {field.label}
            {field.required && !field.readOnly ? <span className="text-rose-500"> *</span> : null}
          </Label>
          {field.input === 'select' ? (
            <select
              id={`meta-field-${field.key}`}
              className="w-full rounded-full border border-line bg-surface px-3 py-2 text-sm text-ink"
              aria-invalid={Boolean(errorMessage)}
              value={formData[field.key] ?? ''}
              onChange={(event) => updateField(field.key, event.target.value)}
              disabled={disabled}
            >
              <option value="">Select</option>
              {(field.enumValues ?? []).map((option) => (
                <option key={option} value={option}>
                  {option}
                </option>
              ))}
            </select>
          ) : field.input === 'boolean' ? (
            <select
              id={`meta-field-${field.key}`}
              className="w-full rounded-full border border-line bg-surface px-3 py-2 text-sm text-ink"
              aria-invalid={Boolean(errorMessage)}
              value={String(formData[field.key] ?? '')}
              onChange={(event) => updateField(field.key, event.target.value)}
              disabled={disabled}
            >
              <option value="">Select</option>
              <option value="true">true</option>
              <option value="false">false</option>
            </select>
          ) : field.input === 'json' || field.input === 'textarea' ? (
            <Textarea
              id={`meta-field-${field.key}`}
              value={formatValue(formData[field.key], field)}
              onChange={(event) => updateField(field.key, event.target.value)}
              placeholder={placeholder}
              className="min-h-[120px]"
              aria-invalid={Boolean(errorMessage)}
              disabled={disabled}
            />
          ) : field.lookup ? (
            <LookupInput
              field={field}
              inputId={`meta-field-${field.key}`}
              value={formatValue(formData[field.key], field)}
              formData={formData}
              invalid={Boolean(errorMessage)}
              onChange={(value) => updateField(field.key, value)}
              onSelect={(item) => setSelectedLookups((prev) => ({ ...prev, [field.key]: item }))}
              onClearSelection={() => setSelectedLookups((prev) => ({ ...prev, [field.key]: undefined }))}
              placeholder={placeholder ?? (field.input === 'iri' ? '/resource/id' : undefined)}
              disabled={disabled}
            />
          ) : (
            <Input
              id={`meta-field-${field.key}`}
              type={
                field.input === 'number'
                  ? 'number'
                  : field.input === 'date'
                    ? 'date'
                    : field.input === 'datetime'
                      ? 'datetime-local'
                      : 'text'
              }
              value={formatValue(formData[field.key], field)}
              onChange={(event) => updateField(field.key, event.target.value)}
              placeholder={placeholder ?? (field.input === 'iri' ? '/resource/id' : undefined)}
              aria-invalid={Boolean(errorMessage)}
              disabled={disabled}
            />
          )}
          {errorMessage ? <p className="mt-1 text-xs text-rose-500">{errorMessage}</p> : null}
          {field.helper ? <p className="mt-1 text-xs text-muted">{field.helper}</p> : null}
        </div>
        )
      })}
      <div className="flex flex-wrap items-center gap-3">
        <Button onClick={() => void handleSubmit()} disabled={isSubmitting || isResolvingLookups}>
          {submitLabel}
        </Button>
        {assistantConfig ? (
          <AiSuggestionDialog
            triggerLabel="AI form draft"
            title={`Suggest ${assistantConfig.resourceLabel} form inputs`}
            description="Generate AI-reviewed draft values for the current form. Nothing is saved until you apply the draft and submit manually."
            action="suggest_form_inputs"
            contextType={`${assistantConfig.resourceType}_form`}
            promptLabel="Optional prompt"
            promptPlaceholder="Highlight what should be improved or completed"
            buildContext={buildAssistantContext}
            onApply={(response) => {
              const draftFields = response.structuredOutput.fields

              if (!isObjectRecord(draftFields)) {
                toast({
                  title: 'AI draft unavailable',
                  description: 'The AI preview did not return form fields that can be applied.',
                })
                return
              }

              setFormData((prev) => ({ ...prev, ...draftFields }))
              setFieldErrors({})
              toast({
                title: 'AI draft applied',
                description: 'Review the populated fields before saving.',
              })
            }}
            applyLabel="Apply draft to form"
          />
        ) : null}
        {footerActions}
      </div>
    </div>
  )
}
