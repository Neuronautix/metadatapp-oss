# CSV Client-Side Parsing Example

This example demonstrates how CSV files are parsed client-side using PapaParse.

## Usage Example

```typescript
import { analyzeFileStructure, generateFullImportReport } from '@/domain/curation/analysis.engine'

// Example 1: Quick analysis (uses first 100 rows)
const quickAnalysis = async (file: File) => {
  const report = await analyzeFileStructure(file)
  
  console.log('Headers:', report.headers)
  console.log('Row count (from sample):', report.rowCount)
  console.log('Column types:', report.detectedTypes)
  console.log('Missing values:', report.missingValueStats)
  console.log('Preview:', report.previewRows)
}

// Example 2: Full analysis with accurate row count
const fullAnalysis = async (file: File) => {
  const report = await generateFullImportReport(file)
  
  console.log('Total rows:', report.rowCount)  // Accurate count for CSV
  console.log('All data:', report)
}

// Example 3: File upload handler
const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
  const file = event.target.files?.[0]
  if (!file) return
  
  try {
    const report = await generateFullImportReport(file)
    
    // CSV files: parsed client-side with PapaParse
    // Excel files: sent to server at /api/excel/analyze
    
    console.log(`Analyzed ${file.name}`)
    console.log(`Found ${report.rowCount} rows, ${report.columnCount} columns`)
  } catch (error) {
    console.error('Failed to analyze file:', error)
  }
}
```

## Sample CSV File

```csv
name,age,city,salary
Alice,30,New York,75000
Bob,25,Los Angeles,65000
Charlie,35,Chicago,80000
Diana,,Boston,
Eve,28,,72000
```

## Expected Output

```json
{
  "rowCount": 5,
  "columnCount": 4,
  "headers": ["name", "age", "city", "salary"],
  "detectedTypes": {
    "name": "string",
    "age": "string",
    "city": "string",
    "salary": "string"
  },
  "missingValueStats": {
    "name": 0,
    "age": 1,
    "city": 1,
    "salary": 1
  },
  "previewRows": [
    {"name": "Alice", "age": "30", "city": "New York", "salary": "75000"},
    {"name": "Bob", "age": "25", "city": "Los Angeles", "salary": "65000"},
    {"name": "Charlie", "age": "35", "city": "Chicago", "salary": "80000"},
    {"name": "Diana", "age": "", "city": "Boston", "salary": ""},
    {"name": "Eve", "age": "28", "city": "", "salary": "72000"}
  ]
}
```

## Performance Characteristics

### CSV Files (Client-side with PapaParse)
- ✅ Zero server round-trips
- ✅ Immediate feedback to users
- ✅ Reduced server load
- ✅ Works offline
- ✅ Streaming support for large files

### Excel Files (Server-side)
- ⚠️ One server round-trip required
- ⚠️ PapaParse doesn't support Excel formats
- ✅ Same API and response structure
- ✅ Handles complex Excel features (formulas, formatting)

## Integration Example

```tsx
import { useState } from 'react'
import { analyzeFileStructure } from '@/domain/curation/analysis.engine'
import type { DataImportReport } from '@/domain/curation/curation.types'

function FileAnalyzer() {
  const [report, setReport] = useState<DataImportReport | null>(null)
  const [loading, setLoading] = useState(false)
  
  const handleFile = async (file: File) => {
    setLoading(true)
    try {
      const result = await analyzeFileStructure(file)
      setReport(result)
    } catch (error) {
      console.error('Analysis failed:', error)
    } finally {
      setLoading(false)
    }
  }
  
  return (
    <div>
      <input 
        type="file" 
        accept=".csv,.xlsx,.xls,.ods"
        onChange={(e) => e.target.files?.[0] && handleFile(e.target.files[0])}
      />
      
      {loading && <p>Analyzing file...</p>}
      
      {report && (
        <div>
          <h3>Analysis Results</h3>
          <p>Rows: {report.rowCount}</p>
          <p>Columns: {report.columnCount}</p>
          <p>Headers: {report.headers.join(', ')}</p>
        </div>
      )}
    </div>
  )
}
```
