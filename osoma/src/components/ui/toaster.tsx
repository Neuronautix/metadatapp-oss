import { ToastCard, useToast } from './toast.tsx'

export function Toaster() {
  const { toasts, dismiss } = useToast()

  if (toasts.length === 0) {
    return null
  }

  return (
    <div className="fixed bottom-6 right-6 z-50 flex w-[320px] flex-col gap-3">
      {toasts.map((toast) => (
        <button
          key={toast.id}
          className="text-left"
          onClick={() => toast.id && dismiss(toast.id)}
        >
          <ToastCard toast={toast} />
        </button>
      ))}
    </div>
  )
}
