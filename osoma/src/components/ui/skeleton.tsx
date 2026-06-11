import type React from 'react'
import { cn } from '@/lib/utils.ts'

function Skeleton({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) {
  return (
    <div
      className={cn('animate-pulse rounded-xl bg-slate-200/70', className)}
      {...props}
    />
  )
}

export { Skeleton }
