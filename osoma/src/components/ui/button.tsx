import * as React from 'react'
import { Slot } from '@radix-ui/react-slot'
import { cva, type VariantProps } from 'class-variance-authority'
import { cn } from '@/lib/utils.ts'

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[var(--radius-xl)] text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus disabled:pointer-events-none disabled:opacity-50',
  {
    variants: {
      variant: {
        default: 'bg-brand text-on-brand shadow-subtle hover:bg-brand/90',
        secondary: 'bg-secondary text-on-secondary hover:bg-secondary/90',
        destructive: 'bg-red-600 text-white shadow-subtle hover:bg-red-700',
        outline: 'border border-line bg-surface text-ink hover:bg-surface-2',
        ghost: 'text-muted hover:bg-surface-2',
      },
      size: {
        default: 'h-[var(--control-height)] px-[var(--control-padding-x)] py-[var(--control-padding-y)]',
        sm: 'h-[var(--control-height-sm)] px-[calc(var(--control-padding-x)*0.75)] text-xs',
        lg: 'h-[var(--control-height-lg)] px-[calc(var(--control-padding-x)*1.2)] text-base',
        icon: 'h-[var(--control-height)] w-[var(--control-height)]',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  }
)

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>,
    VariantProps<typeof buttonVariants> {
  asChild?: boolean
}

const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button'
    return (
      <Comp
        className={cn(buttonVariants({ variant, size, className }))}
        ref={ref}
        {...props}
      />
    )
  }
)
Button.displayName = 'Button'

export { Button, buttonVariants }
