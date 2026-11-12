import * as React from 'react'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils.ts'

const badgeVariants = cva(
  'inline-flex items-center rounded-[var(--radius-full)] border border-transparent px-2.5 py-1 text-xs font-medium',
  {
    variants: {
      variant: {
        default: 'bg-secondary text-on-secondary',
        success: 'bg-success/15 text-success border-success/30',
        warning: 'bg-warning/15 text-warning border-warning/30',
        critical: 'bg-error/20 text-error border-error/40',
        outline: 'border-line text-muted',
      },
    },
    defaultVariants: {
      variant: 'outline',
    },
  }
)

export interface BadgeProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
  return <div className={cn(badgeVariants({ variant }), className)} {...props} />
}

export { Badge, badgeVariants }
