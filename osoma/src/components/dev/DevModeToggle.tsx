import { useState, useEffect } from 'react'
import { Database, HardDrive, RefreshCcw } from 'lucide-react'
import { getDataMode, setDataMode, type DataMode } from '@/lib/mode.ts'

export function DevModeToggle() {
    const [dataMode, setDataModeState] = useState<DataMode>(() => getDataMode())
    const [isVisible, setIsVisible] = useState(false)

    // Show toggle only in development
    useEffect(() => {
        if (import.meta.env.DEV) {
            setIsVisible(true)
        }
    }, [])

    const toggleMode = () => {
        const newMode: DataMode = dataMode === 'mock' ? 'real' : 'mock'
        setDataMode(newMode)
        // Backwards compatibility for any legacy checks
        localStorage.setItem('use_msw', String(newMode === 'mock'))
        setDataModeState(newMode)
        // Refresh to re-initialize MSW
        window.location.reload()
    }

    if (!isVisible) return null

    return (
        <div className="fixed bottom-4 left-4 z-[9999] flex items-center gap-2 rounded-full bg-slate-900 p-1.5 shadow-xl border border-slate-700">
            <button
                onClick={toggleMode}
                className={`flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium transition-all ${dataMode === 'mock'
                    ? 'bg-blue-600 text-white'
                    : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                    }`}
                title={dataMode === 'mock' ? 'Switch to API Mode' : 'Switch to Mock Mode'}
            >
                {dataMode === 'mock' ? <HardDrive size={14} /> : <Database size={14} />}
                <span>{dataMode === 'mock' ? 'Mock Data' : 'API Data'}</span>
            </button>
            <button
                onClick={() => window.location.reload()}
                className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-full transition-colors"
                title="Refresh Page"
            >
                <RefreshCcw size={14} />
            </button>
        </div>
    )
}
