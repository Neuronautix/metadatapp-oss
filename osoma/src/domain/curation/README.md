# Curation Data Import Analysis

This module provides file analysis capabilities for the curation workflow, supporting both CSV and Excel files.

## Architecture

The implementation uses a **hybrid approach** that optimizes for client-side performance while handling format limitations:

### CSV Files (.csv)
- **Parsed client-side** using [PapaParse](https://www.papaparse.com/)
- Zero server round-trips for analysis
- Efficient for large files through streaming API
- Type detection and validation performed in the browser

### Excel Files (.xlsx, .xls, .ods)
- **Parsed server-side** using PHPOffice/PhpSpreadsheet
- Single API call to `POST /api/excel/analyze`
- Required because PapaParse only supports CSV format
- Returns the same analysis report structure as CSV parsing

## API

### `analyzeFileStructure(file: File): Promise<DataImportReport>`

Analyzes a file and returns a report with:
- Row and column counts
- Column headers
- Detected data types per column
- Missing value statistics per column
- Preview of first 10 rows

**Implementation:**
- CSV files: Uses PapaParse with a preview of 100 rows for analysis
- Excel files: Delegates to server endpoint `/excel/analyze`

### `getFileMetrics(file: File): Promise<{ rowCount: number }>`

Gets accurate row count for large CSV files using a lightweight streaming pass.
- **Only supports CSV files**
- Throws error for Excel files (use `generateFullImportReport` instead)

### `generateFullImportReport(file: File): Promise<DataImportReport>`

Generates a complete analysis report with accurate row counts.
- For CSV: Combines `getFileMetrics()` + `analyzeFileStructure()`
- For Excel: Single server round-trip returns complete report

## Data Types

### DataImportReport
```typescript
{
  rowCount: number           // Total rows (excluding header)
  columnCount: number        // Number of columns
  headers: string[]          // Column names
  detectedTypes: Record<string, string>  // Type per column
  missingValueStats: Record<string, number>  // Missing count per column
  previewRows: Record<string, any>[]  // First 10 data rows
}
```

## Testing

Run tests with:
```bash
npm run test:integration -- analysis.engine.test.ts
```

Tests cover:
- CSV parsing via PapaParse
- Type detection
- Missing value statistics
- Row counting
- Excel file routing (delegates to server)

## Performance Considerations

- CSV files are analyzed entirely client-side, reducing server load
- Large CSV files use streaming API for row counting
- Excel files require one server round-trip but return complete analysis
- Preview limited to 10 rows to minimize memory usage
- Analysis samples first 100 rows for type detection

## Backend Implementation

The Excel analysis endpoint is implemented in:
- `api/src/Controller/Api/ExcelAnalysisController.php`
- Tests: `api/tests/Unit/Controller/Api/ExcelAnalysisControllerTest.php`

Requires `ROLE_USER` authorization.
