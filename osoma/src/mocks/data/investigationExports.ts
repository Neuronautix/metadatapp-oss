const hoursAgo = (hours: number) => new Date(Date.now() - hours * 3600000).toISOString()

export const investigationExports: Record<string, { lastExportAt: string; rowCount: number }> = {
  'PRJ-0007': { lastExportAt: hoursAgo(30), rowCount: 4 },
  'PRJ-0012': { lastExportAt: hoursAgo(18), rowCount: 2 },
}
