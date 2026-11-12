import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card.tsx'
import { Avatar, AvatarFallback } from '@/components/ui/avatar.tsx'
import { cn } from '@/lib/utils.ts'
import { formatRelativeTime } from '@/lib/format.ts'

export interface ActivityItem {
    id: string
    title: string
    description: string
    timestamp: string | Date
    type: 'investigation' | 'study' | 'sample'
    user?: {
        name: string
        initials: string
    }
}

interface RecentActivityListProps {
    items: ActivityItem[]
    title?: string
    description?: string
    className?: string
}

export function RecentActivityList({
    items,
    title = 'Recent Activity',
    description = 'Latest updates from your team',
    className,
}: RecentActivityListProps) {
    return (
        <Card className={cn("col-span-3", className)}>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="space-y-8">
                    {items.map((item) => (
                        <div key={item.id} className="flex items-center">
                            <Avatar className="h-9 w-9">
                                <AvatarFallback>{item.user?.initials || '??'}</AvatarFallback>
                            </Avatar>
                            <div className="ml-4 space-y-1">
                                <p className="text-sm font-medium leading-none">{item.title}</p>
                                <p className="text-sm text-muted-foreground">
                                    {item.description}
                                </p>
                            </div>
                            <div className="ml-auto text-sm text-muted-foreground">
                                {formatRelativeTime(item.timestamp)}
                            </div>
                        </div>
                    ))}
                    {items.length === 0 && (
                        <div className="text-center text-sm text-muted-foreground py-4">
                            No recent activity found.
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    )
}
