import { useAuth } from '@/app/auth-context.tsx'
import { Button } from '@/components/ui/button.tsx'
import { Loader2, LogIn } from 'lucide-react'
import { useState } from 'react'
import { Logo } from '@/components/Logo.tsx'

export function LoginPage() {
    const { login } = useAuth()
    const [isLoggingIn, setIsLoggingIn] = useState(false)

    const handleLogin = async () => {
        setIsLoggingIn(true)
        try {
            await login()
        } catch (error) {
            console.error('Login prompt failed:', error)
            setIsLoggingIn(false)
        }
    }

    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-4 relative overflow-hidden">
            {/* Decorative background blobs to mimic the AppLayout styling */}
            <div className="pointer-events-none absolute inset-0">
                <div
                    className="absolute left-[15%] top-[10%] h-64 w-64 rounded-full bg-accent/25 blur-[100px]"
                    style={{ opacity: 'var(--ambient-blob)' }}
                />
                <div
                    className="absolute right-[15%] bottom-[15%] h-72 w-72 rounded-full bg-info/20 blur-[100px]"
                    style={{ opacity: 'var(--ambient-blob)' }}
                />
            </div>

            <div className="relative z-10 w-full max-w-md rounded-3xl border border-line bg-surface p-10 shadow-[0_0_40px_-15px_rgba(0,0,0,0.1)] backdrop-blur-sm">
                <div className="flex flex-col items-center text-center space-y-6">

                    <div className="flex items-center space-x-3 mb-2">
                        <Logo className="text-primary" />
                    </div>

                    <div className="space-y-2">
                        <h1 className="font-display text-2xl font-bold tracking-tight">
                            Welcome to Osoma
                        </h1>
                        <p className="text-sm text-slate-500 max-w-xs mx-auto">
                            Please identify yourself with your Keycloak credentials to access your centralized metadata environment.
                        </p>
                    </div>

                    <div className="w-full pt-4">
                        <Button
                            className="w-full py-6 text-base font-medium"
                            size="lg"
                            onClick={handleLogin}
                            disabled={isLoggingIn}
                        >
                            {isLoggingIn ? (
                                <>
                                    <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                                    Authenticating...
                                </>
                            ) : (
                                <>
                                    <LogIn className="mr-2 h-5 w-5" />
                                    Login with Keycloak
                                </>
                            )}
                        </Button>
                    </div>

                    {import.meta.env.DEV && (
                        <div className="w-full mt-2 rounded-xl border border-dashed border-amber-300 bg-amber-50/60 px-4 py-3 text-left space-y-2">
                            <p className="text-[10px] font-semibold uppercase tracking-widest text-amber-600">
                                Dev credentials · password: <span className="font-mono">Pa55w0rd</span>
                            </p>
                            {[
                                { role: 'Admin', email: 'demo.admin@metadatapp.net' },
                                { role: 'User',  email: 'demo.user@metadatapp.net'  },
                            ].map(({ role, email }) => (
                                <div key={email} className="flex items-center justify-between gap-3">
                                    <span className="text-[10px] text-amber-700 font-medium w-10 shrink-0">{role}</span>
                                    <code className="flex-1 truncate text-[11px] text-amber-900 bg-amber-100 rounded px-2 py-0.5 select-all">
                                        {email}
                                    </code>
                                </div>
                            ))}
                        </div>
                    )}

                </div>
            </div>
        </div>
    )
}
