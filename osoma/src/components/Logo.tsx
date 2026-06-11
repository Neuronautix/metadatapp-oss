import { Link } from 'react-router-dom'
import { cn } from '@/lib/utils.ts'

interface LogoProps {
    className?: string
    iconOnly?: boolean
}

export function Logo({ className, iconOnly = false }: LogoProps) {
    return (
        <Link to="/" className={cn('flex items-center gap-2', className)}>
            <img
                src="/logo.png"
                alt={iconOnly ? 'Metadatapp logo' : ''}
                aria-hidden={iconOnly ? false : true}
                className="h-10 w-10 rounded-xl object-cover shadow-subtle"
            />
            {!iconOnly && (
                <div className="flex flex-col">
                    <span className="font-display text-lg font-bold tracking-tight text-ink">
                        Metadata<span className="text-brand">pp</span>
                    </span>
                    <span className="text-[10px] font-medium uppercase tracking-[0.2em] text-muted">
                        Precision Metadata
                    </span>
                </div>
            )}
        </Link>
    )
}
