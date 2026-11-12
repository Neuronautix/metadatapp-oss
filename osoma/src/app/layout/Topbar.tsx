import { Link, NavLink } from 'react-router-dom'
import {
  Bell,
  BookOpenCheck,
  ChevronDown,
  KeyRound,
  LogIn,
  LogOut,
  Moon,
  Palette,
  ShieldCheck,
  SlidersHorizontal,
  Sparkles,
  Sun,
  UserCircle2,
  Users,
} from 'lucide-react'
import { useState } from 'react'
import { Button } from '@/components/ui/button.tsx'
import { Badge } from '@/components/ui/badge.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { SearchBar } from '@/features/system/search/SearchBar.tsx'
import { useRole } from '@/app/role-context.tsx'
import { canEdit, isAdmin, roleOptions } from '@/lib/rbac.ts'
import { useFeatureFlagContext } from '@/feature-flags/FeatureFlagProvider.tsx'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { useAuth } from '@/app/auth-context.tsx'
import type { FeatureFlagPresetKey } from '@/feature-flags/presets.ts'
import { useTheme } from '@/theme/useTheme.ts'

export function Topbar() {
  const { toast } = useToast()
  const [accountMenuOpen, setAccountMenuOpen] = useState(false)
  const { role, setRole, isDerivedFromAuth } = useRole()
  const { session, login, logout } = useAuth()
  const showRoleSwitch = import.meta.env.DEV && !isDerivedFromAuth
  const { presets, activePreset, applyPreset } = useFeatureFlagContext()
  const metadatappEnabled = useFeatureFlag('metadatapp-feat.enabled')
  const nonMetadatappEnabled = useFeatureFlag('non-metadatapp-feat.enabled')
  const atmpEnabled = useFeatureFlag('atmp.enabled')
  const dvcNamEnabled = useFeatureFlag('dvc.namVcg.enabled')
  const cagesEnabled = useFeatureFlag('cages.enabled')
  const dropdownValue = activePreset ?? 'custom'
  const { tokens, updateTokens } = useTheme()
  const isDark = tokens.mode === 'dark'
  const allowEdit = canEdit(role)
  const allowAdmin = isAdmin(role)
  const organizationName = session?.user?.accountName ?? session?.user?.accountKey
  const roleBadgeLabel = session?.user?.organizationRole ? session.user.organizationRole.toUpperCase() : role
  const settingsLinks = [
    { to: '/settings/profile', label: 'Profile', icon: UserCircle2 },
    { to: '/settings/appearance', label: 'Appearance', icon: Palette },
    { to: '/settings/docs', label: 'Documentation', icon: BookOpenCheck },
  ]
  const adminLinks = [
    { to: '/settings/api-keys', label: 'API Keys', icon: KeyRound },
    { to: '/settings/AI-providers', label: 'AI Providers', icon: Sparkles },
    { to: '/users', label: 'Users', icon: Users },
    { to: '/audit', label: 'Audit Log', icon: ShieldCheck },
    { to: '/admin/feature-flags', label: 'Flag Studio', icon: SlidersHorizontal },
  ]

  const toggleMode = () => {
    updateTokens((current) => ({ ...current, mode: current.mode === 'dark' ? 'light' : 'dark' }))
  }

  return (
    <header className="sticky top-0 z-30 border-b border-line/50 bg-surface/80 px-6 py-4 backdrop-blur-md shadow-subtle transition-all duration-300">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-muted">METADATAPP</p>
          <h1 className="font-display text-2xl text-ink">All-in-One Metadata Management Platform</h1>
          <div className="mt-2 flex items-center gap-2 text-xs text-muted md:hidden">
            {metadatappEnabled ? (
              <>
                <NavLink to="/metadata" className="rounded-full border border-line px-3 py-1">
                  Metadata Catalog
                </NavLink>
                <NavLink to="/investigations" className="rounded-full border border-line px-3 py-1">
                  Investigations
                </NavLink>
                <NavLink to="/studies" className="rounded-full border border-line px-3 py-1">
                  Studies
                </NavLink>
                <NavLink to="/subjects" className="rounded-full border border-line px-3 py-1">
                  Subjects
                </NavLink>
                {allowAdmin ? (
                  <NavLink to="/users" className="rounded-full border border-line px-3 py-1">
                    Users
                  </NavLink>
                ) : null}
              </>
            ) : null}
            {nonMetadatappEnabled ? (
              <>
                <NavLink to="/samples" className="rounded-full border border-line px-3 py-1">
                  Samples
                </NavLink>
                <NavLink to="/calendar" className="rounded-full border border-line px-3 py-1">
                  Calendar
                </NavLink>
                <NavLink to="/audit" className="rounded-full border border-line px-3 py-1">
                  Audit
                </NavLink>
                <NavLink to="/tp" className="rounded-full border border-line px-3 py-1">
                  Ops
                </NavLink>
                {cagesEnabled ? (
                  <NavLink to="/cages" className="rounded-full border border-line px-3 py-1">
                    Cages
                  </NavLink>
                ) : null}
                <NavLink to="/dvc/metadata-fusion" className="rounded-full border border-line px-3 py-1">
                    Fusion
                </NavLink>
                <NavLink to="/hcm/comparison" className="rounded-full border border-line px-3 py-1">
                    HCM
                </NavLink>
              </>
            ) : null}
            {allowAdmin ? (
              <NavLink to="/admin/feature-flags" className="rounded-full border border-line px-3 py-1">
                Flag Studio
              </NavLink>
            ) : null}
            {nonMetadatappEnabled && atmpEnabled ? (
              <NavLink to="/atmp" className="rounded-full border border-line px-3 py-1">
                ATMP
              </NavLink>
            ) : null}
            {nonMetadatappEnabled && dvcNamEnabled ? (
              <NavLink to="/nam-vcg" className="rounded-full border border-line px-3 py-1">
                NAM-VCG
              </NavLink>
            ) : null}
            {nonMetadatappEnabled && dvcNamEnabled ? (
              <NavLink to="/fair-navigator" className="rounded-full border border-line px-3 py-1">
                FAIR
              </NavLink>
            ) : null}
          </div>
        </div>
        <div className="flex items-center gap-3">
          <SearchBar />
          <select
            className="h-9 rounded-full border border-line bg-surface px-3 text-xs text-muted"
            value={dropdownValue}
            onChange={(event) => {
              const value = event.target.value
              if (value === 'custom') {
                return
              }
              applyPreset(value as FeatureFlagPresetKey)
            }}
          >
            {activePreset ? null : (
              <option value="custom" disabled>
                Manual tone
              </option>
            )}
            {Object.values(presets).map((preset) => (
              <option key={preset.key} value={preset.key}>
                {preset.label}
              </option>
            ))}
          </select>
          {showRoleSwitch ? (
            <div className="hidden items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-xs text-muted md:flex">
              <span className="uppercase tracking-[0.2em]">Role</span>
              <select
                className="border-0 bg-transparent text-xs text-ink focus:outline-none"
                value={role}
                onChange={(event) => setRole(event.target.value as (typeof roleOptions)[number])}
              >
                {roleOptions.map((option) => (
                  <option key={option} value={option}>
                    {option}
                  </option>
                ))}
              </select>
            </div>
          ) : null}
          <Button
            disabled={!allowEdit}
            title={allowEdit ? undefined : 'Editor or admin access is required to create investigations.'}
            aria-label={allowEdit ? 'New investigation' : 'New investigation requires editor or admin access'}
            onClick={() =>
              toast({
                title: 'Investigation draft created',
                description: 'A new investigation space is ready in Investigations.',
              })
            }
          >
            New investigation
          </Button>
          <Button variant="outline" asChild className="gap-2">
            <Link to="/assistant">
              <Sparkles className="h-4 w-4" />
              Ask AI
            </Link>
          </Button>
          <button
            onClick={toggleMode}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            aria-pressed={isDark}
            className="grid h-10 w-10 place-items-center rounded-full border border-line bg-surface text-muted transition-colors hover:text-ink"
          >
            {isDark ? <Sun className="h-4 w-4" /> : <Moon className="h-4 w-4" />}
          </button>
          <button className="grid h-10 w-10 place-items-center rounded-full border border-line bg-surface text-muted">
            <Bell className="h-4 w-4" />
          </button>
          {session ? (
            <div className="relative">
              <button
                type="button"
                aria-haspopup="menu"
                aria-expanded={accountMenuOpen}
                onClick={() => setAccountMenuOpen((open) => !open)}
                className="flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-sm text-muted transition hover:text-ink"
              >
                <UserCircle2 className="h-5 w-5" />
                <span className="max-w-36 truncate">{session.user?.name || 'User'}</span>
                {organizationName ? (
                  <Badge variant="outline" className="hidden text-[10px] xl:inline-flex">
                    {organizationName}
                  </Badge>
                ) : null}
                <Badge variant="outline" className="text-[10px]">
                  {roleBadgeLabel}
                </Badge>
                <ChevronDown className={`h-4 w-4 transition-transform ${accountMenuOpen ? 'rotate-180' : ''}`} />
              </button>

              {accountMenuOpen ? (
                <div
                  role="menu"
                  className="absolute right-0 z-50 mt-2 w-72 overflow-hidden rounded-lg border border-line bg-surface shadow-xl"
                >
                  <div className="border-b border-line px-4 py-3">
                    <p className="truncate text-sm font-semibold text-ink">{session.user?.name || 'User'}</p>
                    <p className="truncate text-xs text-muted">{session.user?.email ?? organizationName ?? roleBadgeLabel}</p>
                  </div>

                  <div className="py-2">
                    <p className="px-4 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">Settings</p>
                    {settingsLinks.map((item) => (
                      <Link
                        key={item.to}
                        to={item.to}
                        role="menuitem"
                        onClick={() => setAccountMenuOpen(false)}
                        className="flex items-center gap-3 px-4 py-2 text-sm text-muted transition hover:bg-surface-2 hover:text-ink"
                      >
                        <item.icon className="h-4 w-4" />
                        {item.label}
                      </Link>
                    ))}
                  </div>

                  {allowAdmin ? (
                    <div className="border-t border-line py-2">
                      <p className="px-4 py-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-muted">
                        Administration
                      </p>
                      {adminLinks.map((item) => (
                        <Link
                          key={item.to}
                          to={item.to}
                          role="menuitem"
                          onClick={() => setAccountMenuOpen(false)}
                          className="flex items-center gap-3 px-4 py-2 text-sm text-muted transition hover:bg-surface-2 hover:text-ink"
                        >
                          <item.icon className="h-4 w-4" />
                          {item.label}
                        </Link>
                      ))}
                    </div>
                  ) : null}

                  <div className="border-t border-line py-2">
                    <button
                      type="button"
                      role="menuitem"
                      onClick={() => {
                        setAccountMenuOpen(false)
                        logout()
                      }}
                      className="flex w-full items-center gap-3 px-4 py-2 text-left text-sm text-muted transition hover:bg-surface-2 hover:text-ink"
                    >
                      <LogOut className="h-4 w-4" />
                      Logout
                    </button>
                  </div>
                </div>
              ) : null}
            </div>
          ) : (
            <Button
              onClick={login}
              variant="outline"
              className="rounded-full border border-line px-3 py-1.5 text-xs"
            >
              <LogIn className="mr-1 h-4 w-4" />
              Login
            </Button>
          )}
        </div>
      </div>
    </header>
  )
}
