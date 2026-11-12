import { describe, expect, it } from 'vitest'
import { analyzeFileStructure, getFileMetrics, generateFullImportReport } from './analysis.engine'

describe('CSV parsing via PapaParse', () => {
  it('analyzes CSV file structure correctly', async () => {
    // Create a mock CSV file
    const csvContent = `name,age,city
John,30,New York
Jane,25,Los Angeles
Bob,35,Chicago`
    
    const file = new File([csvContent], 'test.csv', { type: 'text/csv' })
    
    const report = await analyzeFileStructure(file)
    
    expect(report.headers).toEqual(['name', 'age', 'city'])
    expect(report.columnCount).toBe(3)
    expect(report.rowCount).toBe(3)
    expect(report.previewRows).toHaveLength(3)
    expect(report.detectedTypes).toBeDefined()
    expect(report.missingValueStats).toBeDefined()
  })

  it('handles CSV files with missing values', async () => {
    const csvContent = `name,age,city
John,30,New York
Jane,,Los Angeles
Bob,35,`
    
    const file = new File([csvContent], 'test.csv', { type: 'text/csv' })
    
    const report = await analyzeFileStructure(file)
    
    expect(report.missingValueStats['age']).toBe(1)
    expect(report.missingValueStats['city']).toBe(1)
    expect(report.missingValueStats['name']).toBe(0)
  })

  it('detects data types correctly in CSV', async () => {
    const csvContent = `name,age,active
John,30,true
Jane,25,false`
    
    const file = new File([csvContent], 'test.csv', { type: 'text/csv' })
    
    const report = await analyzeFileStructure(file)
    
    expect(report.detectedTypes['name']).toBe('string')
    expect(report.detectedTypes['age']).toBe('string') // PapaParse returns strings by default
  })

  it('gets accurate row count for CSV files', async () => {
    const csvContent = `col1,col2,col3
${'a,b,c\n'.repeat(100)}`
    
    const file = new File([csvContent], 'large.csv', { type: 'text/csv' })
    
    const metrics = await getFileMetrics(file)
    
    expect(metrics.rowCount).toBe(100)
  })

  it('generates full import report for CSV', async () => {
    const csvContent = `name,value
Item1,100
Item2,200
Item3,300`
    
    const file = new File([csvContent], 'test.csv', { type: 'text/csv' })
    
    const report = await generateFullImportReport(file)
    
    expect(report.rowCount).toBe(3)
    expect(report.headers).toEqual(['name', 'value'])
    expect(report.previewRows).toHaveLength(3)
  })

  it('throws error when trying to get metrics for Excel files', async () => {
    const file = new File(['fake excel content'], 'test.xlsx', { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
    
    await expect(getFileMetrics(file)).rejects.toThrow('getFileMetrics does not support Excel files')
  })

  it('handles empty CSV columns with default string type', async () => {
    const csvContent = `col1,col2,col3
,,
,,`
    
    const file = new File([csvContent], 'empty.csv', { type: 'text/csv' })
    
    const report = await analyzeFileStructure(file)
    
    // When all values are empty, it should default to 'string'
    expect(report.detectedTypes['col1']).toBe('string')
    expect(report.detectedTypes['col2']).toBe('string')
    expect(report.detectedTypes['col3']).toBe('string')
  })
})
