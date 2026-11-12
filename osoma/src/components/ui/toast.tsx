import * as React from 'react'
import { cn } from '@/lib/utils.ts'

export type ToastMessage = {
  id?: string
  title: string
  description?: string
  variant?: 'default' | 'success' | 'error'
}

type ToastContextValue = {
  toasts: ToastMessage[]
  toast: (message: ToastMessage) => void
  dismiss: (id: string) => void
}

const ToastContext = React.createContext<ToastContextValue | undefined>(undefined)

const TOAST_DURATION = 4000

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = React.useState<ToastMessage[]>([])

  const dismiss = React.useCallback((id: string) => {
    setToasts((current) => current.filter((toast) => toast.id !== id))
  }, [])

  const toast = React.useCallback(
    (message: ToastMessage) => {
      const id = message.id ?? (globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`)
      const next = { ...message, id }

      setToasts((current) => [...current, next])

      window.setTimeout(() => {
        dismiss(id)
      }, TOAST_DURATION)
    },
    [dismiss]
  )

  const value = React.useMemo(() => ({ toasts, toast, dismiss }), [toasts, toast, dismiss])

  return <ToastContext.Provider value={value}>{children}</ToastContext.Provider>
}

export function useToast() {
  const context = React.useContext(ToastContext)
  if (!context) {
    throw new Error('useToast must be used within ToastProvider')
  }
  return context
}

export function ToastCard({ toast }: { toast: ToastMessage }) {
  const variantStyles = {
    default: 'bg-secondary text-on-secondary',
    success: 'bg-success text-white',
    error: 'bg-error text-white',
  }

  return (
    <div className={cn('rounded-2xl px-4 py-3 shadow-panel', variantStyles[toast.variant ?? 'default'])}>
      <p className="text-sm font-semibold">{toast.title}</p>
      {toast.description ? <p className="mt-1 text-xs text-white/80">{toast.description}</p> : null}
    </div>
  )
}
