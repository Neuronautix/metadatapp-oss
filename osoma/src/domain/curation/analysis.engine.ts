import Papa from 'papaparse'
import { apiFetch } from '@/lib/api'
import { DataImportReport } from './curation.types'

const EXCEL_EXTENSIONS = ['.xlsx', '.xls', '.ods']
type ParsedRow = Record<string, string>
type ParseResult = {
  report: DataImportReport
  rows: ParsedRow[]
}

const isExcelFile = (file: File): boolean => {
  const name = file.name.toLowerCase()
  return EXCEL_EXTENSIONS.some(ext => name.endsWith(ext))
}

const analyzeExcelOnServer = (file: File): Promise<DataImportReport> => {
  const formData = new FormData()
  formData.append('file', file)

  return apiFetch<DataImportReport>('/excel/analyze', {
    method: 'POST',
    body: formData,
  })
}

export const analyzeFileStructure = (file: File): Promise<DataImportReport> => {
  if (isExcelFile(file)) {
    return analyzeExcelOnServer(file)
  }

  return new Promise((resolve, reject) => {
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      preview: 100,
      complete: (results) => {
        const data = results.data as Record<string, any>[]
        const headers = results.meta.fields || []

        const report: DataImportReport = {
          rowCount: data.length,
          columnCount: headers.length,
          headers,
          detectedTypes: {},
          missingValueStats: {},
          previewRows: data.slice(0, 10),
        }

        headers.forEach(header => {
          let missingCount = 0
          const typesFound = new Set<string>()

          data.forEach(row => {
            const value = row[header]
            if (value === undefined || value === null || value === '') {
              missingCount++
            } else {
              typesFound.add(typeof value === 'string' && !isNaN(Date.parse(value)) ? 'date' : typeof value)
            }
          })

          report.missingValueStats[header] = missingCount
          report.detectedTypes[header] = typesFound.size === 0 ? 'string' : typesFound.size === 1 ? Array.from(typesFound)[0] : 'mixed'
        })

        resolve(report)
      },
      error: reject,
    })
  })
}

// ─── XLSX ────────────────────────────────────────────────────────────────────

/**
 * For large CSV files: get accurate total row count with a lightweight pass.
 * Excel files are handled server-side and return the accurate count directly.
 */
export const getFileMetrics = async (file: File): Promise<{ rowCount: number }> => {
  if (isExcelFile(file)) {
    throw new Error('getFileMetrics does not support Excel files. Use generateFullImportReport instead, which delegates Excel analysis to the server.')
  }

  return new Promise((resolve) => {
    let count = 0
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      step: () => {
        count++
      },
      complete: () => {
        resolve({ rowCount: count })
      }
    })
  })
}

// ─── Shared helpers ──────────────────────────────────────────────────────────

const detectType = (value: string): string => {
  const normalized = value.trim()
  if (normalized === '') return 'string'
  if (!isNaN(Number(normalized))) return 'number'
  // Only flag as date if it is not numeric and matches a date-like pattern
  if (/^\d{4}-\d{2}-\d{2}/.test(normalized) && !isNaN(Date.parse(normalized))) return 'date'
  return 'string'
}

const buildReport = (headers: string[], rows: ParsedRow[]): DataImportReport => {
  const detectedTypes: Record<string, string> = {}
  const missingValueStats: Record<string, number> = {}

  headers.forEach((header) => {
    let missingCount = 0
    const typesFound = new Set<string>()

    rows.forEach((row) => {
      const value = row[header]
      const normalized = (value ?? '').trim()
      if (normalized === '') {
        missingCount++
      } else {
        typesFound.add(detectType(normalized))
      }
    })

    missingValueStats[header] = missingCount
    detectedTypes[header] = typesFound.size === 0 ? 'string' : typesFound.size === 1 ? Array.from(typesFound)[0] : 'mixed'
  })

  return {
    rowCount: rows.length,
    columnCount: headers.length,
    headers,
    detectedTypes,
    missingValueStats,
    previewRows: rows.slice(0, 10),
  }
}

const parseCsvFile = (file: File): Promise<ParseResult> => {
  return new Promise((resolve, reject) => {
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      complete: (results) => {
        const rows = (results.data as ParsedRow[]).map((row) =>
          Object.fromEntries(
            Object.entries(row).map(([key, value]) => [key, value ?? ''])
          )
        )
        const headers = results.meta.fields ?? []

        resolve({
          report: buildReport(headers, rows),
          rows,
        })
      },
      error: reject,
    })
  })
}

const parseXlsxFile = async (file: File): Promise<ParseResult> => {
  const report = await analyzeExcelOnServer(file)

  return {
    report,
    rows: report.previewRows.map((row) =>
      Object.fromEntries(
        Object.entries(row).map(([key, value]) => [key, value == null ? '' : String(value)])
      )
    ),
  }
}

// ─── Public API ───────────────────────────────────────────────────────────────

export const parseFile = (file: File): Promise<ParseResult> => {
  if (file.name.toLowerCase().endsWith('.xlsx')) {
    return parseXlsxFile(file)
  }
  return parseCsvFile(file)
}

/** @deprecated Use parseFile() which also returns the full rows array. */
export const generateFullImportReport = async (file: File): Promise<DataImportReport> => {
  if (isExcelFile(file)) {
    // Server returns a complete, accurate report in one round-trip
    return analyzeExcelOnServer(file)
  }

  const metrics = await getFileMetrics(file)
  const analysisReport = await analyzeFileStructure(file)
  return {
    ...analysisReport,
    rowCount: metrics.rowCount
  }
}
