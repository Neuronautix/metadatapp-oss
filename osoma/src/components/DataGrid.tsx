import type React from 'react'
import { useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button.tsx'
import { Card } from '@/components/ui/card.tsx'
import { Table, TableBody, TableHeader } from '@/components/ui/table.tsx'
import { Skeleton } from '@/components/ui/skeleton.tsx'
import { EmptyState } from '@/components/EmptyState.tsx'
import { PageHeader } from '@/components/PageHeader.tsx'
import type { PaginatedResponse } from '@/lib/types.ts'

export type DataGridProps<TData, TFilters> = {
    title: string
    subtitle?: string
    queryKey: unknown[]
    queryFn: (filters: TFilters & { page: number }) => Promise<PaginatedResponse<TData>>
    initialFilters: TFilters
    renderFilters?: (filters: TFilters, setFilters: (newFilters: TFilters) => void, reset: () => void) => React.ReactNode
    renderHeader: () => React.ReactNode
    renderRow: (item: TData) => React.ReactNode
    emptyState: { title: string; description: string }
    actions?: React.ReactNode
}

export function DataGrid<TData, TFilters>({
    title,
    subtitle,
    queryKey,
    queryFn,
    initialFilters,
    renderFilters,
    renderHeader,
    renderRow,
    emptyState,
    actions,
}: DataGridProps<TData, TFilters>) {
    const [page, setPage] = useState(1)
    const [filters, setFilters] = useState<TFilters>(initialFilters)
    const queryClient = useQueryClient()

    const activeFilters = useMemo(() => ({ ...filters, page }), [filters, page])

    const { data, isLoading, isError, error } = useQuery({
        queryKey: [...queryKey, activeFilters],
        queryFn: () => queryFn(activeFilters),
    })

    // Avoid divide by zero if itemsPerPage is missing, assume 1 as fallback for totalPages calculation
    const totalPages = data ? Math.max(1, Math.ceil(data.total / (data.pageSize ?? 10))) : 1

    const handleReset = () => {
        setFilters(initialFilters)
        setPage(1)
    }

    const handleFilterChange = (newFilters: TFilters) => {
        setFilters(newFilters)
        setPage(1) // Reset to page 1 on filter change
    }

    return (
        <div className="space-y-6">
            <PageHeader title={title} subtitle={subtitle} actions={actions} />

            <Card className="space-y-6">
                {renderFilters ? renderFilters(filters, handleFilterChange, handleReset) : null}

                {isLoading ? (
                    <div className="space-y-3">
                        {Array.from({ length: 6 }).map((_, index) => (
                            <Skeleton key={index} className="h-12 w-full" />
                        ))}
                    </div>
                ) : isError ? (
                    <EmptyState
                        title={`${title} unavailable`}
                        description={(error as Error).message}
                        actionLabel="Retry"
                        onAction={() => queryClient.invalidateQueries({ queryKey })}
                    />
                ) : data && data.data.length === 0 ? (
                    <EmptyState title={emptyState.title} description={emptyState.description} />
                ) : (
                    <Table>
                        <TableHeader>{renderHeader()}</TableHeader>
                        <TableBody>{data?.data.map((item) => renderRow(item))}</TableBody>
                    </Table>
                )}

                {data && data.total > 0 ? (
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                        <span>
                            Page {page} of {totalPages}
                        </span>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                                disabled={page === 1}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setPage((prev) => Math.min(totalPages, prev + 1))}
                                disabled={page === totalPages}
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                ) : null}
            </Card>
        </div>
    )
}
