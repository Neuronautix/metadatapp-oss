const REQUIRED_HEADERS = ['name', 'cage']
const SUPPORTED_FIELDS = ['name', 'cage', 'strain', 'sex', 'birthDate', 'earTag', 'genotype', 'status']

export interface ValidationResult {
    valid: boolean
    errors: string[]
    warnings: string[]
    rowCount: number
}

export function validateCsv(headers: string[], rows: Record<string, string>[]): ValidationResult {
    const errors: string[] = []
    const warnings: string[] = []
    const lowerHeaders = headers.map((h) => h.toLowerCase().trim())

    for (const required of REQUIRED_HEADERS) {
        if (!lowerHeaders.includes(required)) {
            errors.push(`Missing required column: "${required}"`)
        }
    }

    if (rows.length === 0) {
        errors.push('No data rows found in CSV.')
    }

    const unknownHeaders = lowerHeaders.filter(
        (h) => !SUPPORTED_FIELDS.includes(h) && h !== ''
    )
    if (unknownHeaders.length > 0) {
        warnings.push(`Unknown columns will be ignored: ${unknownHeaders.join(', ')}`)
    }

    return {
        valid: errors.length === 0,
        errors,
        warnings,
        rowCount: rows.length,
    }
}
