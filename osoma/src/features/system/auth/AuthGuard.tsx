import { useAuth } from '@/app/auth-context.tsx'
import { LoginPage } from './LoginPage.tsx'
import { Loader2 } from 'lucide-react'
import { ReactNode } from 'react'

interface AuthGuardProps {
    children: ReactNode
}

export function AuthGuard({ children }: AuthGuardProps) {
    const { isAuthenticated, isLoading } = useAuth()

    if (isLoading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-background">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
        )
    }

    if (!isAuthenticated) {
        return <LoginPage />
    }

    return <>{children}</>
}
