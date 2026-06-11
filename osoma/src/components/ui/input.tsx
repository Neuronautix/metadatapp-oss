import * as React from 'react'
import { cn } from '@/lib/utils.ts'

const Input = React.forwardRef<HTMLInputElement, React.InputHTMLAttributes<HTMLInputElement>>(
  ({ className, type, ...props }, ref) => (
    <input
      type={type}
      className={cn(
        'flex h-[var(--control-height)] w-full rounded-[var(--radius-xl)] border border-line bg-surface px-[var(--control-padding-x)] py-[var(--control-padding-y)] text-sm text-ink placeholder:text-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus',
        className
      )}
      ref={ref}
      {...props}
    />
  )
)
Input.displayName = 'Input'

export { Input }
