import { useCallback, useEffect, useRef, useState } from 'react'
import { Badge } from '@/components/ui/badge.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table.tsx'
import { Download, FileText, RefreshCw } from 'lucide-react'
import {
  DVC_EXTRACTION_JOBS_UPDATED_EVENT,
  type DvcExtractionJob,
  getDvcExtractionJobsForApp,
  loadDvcExtractionJobs,
  saveDvcExtractionJobs,
} from './dvc.job-history.ts'
import { getTaskState, TaskStateEnum } from './dvc.integration.api.ts'
import { downloadTaskZip, extractTaskForVisualization } from './dvc.task-results.ts'
import { findTaskStateById } from './dvc.task-state.ts'

interface DVCExtractionJobsPanelProps {
  appId: string
  onDataExtracted: (metrics: string[], cages: string[], rowCount: number) => void
}

const formatWindowLabel = (start: string, stop: string) =>
  `${new Date(start).toLocaleDateString()} -> ${new Date(stop).toLocaleDateString()}`

export function DVCExtractionJobsPanel({ appId, onDataExtracted }: DVCExtractionJobsPanelProps) {
  const [jobs, setJobs] = useState<DvcExtractionJob[]>(() => getDvcExtractionJobsForApp(appId))
  const [isRefreshing, setIsRefreshing] = useState(false)
  const [isPiping, setIsPiping] = useState<number | null>(null)
  const [isDownloadingTask, setIsDownloadingTask] = useState<number | null>(null)
  const refreshInFlightRef = useRef(false)

  const refreshJobsFromStorage = useCallback(() => {
    setJobs(getDvcExtractionJobsForApp(appId))
  }, [appId])

  const persistJobs = useCallback((nextJobs: DvcExtractionJob[]) => {
    setJobs(nextJobs)
    const otherAppJobs = loadDvcExtractionJobs().filter((job) => job.appId !== appId)
    saveDvcExtractionJobs([...nextJobs, ...otherAppJobs])
  }, [appId])

  const refreshStatuses = useCallback(async (sourceJobs?: DvcExtractionJob[]) => {
    if (refreshInFlightRef.current) {
      return
    }

    const latestJobs = [...(sourceJobs ?? getDvcExtractionJobsForApp(appId))].sort((left, right) => {
      return new Date(right.createdAt).getTime() - new Date(left.createdAt).getTime()
    })

    if (!latestJobs.length) {
      setJobs([])
      return
    }

    setIsRefreshing(true)
    refreshInFlightRef.current = true
    try {
      const updates = await Promise.all(latestJobs.map(async (job) => {
        const checkedAt = new Date().toISOString()
        if (job.state !== TaskStateEnum.SUBMITTED && job.state !== TaskStateEnum.RUNNING) {
          return { ...job, lastCheckedAt: checkedAt }
        }

        try {
          const states = await getTaskState(job.appId, job.id)
          if (!states.length) {
            return { ...job, lastCheckedAt: checkedAt }
          }

          const nextState = findTaskStateById(states, job.id)

          return nextState
            ? { ...job, ...nextState, lastCheckedAt: checkedAt }
            : { ...job, lastCheckedAt: checkedAt }
        } catch {
          return { ...job, lastCheckedAt: checkedAt }
        }
      }))

      persistJobs(updates)
    } finally {
      refreshInFlightRef.current = false
      setIsRefreshing(false)
    }
  }, [appId, persistJobs])

  useEffect(() => {
    refreshJobsFromStorage()

    const handleJobsUpdated = () => {
      refreshJobsFromStorage()
    }

    window.addEventListener(DVC_EXTRACTION_JOBS_UPDATED_EVENT, handleJobsUpdated)
    window.addEventListener('storage', handleJobsUpdated)

    return () => {
      window.removeEventListener(DVC_EXTRACTION_JOBS_UPDATED_EVENT, handleJobsUpdated)
      window.removeEventListener('storage', handleJobsUpdated)
    }
  }, [refreshJobsFromStorage])

  useEffect(() => {
    if (!jobs.length) {
      return
    }

    const activeJobs = jobs.filter((job) =>
      typeof job.id === 'number' &&
      Number.isFinite(job.id) &&
      (job.state === TaskStateEnum.SUBMITTED || job.state === TaskStateEnum.RUNNING)
    )
    if (!activeJobs.length) {
      return
    }

    const interval = window.setInterval(() => {
      void refreshStatuses(activeJobs)
    }, 5000)

    return () => window.clearInterval(interval)
  }, [jobs, refreshStatuses])

  const handlePlot = async (job: DvcExtractionJob) => {
    setIsPiping(job.id)
    try {
      await extractTaskForVisualization(job.appId, job.id, onDataExtracted)
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unknown extraction error'
      alert(`Failed to parse the selected extraction job: ${message}`)
    } finally {
      setIsPiping(null)
    }
  }

  const handleZipDownload = async (job: DvcExtractionJob) => {
    setIsDownloadingTask(job.id)
    try {
      await downloadTaskZip(job.appId, job.id, job.outputFilename)
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unknown download error'
      alert(`Failed to download the extraction ZIP: ${message}`)
    } finally {
      setIsDownloadingTask(null)
    }
  }

  if (!jobs.length) {
    return (
      <Card className="rounded-2xl border border-dashed">
        <CardHeader>
          <CardTitle>Extraction Jobs</CardTitle>
          <CardDescription>No saved DVC extraction jobs yet. Run a live extraction first.</CardDescription>
        </CardHeader>
      </Card>
    )
  }

  return (
    <Card className="rounded-2xl border border-line bg-surface shadow-[0_0_40px_-15px_rgba(0,0,0,0.05)]">
      <CardHeader className="flex flex-row items-start justify-between gap-4">
        <div>
          <CardTitle>Extraction Jobs</CardTitle>
          <CardDescription>Saved DVC task history with live status refresh, plotting, and raw ZIP export.</CardDescription>
        </div>
        <Button variant="outline" onClick={() => void refreshStatuses()} disabled={isRefreshing}>
          <RefreshCw className={`mr-2 h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
          Refresh Status
        </Button>
      </CardHeader>
      <CardContent>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Task ID</TableHead>
              <TableHead>Scope</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Metrics</TableHead>
              <TableHead>Window</TableHead>
              <TableHead>Last Checked</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {jobs.map((job) => {
              const hasValidTaskId = typeof job.id === 'number' && Number.isFinite(job.id)
              const isDone = hasValidTaskId && job.state === TaskStateEnum.COMPLETED
              const isActive = hasValidTaskId && (job.state === TaskStateEnum.SUBMITTED || job.state === TaskStateEnum.RUNNING)

              return (
                <TableRow key={job.id}>
                  <TableCell className="font-medium">{hasValidTaskId ? `#${job.id}` : 'Invalid task'}</TableCell>
                  <TableCell className="text-sm text-slate-700">{job.targetLabel || job.selectedIds.join(', ')}</TableCell>
                  <TableCell>
                    <Badge
                      variant={isDone ? 'default' : (job.state === TaskStateEnum.FAILED ? 'critical' : 'outline')}
                      className={isActive ? 'animate-pulse' : ''}
                    >
                      {job.state}
                    </Badge>
                  </TableCell>
                  <TableCell className="max-w-[260px] truncate text-xs text-slate-600">{job.metrics.join(', ')}</TableCell>
                  <TableCell className="text-xs text-slate-600">
                    {job.requestedStart && job.requestedStop ? formatWindowLabel(job.requestedStart, job.requestedStop) : '-'}
                  </TableCell>
                  <TableCell className="text-xs text-slate-600">
                    {new Date(job.lastCheckedAt || job.finishedAt || job.createdAt).toLocaleString()}
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button
                        size="sm"
                        variant="outline"
                        disabled={!isDone || isDownloadingTask !== null}
                        onClick={() => void handleZipDownload(job)}
                      >
                        {isDownloadingTask === job.id ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}
                        Export ZIP
                      </Button>
                      <Button
                        size="sm"
                        disabled={!isDone || isPiping !== null || isDownloadingTask !== null}
                        onClick={() => void handlePlot(job)}
                        className="bg-emerald-600 hover:bg-emerald-700 text-white"
                      >
                        {isPiping === job.id ? <RefreshCw className="mr-2 h-4 w-4 animate-spin" /> : <FileText className="mr-2 h-4 w-4" />}
                        See Plot
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              )
            })}
          </TableBody>
        </Table>
      </CardContent>
    </Card>
  )
}
