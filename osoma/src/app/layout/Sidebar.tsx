import { useEffect, useState } from 'react'
import { NavLink, useLocation } from 'react-router-dom'
import {
  BookOpenCheck,
  CalendarDays,
  Building2,
  ClipboardList,
  Database,
  FlaskConical,
  FolderKanban,
  Activity,
  MapPinned,
  ShieldAlert,
  ThermometerSun,
  Radar,
  Aperture,
  ShieldCheck,
  Sparkles,
  TestTube2,
  Users,
  SlidersHorizontal,
  Dna,
  ActivitySquare,
  Network,
  LayoutGrid,
  LineChart,
  Zap,
  ChevronDown,
  Upload,
  Layers,
  Filter,
  Download,
  Bot,
} from 'lucide-react'
import { useFeatureFlag } from '@/feature-flags/useFeatureFlag.ts'
import { useRole } from '@/app/role-context.tsx'
import { isAdmin } from '@/lib/rbac.ts'
import { cn } from '@/lib/utils.ts'
import { Logo } from '@/components/Logo.tsx'

type NavItem = { to: string; label: string; icon: React.ComponentType<{ className?: string }> }

function SidebarSection({
  label,
  icon: Icon,
  items,
}: {
  label: string
  icon: React.ComponentType<{ className?: string }>
  items: NavItem[]
}) {
  const { pathname } = useLocation()
  const hasActiveChild = items.some((item) => pathname === item.to || pathname.startsWith(item.to + '/'))
  const [open, setOpen] = useState(hasActiveChild)

  useEffect(() => {
    // Keep the active section expanded when navigation changes to one of its child routes.
    // This is intentionally route-driven UI state rather than business state, and it only
    // re-opens a section after navigation so users do not lose context when drilling into a page.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (hasActiveChild) setOpen(true)
  }, [hasActiveChild, pathname])

  const navId = `sidebar-nav-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')}`

  return (
    <div>
      <button
        type="button"
        aria-expanded={open}
        aria-controls={navId}
        onClick={() => setOpen((v) => !v)}
        className={cn(
          'flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold transition',
          hasActiveChild ? 'text-ink' : 'text-muted hover:bg-surface-2 hover:text-ink'
        )}
      >
        <Icon className="h-4 w-4 shrink-0" />
        <span className="flex-1 text-left">{label}</span>
        <ChevronDown
          className={cn('h-3.5 w-3.5 shrink-0 transition-transform duration-200', open && 'rotate-180')}
        />
      </button>
      {open ? (
        <nav id={navId} className="ml-3 mt-1 space-y-0.5 border-l border-line pl-3">
          {items.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium transition',
                  isActive
                    ? 'bg-brand text-on-brand shadow-subtle'
                    : 'text-muted hover:bg-surface-2 hover:text-ink'
                )
              }
            >
              <item.icon className="h-3.5 w-3.5" />
              {item.label}
            </NavLink>
          ))}
        </nav>
      ) : null}
    </div>
  )
}

export function Sidebar() {
  const { role } = useRole()
  const metadatappEnabled = useFeatureFlag('metadatapp-feat.enabled')
  const nonMetadatappEnabled = useFeatureFlag('non-metadatapp-feat.enabled')
  const zefixEnabled = useFeatureFlag('zefix.enabled')
  const auditEnabled = useFeatureFlag('feature.auditLogs')
  const observatoryEnabled = useFeatureFlag('feature.observatory')
  const atmpEnabled = useFeatureFlag('atmp.enabled')
  const dvcNamEnabled = useFeatureFlag('dvc.namVcg.enabled')
  const cagesEnabled = useFeatureFlag('cages.enabled')
  const connectedAppsEnabled = useFeatureFlag('feature.connectedApps')
  const importEnabled = useFeatureFlag('import.enabled')
  const dataEntryEnabled = useFeatureFlag('dataEntry.enabled')
  const allowAdmin = isAdmin(role)
  const curationWorkflowEnabled = useFeatureFlag('feature.curationWorkflow.enabled')

 // ── 1. Research & Experiments ────────────────────────────────────────────
// Core scientific workflow: ISA objects, data ingestion, curation.
// Investigations and metadata tools are under MetaGuard.
const researchItems: NavItem[] = [
  ...(metadatappEnabled
    ? [{ to: '/investigations', label: 'Investigations', icon: FolderKanban }]
    : []),
  { to: '/studies', label: 'Studies', icon: FlaskConical },
  { to: '/assays', label: 'Assays', icon: ClipboardList },
  { to: '/datasets', label: 'Datasets', icon: Database },
  { to: '/smart-import', label: 'Import & Curation Hub', icon: Sparkles },
  ...(metadatappEnabled && importEnabled
    ? [{ to: '/import/csv', label: 'Import Data', icon: Upload }]
    : []),
  ...(metadatappEnabled && dataEntryEnabled
    ? [{ to: '/data-entry', label: 'Data Entry', icon: LayoutGrid }]
    : []),
  ...(metadatappEnabled && curationWorkflowEnabled
    ? [{ to: '/curation', label: 'Data Curation', icon: Filter }]
    : []),
  { to: '/assistant', label: 'AI Assistant', icon: Bot },
]

// ── 2. Subjects & Bio-Resources ──────────────────────────────────────────
// Biological entities and containment: subjects, cages, genetic lines.
// Cages is under MetaGuard + cages flag. Zefix items shown with zefixEnabled.
const subjectsItems: NavItem[] = [
  { to: '/subjects', label: 'Subjects', icon: Users },
  { to: '/samples', label: 'Samples', icon: TestTube2 },
  ...(metadatappEnabled && cagesEnabled
    ? [{ to: '/cages', label: 'Cages', icon: LayoutGrid }]
    : []),
  ...(zefixEnabled
    ? [
        { to: '/zefix/batches', label: 'Batches', icon: Layers },
        { to: '/zefix/lines', label: 'Lines', icon: Dna },
        { to: '/zefix/location', label: 'Housing Management', icon: MapPinned },
      ]
    : []),
]

  // ── 3. Operations & Environment ──────────────────────────────────────────
  // Physical facility monitoring, hardware, and housing infrastructure.
  // TP routes are under NonMetaGuard; zefix room/system/alert items use zefixEnabled.
  const operationsItems: NavItem[] = [
    { to: '/calendar', label: 'Calendar', icon: CalendarDays },
    ...(nonMetadatappEnabled
      ? [
          { to: '/tp', label: 'Operations', icon: Activity },
          ...(observatoryEnabled
            ? [{ to: '/observatory', label: 'Observatory', icon: Aperture }]
            : []),
          { to: '/tp/map', label: 'Facility Map', icon: MapPinned },
          { to: '/tp/sensors', label: 'Sensors', icon: Radar },
          { to: '/tp/conditions', label: 'Conditions', icon: ThermometerSun },
          { to: '/tp/alarms', label: 'Alarms', icon: ShieldAlert },
        ]
      : []),
    ...(zefixEnabled
      ? [
          { to: '/zefix/rooms', label: 'Rooms', icon: Building2 },
          { to: '/zefix/systems', label: 'Systems', icon: ThermometerSun },
          { to: '/zefix/alerts', label: 'Zefix Alerts', icon: ShieldAlert },
        ]
      : []),
  ]

  // ── 4. Data Intelligence ─────────────────────────────────────────────────
  // Advanced processing, analytics, compliance, and specialized modules.
  // TP/DVC items are under NonMetaGuard; ZEFIX Overview uses zefixEnabled.
  const intelligenceItems: NavItem[] = [
    ...(nonMetadatappEnabled
      ? [
          { to: '/dvc/metadata-fusion', label: 'Metadata Fusion', icon: Network },
          { to: '/hcm/comparison', label: 'HCM Comparison', icon: LineChart },
          { to: '/dvc/analytics', label: 'Analytics Hub', icon: Zap },
          { to: '/dvc/activity', label: 'Activity Explorer', icon: LineChart },
          ...(atmpEnabled ? [{ to: '/atmp', label: 'ATMP', icon: Dna }] : []),
          ...(dvcNamEnabled ? [{ to: '/nam-vcg', label: 'NAM-VCG', icon: ShieldCheck }] : []),
          ...(dvcNamEnabled ? [{ to: '/fair-navigator', label: 'FAIR Navigator', icon: ShieldCheck }] : []),
        ]
      : []),
    ...(zefixEnabled ? [{ to: '/zefix', label: 'ZEFIX Overview', icon: ActivitySquare }] : []),
    ...(metadatappEnabled ? [{ to: '/metadata', label: 'Metadata Catalog', icon: BookOpenCheck }] : []),
    ...(metadatappEnabled ? [{ to: '/export', label: 'FAIR Export', icon: Download }] : []),
  ]

  // ── 5. Connected Apps ────────────────────────────────────────────────────
  // Integrations with external platforms. /connected-apps is under MetaGuard.
  const connectedAppsItems: NavItem[] = [
    ...(metadatappEnabled && connectedAppsEnabled
      ? [{ to: '/connected-apps', label: 'App Directory', icon: Network }]
      : []),
  ]

  // ── 6. System Administration ─────────────────────────────────────────────
  // Global configuration, security, and developer tools.
  const adminItems: NavItem[] = [
    ...(allowAdmin ? [{ to: '/users', label: 'Users', icon: Users }] : []),
    ...(allowAdmin ? [{ to: '/organizations', label: 'Organizations', icon: Building2 }] : []),
    ...(allowAdmin && auditEnabled ? [{ to: '/audit', label: 'Audit Log', icon: ShieldCheck }] : []),
    ...(allowAdmin ? [{ to: '/settings/api-keys', label: 'API Keys', icon: ShieldCheck }] : []),
    ...(allowAdmin ? [{ to: '/settings/AI-providers', label: 'AI Providers', icon: Sparkles }] : []),
    { to: '/settings/appearance', label: 'Appearance', icon: SlidersHorizontal },
    { to: '/settings/profile', label: 'Profile', icon: Users },
    ...(allowAdmin ? [{ to: '/admin/feature-flags', label: 'Flag Studio', icon: Sparkles }] : []),
    { to: '/settings/docs', label: 'Documentation', icon: BookOpenCheck },
  ]

  return (
    <aside className="hidden w-64 flex-col border-r border-line bg-surface/90 px-4 py-6 lg:flex">
      <Logo />

      <div className="mt-8 flex-1 space-y-1 overflow-y-auto">
        <SidebarSection label="Research & Studies" icon={FlaskConical} items={researchItems} />
        <SidebarSection label="Subjects & Bio-Resources" icon={Users} items={subjectsItems} />
        {operationsItems.length > 0 ? (
          <SidebarSection label="Operations & Environment" icon={Activity} items={operationsItems} />
        ) : null}
        {intelligenceItems.length > 0 ? (
          <SidebarSection label="Data Intelligence & FAIR" icon={Zap} items={intelligenceItems} />
        ) : null}
        {connectedAppsItems.length > 0 ? (
          <SidebarSection label="Connected Apps" icon={Network} items={connectedAppsItems} />
        ) : null}
        <SidebarSection label="System Administration" icon={SlidersHorizontal} items={adminItems} />
      </div>

      <div className="mt-4 rounded-2xl border border-line bg-surface-2 p-4 text-xs text-muted">
        <p className="font-semibold text-ink">Metadatapp</p>
        <p className="mt-2">All-in-One Metadata Management Platform.</p>
      </div>
    </aside>
  )
}
