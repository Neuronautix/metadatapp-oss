import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { DVCExtractionJobsPanel } from './DVCExtractionJobsPanel.tsx'

const { getTaskStateMock } = vi.hoisted(() => ({
  getTaskStateMock: vi.fn(),
}))

vi.mock('./dvc.integration.api.ts', async () => {
  const actual = await vi.importActual<typeof import('./dvc.integration.api.ts')>('./dvc.integration.api.ts')
  return {
    ...actual,
    getTaskState: getTaskStateMock,
  }
})

vi.mock('./dvc.task-results.ts', () => ({
  downloadTaskZip: vi.fn(),
  extractTaskForVisualization: vi.fn(),
}))

const initialJob = {
  appId: 'app-123',
  id: 26052,
  state: 'SUBMITTED' as const,
  createdAt: '2026-03-13T09:00:00Z',
  lastCheckedAt: '2026-03-13T09:00:00Z',
  aggrType: 'HOUR' as const,
  metrics: ['ACTIVATION'],
  outputFilename: 'dvc-export',
  partitionType: 'SINGLE_FILE_BY_CAGE' as const,
  resourceType: 'CAGE' as const,
  selectedIds: ['S81P-40648'],
  targetLabel: 'S81P-40648',
  requestedStart: '2026-02-18T00:00:00Z',
  requestedStop: '2026-02-19T00:00:00Z',
}

describe('DVCExtractionJobsPanel', () => {
  beforeEach(() => {
    localStorage.setItem('dvc-extraction-jobs', JSON.stringify([initialJob]))
    getTaskStateMock.mockReset()
  })

  it('persists completed status updates and enables plot/export actions', async () => {
    getTaskStateMock.mockResolvedValue([
      {
        id: 26052,
        state: 'COMPLETED',
        createdAt: '2026-03-13T09:00:00Z',
        finishedAt: '2026-03-13T09:05:00Z',
        fileSize: 4096,
      },
    ])

    render(<DVCExtractionJobsPanel appId="app-123" onDataExtracted={vi.fn()} />)

    fireEvent.click(screen.getByRole('button', { name: /Refresh Status/i }))

    await waitFor(() => {
      expect(screen.getByText('COMPLETED')).toBeInTheDocument()
    })

    expect(screen.getByRole('button', { name: /Export ZIP/i })).toBeEnabled()
    expect(screen.getByRole('button', { name: /See Plot/i })).toBeEnabled()

    const persisted = JSON.parse(localStorage.getItem('dvc-extraction-jobs') || '[]')
    expect(persisted[0]).toMatchObject({
      id: 26052,
      state: 'COMPLETED',
      fileSize: 4096,
    })
  })
})
