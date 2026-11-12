import JSZip from 'jszip'
import { dvcWorker } from './dvcWorkerClient.ts'
import { downloadTaskResult } from './dvc.integration.api.ts'

const isPreferredVisualizationCsv = (filename: string, header: string) => {
  const lowerName = filename.toLowerCase()
  const lowerHeader = header.toLowerCase()

  if (lowerName.includes('event')) {
    return false
  }

  if (lowerName.includes('locomotion') || lowerName.includes('activation') || lowerName.includes('activity')) {
    return true
  }

  return (
    lowerHeader.includes('timestamp') &&
    lowerHeader.includes('cage') &&
    (
      lowerHeader.includes('v_1') ||
      lowerHeader.includes('global_activity') ||
      lowerHeader.includes('metric,value')
    )
  )
}

const pickVisualizationCsv = async (zip: JSZip) => {
  const csvFiles = Object.values(zip.files).filter((file) => file.name.toLowerCase().endsWith('.csv'))
  if (!csvFiles.length) {
    throw new Error('No CSV files found in the extracted archive.')
  }

  for (const csvFile of csvFiles) {
    const text = await csvFile.async('text')
    const header = text.split(/\r?\n/, 1)[0] ?? ''
    if (isPreferredVisualizationCsv(csvFile.name, header)) {
      return {
        csvFile,
        content: text,
      }
    }
  }

  const fallback = csvFiles.find((file) => !file.name.toLowerCase().includes('event')) ?? csvFiles[0]
  return {
    csvFile: fallback,
    content: await fallback.async('text'),
  }
}

export const downloadTaskZip = async (appId: string, taskId: number, filename: string) => {
  const blob = await downloadTaskResult(appId, taskId)
  const downloadUrl = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = downloadUrl
  anchor.download = `${filename}-${taskId}.zip`
  document.body.appendChild(anchor)
  anchor.click()
  document.body.removeChild(anchor)
  window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000)
}

export const extractTaskForVisualization = async (
  appId: string,
  taskId: number,
  onDataExtracted: (metrics: string[], cages: string[], rowCount: number) => void,
) => {
  const blob = await downloadTaskResult(appId, taskId)
  const zip = await JSZip.loadAsync(blob)
  const { csvFile: selectedCsv, content } = await pickVisualizationCsv(zip)
  const csvFile = new File([content], selectedCsv.name, { type: 'text/csv' })
  const stats = await dvcWorker.parseFile(csvFile)

  onDataExtracted(stats.metrics, stats.cages, stats.rowCount)
}
