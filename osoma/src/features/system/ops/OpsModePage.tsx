import { useMemo, useState } from 'react'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { getAuthMode, getDataMode, setAuthMode, setDataMode, type AuthMode, type DataMode } from '@/lib/mode.ts'

const OPS_UNLOCK_KEY = 'metadatapp_ops_unlocked'
const OPS_CODE = import.meta.env.VITE_OPS_CODE

const legacySetUseMsw = (mode: DataMode) => {
  try {
    localStorage.setItem('use_msw', String(mode === 'mock'))
  } catch {
    // Ignore storage errors
  }
}

export function OpsModePage() {
  const [dataMode, setDataModeState] = useState<DataMode>(() => getDataMode())
  const [authMode, setAuthModeState] = useState<AuthMode>(() => getAuthMode())
  const [codeInput, setCodeInput] = useState('')
  const [unlocked, setUnlocked] = useState(() => sessionStorage.getItem(OPS_UNLOCK_KEY) === 'true')

  const opsEnabled = useMemo(() => Boolean(OPS_CODE), [])

  const applyDataMode = (mode: DataMode) => {
    setDataMode(mode)
    legacySetUseMsw(mode)
    setDataModeState(mode)
    window.location.reload()
  }

  const applyAuthMode = (mode: AuthMode) => {
    setAuthMode(mode)
    setAuthModeState(mode)
    window.location.reload()
  }

  const tryUnlock = () => {
    if (!OPS_CODE) return
    if (codeInput.trim() === OPS_CODE) {
      sessionStorage.setItem(OPS_UNLOCK_KEY, 'true')
      setUnlocked(true)
      return
    }
  }

  if (!opsEnabled) {
    return (
      <div className="min-h-screen bg-slate-950 px-6 py-12 text-slate-100">
        <div className="mx-auto max-w-3xl rounded-3xl border border-slate-800 bg-slate-900 p-10">
          <h1 className="text-3xl font-display">Ops Mode</h1>
          <p className="mt-4 text-sm text-slate-300">
            Ops mode is disabled. Set <code className="rounded bg-slate-800 px-2 py-1">VITE_OPS_CODE</code> to enable.
          </p>
        </div>
      </div>
    )
  }

  if (!unlocked) {
    return (
      <div className="min-h-screen bg-slate-950 px-6 py-12 text-slate-100">
        <div className="mx-auto max-w-3xl rounded-3xl border border-slate-800 bg-slate-900 p-10">
          <h1 className="text-3xl font-display">Ops Mode</h1>
          <p className="mt-3 text-sm text-slate-300">
            Enter the ops code to unlock environment toggles.
          </p>
          <div className="mt-6 flex flex-col gap-3 sm:flex-row">
            <input
              className="w-full rounded-full border border-slate-700 bg-slate-950 px-4 py-2 text-sm text-slate-100"
              placeholder="Ops code"
              type="password"
              value={codeInput}
              onChange={(event) => setCodeInput(event.target.value)}
            />
            <Button onClick={tryUnlock} className="rounded-full">
              Unlock
            </Button>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-slate-950 px-6 py-12 text-slate-100">
      <div className="mx-auto max-w-4xl rounded-3xl border border-slate-800 bg-slate-900 p-10">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h1 className="text-3xl font-display">Ops Mode</h1>
          <Badge variant="outline" className="border-slate-700 text-slate-200">
            Hidden Control Panel
          </Badge>
        </div>

        <div className="mt-10">
          <h2 className="text-lg font-semibold">Data Source</h2>
          <p className="mt-2 text-sm text-slate-300">
            Switch between mock data and the real API. The app will reload on change.
          </p>
          <div className="mt-5 grid gap-4 sm:grid-cols-2">
            <button
              className={`rounded-3xl border px-6 py-6 text-left transition ${dataMode === 'mock'
                ? 'border-blue-500 bg-blue-500/10 text-blue-100'
                : 'border-slate-800 bg-slate-950 text-slate-200 hover:border-slate-700'
                }`}
              onClick={() => applyDataMode('mock')}
            >
              <div className="text-sm uppercase tracking-[0.2em] text-slate-400">Mock</div>
              <div className="mt-2 text-2xl font-semibold">Demo Data</div>
              <div className="mt-2 text-xs text-slate-400">
                Uses MSW in the browser.
              </div>
            </button>
            <button
              className={`rounded-3xl border px-6 py-6 text-left transition ${dataMode === 'real'
                ? 'border-emerald-500 bg-emerald-500/10 text-emerald-100'
                : 'border-slate-800 bg-slate-950 text-slate-200 hover:border-slate-700'
                }`}
              onClick={() => applyDataMode('real')}
            >
              <div className="text-sm uppercase tracking-[0.2em] text-slate-400">Real</div>
              <div className="mt-2 text-2xl font-semibold">API Backend</div>
              <div className="mt-2 text-xs text-slate-400">
                Calls live endpoints.
              </div>
            </button>
          </div>
        </div>

        <div className="mt-12">
          <h2 className="text-lg font-semibold">Auth Mode</h2>
          <p className="mt-2 text-sm text-slate-300">
            Keep Keycloak login for realism, or bypass for quick demo resets.
          </p>
          <div className="mt-5 grid gap-4 sm:grid-cols-2">
            <button
              className={`rounded-3xl border px-6 py-6 text-left transition ${authMode === 'real'
                ? 'border-purple-500 bg-purple-500/10 text-purple-100'
                : 'border-slate-800 bg-slate-950 text-slate-200 hover:border-slate-700'
                }`}
              onClick={() => applyAuthMode('real')}
            >
              <div className="text-sm uppercase tracking-[0.2em] text-slate-400">Auth</div>
              <div className="mt-2 text-2xl font-semibold">Keycloak Login</div>
              <div className="mt-2 text-xs text-slate-400">
                Real authentication flow.
              </div>
            </button>
            <button
              className={`rounded-3xl border px-6 py-6 text-left transition ${authMode === 'bypass'
                ? 'border-orange-400 bg-orange-500/10 text-orange-100'
                : 'border-slate-800 bg-slate-950 text-slate-200 hover:border-slate-700'
                }`}
              onClick={() => applyAuthMode('bypass')}
            >
              <div className="text-sm uppercase tracking-[0.2em] text-slate-400">Bypass</div>
              <div className="mt-2 text-2xl font-semibold">Instant Session</div>
              <div className="mt-2 text-xs text-slate-400">
                Uses a mock user locally.
              </div>
            </button>
          </div>
          <p className="mt-4 text-xs text-slate-400">
            Note: Auth bypass + real data will likely fail with 401 responses.
          </p>
        </div>
      </div>
    </div>
  )
}
