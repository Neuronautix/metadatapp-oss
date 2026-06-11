const sanitize = (value: string) => value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')

const timestamp = () => {
  const now = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}-${pad(now.getHours())}${pad(now.getMinutes())}`
}

const downloadFile = (content: string, filename: string, type: string) => {
  const blob = new Blob([content], { type })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}

const escapeCsv = (value: string) => {
  if (value.includes('"') || value.includes(',') || value.includes('\n')) {
    return `"${value.replace(/"/g, '""')}"`
  }
  return value
}

export const toCsv = <T extends Record<string, unknown>>(rows: T[]) => {
  if (!rows.length) return ''
  const headers = Object.keys(rows[0])
  const lines = [headers.join(',')]

  rows.forEach((row) => {
    const values = headers.map((key) => {
      const value = row[key]
      if (value === null || value === undefined) return ''
      if (typeof value === 'object') return escapeCsv(JSON.stringify(value))
      return escapeCsv(String(value))
    })
    lines.push(values.join(','))
  })

  return lines.join('\n')
}

export const exportCsv = <T extends Record<string, unknown>>(rows: T[], name: string) => {
  const csv = toCsv(rows)
  const fileName = `${sanitize(name)}-${timestamp()}.csv`
  downloadFile(csv, fileName, 'text/csv')
}

export const exportJson = (data: unknown, name: string) => {
  const json = JSON.stringify(data, null, 2)
  const fileName = `${sanitize(name)}-${timestamp()}.json`
  downloadFile(json, fileName, 'application/json')
}
