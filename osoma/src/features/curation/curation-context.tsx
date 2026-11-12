import { createContext, useContext, useState, type ReactNode } from 'react'
import type { DataImportReport, MappingDefinition, ValidationReport } from '@/domain/curation/curation.types'

type CurationState = {
  sessionId: string | null
  file: File | null
  report: DataImportReport | null
  parsedRows: Record<string, string>[]
  mappings: MappingDefinition[]
  validationReport: ValidationReport | null
}

type CurationContextValue = CurationState & {
  setSessionId: (id: string | null) => void
  setFile: (file: File) => void
  setReport: (report: DataImportReport) => void
  setParsedRows: (rows: Record<string, string>[]) => void
  setMappings: (mappings: MappingDefinition[]) => void
  setValidationReport: (validationReport: ValidationReport | null) => void
  reset: () => void
}

const CurationContext = createContext<CurationContextValue | null>(null)

const initialState: CurationState = {
  sessionId: null,
  file: null,
  report: null,
  parsedRows: [],
  mappings: [],
  validationReport: null,
}

export function CurationProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<CurationState>(initialState)

  const setSessionId = (sessionId: string | null) => setState((prev) => ({ ...prev, sessionId }))
  const setFile = (file: File) => setState((prev) => ({ ...prev, file }))
  const setReport = (report: DataImportReport) => setState((prev) => ({ ...prev, report }))
  const setParsedRows = (parsedRows: Record<string, string>[]) => setState((prev) => ({ ...prev, parsedRows }))
  const setMappings = (mappings: MappingDefinition[]) => setState((prev) => ({ ...prev, mappings }))
  const setValidationReport = (validationReport: ValidationReport | null) => setState((prev) => ({ ...prev, validationReport }))
  const reset = () => setState(initialState)

  return (
    <CurationContext.Provider value={{ ...state, setSessionId, setFile, setReport, setParsedRows, setMappings, setValidationReport, reset }}>
      {children}
    </CurationContext.Provider>
  )
}

export function useCuration(): CurationContextValue {
  const ctx = useContext(CurationContext)
  if (!ctx) {
    throw new Error('useCuration must be used within a CurationProvider')
  }
  return ctx
}
