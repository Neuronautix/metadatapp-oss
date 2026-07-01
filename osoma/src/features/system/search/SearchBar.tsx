import { useEffect, useMemo, useRef, useState, type KeyboardEvent } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import {
  Activity,
  AlertTriangle,
  Aperture,
  ArrowUpRight,
  BookOpenCheck,
  Building2,
  CalendarDays,
  ClipboardList,
  Copy,
  Database,
  Dna,
  Fish,
  FlaskConical,
  FolderKanban,
  KeyRound,
  LayoutGrid,
  LineChart,
  Loader2,
  MapPinned,
  Network,
  Radar,
  Search,
  ShieldAlert,
  ShieldCheck,
  SlidersHorizontal,
  Sparkles,
  TestTube2,
  ThermometerSun,
  Upload,
  User,
  Users,
  Zap,
  type LucideIcon,
} from 'lucide-react'
import { getSearchResults } from './search.api.ts'
import type { SearchResourceType, SearchResult } from './search.types.ts'
import { Button } from '@/components/ui/button.tsx'
import { Input } from '@/components/ui/input.tsx'
import { Card } from '@/components/ui/card.tsx'
import { useToast } from '@/components/ui/toast.tsx'
import { cn } from '@/lib/utils.ts'
import { useRole } from '@/app/role-context.tsx'
import { isAdmin } from '@/lib/rbac.ts'

type NavItem = { to: string; label: string; icon: LucideIcon; section: string; adminOnly?: boolean }

const allNavItems: NavItem[] = [
  { to: '/investigations', label: 'Investigations', icon: FolderKanban, section: 'Core' },
  { to: '/studies', label: 'Studies', icon: FlaskConical, section: 'Core' },
  { to: '/samples', label: 'Samples', icon: TestTube2, section: 'Core' },
  { to: '/calendar', label: 'Calendar', icon: CalendarDays, section: 'Core' },
  { to: '/subjects', label: 'Subjects', icon: Users, section: 'Registry' },
  { to: '/cages', label: 'Cages', icon: LayoutGrid, section: 'Registry' },
  { to: '/assays', label: 'Assays', icon: ClipboardList, section: 'Registry' },
  { to: '/datasets', label: 'Datasets', icon: Database, section: 'Registry' },
  { to: '/audit', label: 'Audit Log', icon: ShieldCheck, section: 'Operations', adminOnly: true },
  { to: '/users', label: 'Users', icon: Users, section: 'Operations', adminOnly: true },
  { to: '/organizations', label: 'Organizations', icon: Building2, section: 'Operations', adminOnly: true },
  { to: '/tp', label: 'Operations', icon: Activity, section: 'Facility Ops' },
  { to: '/tp/map', label: 'Facility Map', icon: MapPinned, section: 'Facility Ops' },
  { to: '/tp/alarms', label: 'Alarms', icon: ShieldAlert, section: 'Facility Ops' },
  { to: '/tp/conditions', label: 'Conditions', icon: ThermometerSun, section: 'Facility Ops' },
  { to: '/tp/sensors', label: 'Sensors', icon: Radar, section: 'Tecniplast DVC' },
  { to: '/dvc/metadata-fusion', label: 'Metadata-fusion', icon: Network, section: 'Tecniplast DVC' },
  { to: '/hcm/comparison', label: 'HCM Comparison', icon: LineChart, section: 'HCM' },
  { to: '/dvc/activity', label: 'Activity Explorer', icon: LineChart, section: 'Tecniplast DVC' },
  { to: '/dvc/analytics', label: 'Analytics Hub', icon: Zap, section: 'Tecniplast DVC' },
  { to: '/observatory', label: 'Observatory', icon: Aperture, section: 'Facility Ops' },
  { to: '/zefix', label: 'Zebrafish / ZEFIX', icon: Fish, section: 'ZEFIX' },
  { to: '/zefix/lines', label: 'Lines', icon: Dna, section: 'ZEFIX' },
  { to: '/zefix/batches', label: 'Batches', icon: LayoutGrid, section: 'ZEFIX' },
  { to: '/zefix/location', label: 'Location', icon: MapPinned, section: 'ZEFIX' },
  { to: '/metadata', label: 'Metadata Catalog', icon: BookOpenCheck, section: 'Metadata' },
  { to: '/connected-apps', label: 'Connected Apps', icon: Network, section: 'Core' },
  { to: '/import/csv', label: 'CSV Import', icon: Upload, section: 'Metadata' },
  { to: '/data-entry', label: 'Data Entry Grid', icon: LayoutGrid, section: 'Metadata' },
  { to: '/settings/profile', label: 'Profile', icon: User, section: 'Settings' },
  { to: '/settings/api-keys', label: 'API Keys', icon: KeyRound, section: 'Settings', adminOnly: true },
  { to: '/settings/appearance', label: 'Appearance Studio', icon: SlidersHorizontal, section: 'Settings' },
  { to: '/settings/docs', label: 'Documentation', icon: BookOpenCheck, section: 'Settings' },
  { to: '/admin/feature-flags', label: 'Flag Studio', icon: Sparkles, section: 'Settings', adminOnly: true },
  { to: '/atmp', label: 'ATMP', icon: Dna, section: 'Core' },
  { to: '/nam-vcg', label: 'NAM-VCG', icon: Radar, section: 'DVC Control' },
  { to: '/fair-navigator', label: 'FAIR Navigator', icon: Network, section: 'DVC Control' },
  { to: '/curation', label: 'Data Curation', icon: ClipboardList, section: 'Workspace' },
  { to: '/curation/schemas/mapp-view', label: 'MappView', icon: Network, section: 'Workspace' },
]

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPod|iPad/.test(navigator.platform)

const QUICK_JUMP_MAX_ITEMS = 8

const resourceOrder: SearchResourceType[] = [
  'investigation',
  'study',
  'sample',
  'subject',
  'assay',
  'dataset',
  'user',
  'organization',
]

const resourceMeta: Record<SearchResourceType, { label: string; route: string }> = {
  investigation: { label: 'Investigations', route: '/investigations' },
  study: { label: 'Studies', route: '/studies' },
  sample: { label: 'Samples', route: '/samples' },
  subject: { label: 'Subjects', route: '/subjects' },
  assay: { label: 'Assays', route: '/assays' },
  dataset: { label: 'Datasets', route: '/datasets' },
  user: { label: 'Users', route: '/users' },
  organization: { label: 'Organizations', route: '/organizations' },
}

const groupResults = (results: SearchResult[]) =>
  resourceOrder
    .map((type) => ({ type, items: results.filter((result) => result.type === type) }))
    .filter((group) => group.items.length > 0)

export function SearchBar() {
  const { role } = useRole()
  const allowAdmin = isAdmin(role)
  const [query, setQuery] = useState('')
  const [debouncedQuery, setDebouncedQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [activeIndex, setActiveIndex] = useState(-1)
  const containerRef = useRef<HTMLDivElement | null>(null)
  const inputRef = useRef<HTMLInputElement | null>(null)
  const navigate = useNavigate()
  const { toast } = useToast()

  useEffect(() => {
    const handle = window.setTimeout(() => setDebouncedQuery(query.trim()), 320)
    return () => window.clearTimeout(handle)
  }, [query])

  useEffect(() => {
    const handleClick = (event: MouseEvent) => {
      if (!containerRef.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClick)
    return () => document.removeEventListener('mousedown', handleClick)
  }, [])

  useEffect(() => {
    const handleGlobalKeyDown = (event: globalThis.KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
        event.preventDefault()
        inputRef.current?.focus()
        setOpen(true)
      }
    }
    document.addEventListener('keydown', handleGlobalKeyDown)
    return () => document.removeEventListener('keydown', handleGlobalKeyDown)
  }, [])

  const search = useQuery({
    queryKey: ['search', debouncedQuery],
    queryFn: () => getSearchResults(debouncedQuery),
    enabled: debouncedQuery.length > 1,
    retry: false,
  })

  const grouped = useMemo(() => groupResults(search.data?.results ?? []), [search.data?.results])
  const flatResults = useMemo(() => grouped.flatMap((group) => group.items), [grouped])

  const matchingNavItems = useMemo(() => {
    const q = query.trim().toLowerCase()
    const visibleNavItems = allNavItems.filter((item) => allowAdmin || !item.adminOnly)
    if (!q) return visibleNavItems.slice(0, QUICK_JUMP_MAX_ITEMS)
    return visibleNavItems.filter((item) => item.label.toLowerCase().includes(q) || item.section.toLowerCase().includes(q))
  }, [allowAdmin, query])

  const totalItems = matchingNavItems.length + flatResults.length

  useEffect(() => {
    if (flatResults.length || matchingNavItems.length) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setActiveIndex(0)
    } else {
      setActiveIndex(-1)
    }
  }, [flatResults.length, matchingNavItems.length])

  const handleOpen = (result: SearchResult) => {
    navigate(`${resourceMeta[result.type].route}/${result.id}`)
    setOpen(false)
  }

  const handleNavJump = (to: string) => {
    navigate(to)
    setOpen(false)
    setQuery('')
    inputRef.current?.blur()
  }

  const handleCopy = async (id: string) => {
    try {
      await navigator.clipboard.writeText(id)
      toast({ title: 'Copied to clipboard', description: id })
    } catch {
      toast({ title: 'Copy failed', description: 'Clipboard access is unavailable.' })
    }
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (!open) return

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActiveIndex((prev) => Math.min(prev + 1, totalItems - 1))
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveIndex((prev) => Math.max(prev - 1, 0))
    }

    if (event.key === 'Enter' && activeIndex >= 0) {
      event.preventDefault()
      if (activeIndex < matchingNavItems.length) {
        const navItem = matchingNavItems[activeIndex]
        if (navItem) handleNavJump(navItem.to)
      } else {
        const target = flatResults[activeIndex - matchingNavItems.length]
        if (target) handleOpen(target)
      }
    }

    if (event.key === 'Escape') {
      setOpen(false)
      inputRef.current?.blur()
    }
  }

  const showPanel = open

  return (
    <div ref={containerRef} className="relative hidden md:flex md:min-w-[320px] md:max-w-[460px] md:flex-1">
      <div className="flex w-full items-center gap-2 rounded-full border border-line bg-surface px-3 py-1.5 text-sm text-slate-500">
        <Search className="h-4 w-4 shrink-0" />
        <Input
          ref={inputRef}
          value={query}
          onChange={(event) => {
            setQuery(event.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          onKeyDown={handleKeyDown}
          className="h-7 flex-1 border-0 bg-transparent p-0 text-sm text-slate-600 focus-visible:ring-0"
          placeholder="Search or jump to…"
        />
        <kbd className="hidden select-none items-center gap-0.5 rounded border border-line bg-surface-2 px-1.5 py-0.5 text-[10px] font-medium text-muted md:flex">
          {isMac ? '⌘' : 'Ctrl'}K
        </kbd>
      </div>

      {showPanel ? (
        <Card className="absolute left-0 right-0 top-full mt-3 max-h-[70vh] overflow-auto rounded-2xl border border-line bg-white p-3 shadow-subtle">
          {matchingNavItems.length > 0 ? (
            <div className="mb-3 space-y-1">
              <p className="px-2 pb-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                {query.trim() ? 'Jump to' : 'Quick Jump'}
              </p>
              {matchingNavItems.map((item, index) => {
                const isActive = index === activeIndex
                return (
                  <div
                    key={item.to}
                    role="button"
                    tabIndex={-1}
                    onMouseEnter={() => setActiveIndex(index)}
                    onClick={() => handleNavJump(item.to)}
                    className={cn(
                      'flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition',
                      isActive ? 'bg-surface-2' : 'hover:bg-surface-3'
                    )}
                  >
                    <item.icon className="h-4 w-4 shrink-0 text-slate-400" />
                    <span className="flex-1 font-medium text-slate-800">{item.label}</span>
                    <span className="text-xs text-slate-400">{item.section}</span>
                    <ArrowUpRight className="h-3 w-3 text-slate-300" />
                  </div>
                )
              })}
              {flatResults.length > 0 ? (
                <div className="my-2 border-t border-line" />
              ) : null}
            </div>
          ) : null}
          {query.trim().length < 2 ? null : search.isLoading ? (
            <div className="flex items-center gap-2 px-2 py-4 text-sm text-slate-500">
              <Loader2 className="h-4 w-4 animate-spin" />
              Searching across resources...
            </div>
          ) : search.isError ? (
            <div className="flex flex-col gap-3 px-2 py-4 text-sm text-slate-500">
              <div className="flex items-center gap-2">
                <AlertTriangle className="h-4 w-4 text-amber-500" />
                Search service is unavailable.
              </div>
              <Button variant="outline" size="sm" onClick={() => search.refetch()}>
                Retry search
              </Button>
            </div>
          ) : flatResults.length === 0 && matchingNavItems.length === 0 ? (
            <div className="px-2 py-4 text-sm text-slate-500">No matches found.</div>
          ) : flatResults.length > 0 ? (
            <div className="space-y-4">
              {grouped.map((group) => (
                <div key={group.type} className="space-y-2">
                  <p className="px-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                    {resourceMeta[group.type].label}
                  </p>
                  <div className="space-y-1">
                    {group.items.map((item, index) => {
                      const prevGroupsCount = grouped
                        .slice(0, grouped.findIndex((g) => g.type === group.type))
                        .reduce((acc, g) => acc + g.items.length, 0)
                      const currentIndex = matchingNavItems.length + prevGroupsCount + index
                      const isActive = currentIndex === activeIndex
                      return (
                        <div
                          key={`${item.type}-${item.id}`}
                          role="button"
                          tabIndex={-1}
                          onMouseEnter={() => setActiveIndex(currentIndex)}
                          onClick={() => handleOpen(item)}
                          className={cn(
                            'flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2 text-left text-sm transition',
                            isActive ? 'bg-surface-2' : 'hover:bg-surface-3'
                          )}
                        >
                          <div>
                            <p className="font-medium text-slate-900">{item.title}</p>
                            <p className="text-xs text-slate-500">{item.subtitle ?? item.id}</p>
                          </div>
                          <div className="flex items-center gap-2 text-xs text-slate-500">
                            {item.status ? (
                              <span className="rounded-full border border-line px-2 py-0.5 text-[11px]">
                                {item.status}
                              </span>
                            ) : null}
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={(event) => {
                                event.stopPropagation()
                                handleCopy(item.id)
                              }}
                            >
                              <Copy className="h-3 w-3" />
                              Copy ID
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={(event) => {
                                event.stopPropagation()
                                handleOpen(item)
                              }}
                            >
                              <ArrowUpRight className="h-3 w-3" />
                              Open
                            </Button>
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </div>
              ))}
            </div>
          ) : null}
        </Card>
      ) : null}
    </div>
  )
}
