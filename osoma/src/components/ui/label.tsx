import * as React from 'react'
import { cn } from '@/lib/utils.ts'

const Label = React.forwardRef<HTMLLabelElement, React.LabelHTMLAttributes<HTMLLabelElement>>(
  ({ className, ...props }, ref) => (
    <label
      ref={ref}
      className={cn('text-xs font-semibold uppercase tracking-[0.2em] text-slate-600', className)}
      {...props}
    />
  )
)
Label.displayName = 'Label'

export { Label }
