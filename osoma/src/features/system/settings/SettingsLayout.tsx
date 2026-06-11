import { Outlet, NavLink } from 'react-router-dom'
import { User, Palette, Book, KeyRound, Plug, Bot } from 'lucide-react'
import { cn } from '@/lib/utils.ts'
import { PageHeader } from '@/components/PageHeader.tsx'
import { useRole } from '@/app/role-context.tsx'
import { isAdmin } from '@/lib/rbac.ts'

const baseSidebarNavItems = [
    {
        title: 'Profile',
        href: '/settings/profile',
        icon: User,
    },
    {
        title: 'Appearance',
        href: '/settings/appearance',
        icon: Palette,
    },
    {
        title: 'API Keys',
        href: '/settings/api-keys',
        icon: KeyRound,
    },
    {
        title: 'AI Providers',
        href: '/settings/AI-providers',
        icon: Bot,
    },
    {
        title: 'Documentation',
        href: '/settings/docs',
        icon: Book,
    },
    {
        title: 'Connected Apps',
        href: '/connected-apps',
        icon: Plug,
    },
]

export function SettingsLayout() {
    const { role } = useRole()
    const allowAdmin = isAdmin(role)
    const sidebarNavItems = baseSidebarNavItems.filter((item) => allowAdmin || !['/settings/api-keys', '/settings/AI-providers'].includes(item.href))

    return (
        <div className="space-y-6">
            <PageHeader
                title="Settings"
                subtitle="Manage your account, preferences, and workspace configuration."
            />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-x-12 lg:space-y-0">
                <aside className="-mx-4 lg:w-1/5">
                    <nav className="flex space-x-2 lg:flex-col lg:space-x-0 lg:space-y-1">
                        {sidebarNavItems.map((item) => (
                            <NavLink
                                key={item.href}
                                to={item.href}
                                className={({ isActive }) =>
                                    cn(
                                        'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium hover:bg-accent hover:text-accent-foreground',
                                        isActive ? 'bg-accent text-accent-foreground' : 'text-muted-foreground' // Simpler active state
                                    )
                                }
                            >
                                <item.icon className="h-4 w-4" />
                                {item.title}
                            </NavLink>
                        ))}
                    </nav>
                </aside>
                <div className="flex-1 lg:max-w-4xl">
                    <Outlet />
                </div>
            </div>
        </div>
    )
}
