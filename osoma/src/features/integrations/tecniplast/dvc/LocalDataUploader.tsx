import { useState, useCallback } from 'react'
import { UploadCloud, Loader2 } from 'lucide-react'
import { dvcWorker } from './dvcWorkerClient.ts'

interface LocalDataUploaderProps {
    onDataLoaded: (metrics: string[], cages: string[], rowCount: number) => void
}

export function LocalDataUploader({ onDataLoaded }: LocalDataUploaderProps) {
    const [isDragging, setIsDragging] = useState(false)
    const [isProcessing, setIsProcessing] = useState(false)
    const [error, setError] = useState<string | null>(null)

    const processFile = async (file: File) => {
        setIsProcessing(true)
        setError(null)

        try {
            const stats = await dvcWorker.parseFile(file)
            onDataLoaded(stats.metrics, stats.cages, stats.rowCount)
        } catch (err: any) {
            setError(err.message || 'Failed to parse CSV.')
        } finally {
            setIsProcessing(false)
        }
    }

    const onDrop = useCallback((e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault()
        setIsDragging(false)
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            const file = e.dataTransfer.files[0]
            if (file.type === 'text/csv' || file.name.endsWith('.csv')) {
                processFile(file)
            } else {
                setError('Please upload a valid CSV file.')
            }
        }
    }, [])

    return (
        <div
            className={`relative rounded-2xl border-2 border-dashed p-10 text-center transition-colors ${isDragging ? 'border-indigo-500 bg-indigo-50' : 'border-slate-300 bg-slate-50 hover:bg-slate-100 hover:border-slate-400'
                }`}
            onDragOver={(e) => { e.preventDefault(); setIsDragging(true); }}
            onDragLeave={() => setIsDragging(false)}
            onDrop={onDrop}
        >
            <input
                type="file"
                accept=".csv"
                className="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                onChange={(e) => {
                    if (e.target.files && e.target.files.length > 0) {
                        processFile(e.target.files[0])
                    }
                }}
                disabled={isProcessing}
            />

            {isProcessing ? (
                <div className="flex flex-col items-center justify-center space-y-4 relative z-0">
                    <Loader2 className="w-10 h-10 text-indigo-500 animate-spin" />
                    <p className="text-sm font-medium text-slate-600">Processing large CSV file...</p>
                </div>
            ) : (
                <div className="flex flex-col items-center justify-center space-y-4 pointer-events-none relative z-0">
                    <div className="p-4 bg-white rounded-full shadow-sm">
                        <UploadCloud className="w-8 h-8 text-indigo-500" />
                    </div>
                    <div>
                        <p className="text-sm font-bold text-slate-700">Drop your DVC CSV file here, or click to browse</p>
                        <p className="text-xs text-slate-500 mt-1">Supports DVC Analytics Export formats (must contain timestamp & cage columns)</p>
                    </div>
                    {error && (
                        <div className="p-3 mt-4 bg-red-50 text-red-600 rounded-lg text-sm max-w-md pointer-events-auto relative z-20">
                            {error}
                        </div>
                    )}
                </div>
            )}
        </div>
    )
}
