import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import type { LucideIcon } from 'lucide-react'
import { cn } from '@/lib/utils.ts'

interface StatsCardProps {
    title: string
    value: string | number
    description?: string
    icon: LucideIcon
    className?: string
    trend?: {
        value: number
        label: string
        positive?: boolean
    }
}

export function StatsCard({ title, value, description, icon: Icon, className, trend }: StatsCardProps) {
    return (
        <Card className={cn('overflow-hidden', className)}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
                <Icon className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
                {(description || trend) && (
                    <div className="mt-1 flex items-center text-xs text-muted-foreground">
                        {trend && (
                            <span
                                className={cn(
                                    'mr-2 font-medium',
                                    trend.positive ? 'text-green-600' : 'text-red-600'
                                )}
                            >
                                {trend.positive ? '+' : ''}
                                {trend.value}%
                            </span>
                        )}
                        {description}
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
