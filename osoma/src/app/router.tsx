import { createBrowserRouter, Navigate, Outlet, useParams } from 'react-router-dom'
import type { ReactNode } from 'react'
import { AppLayout } from './layout/AppLayout.tsx'
import { AuthCallbackPage } from '@/features/system/auth/AuthCallbackPage.tsx'
import { AuthGuard } from '@/features/system/auth/AuthGuard.tsx'
import { DashboardPage } from '@/features/system/dashboard/DashboardPage.tsx'
import { OpsModePage } from '@/features/system/ops/OpsModePage.tsx'
import { UsersPage } from '@/features/system/users/UsersPage.tsx'
import { UserCreatePage } from '@/features/system/users/UserCreatePage.tsx'
import { UserViewPage } from '@/features/system/users/UserViewPage.tsx'
import { UserMetaEditPage } from '@/features/system/users/UserMetaEditPage.tsx'
import {
  OrganizationsAliasDetailRoute,
  OrganizationsAliasEditRoute,
  OrganizationsAliasListRoute,
} from '@/features/system/organizations/OrganizationsAliasRoutes.tsx'
import { CalendarPage } from '@/features/system/calendar/CalendarPage.tsx'
import { AuditPage } from '@/features/system/audit/AuditPage.tsx'
import { OperationsOverviewPage } from '@/features/integrations/tecniplast/facilities/OperationsOverviewPage.tsx'
import { FacilityMapPage } from '@/features/integrations/tecniplast/locations/FacilityMapPage.tsx'
import { AlarmsPage } from '@/features/integrations/tecniplast/alarms/AlarmsPage.tsx'
import { ConditionsPage } from '@/features/integrations/tecniplast/conditions/ConditionsPage.tsx'
import { RoomDetailPage } from '@/features/integrations/tecniplast/locations/RoomDetailPage.tsx'
import { CagesPage } from '@/features/core/cages/CagesPage.tsx'
import { CageDetailPage } from '@/features/core/cages/CageDetailPage.tsx'
import { SensorsPage } from '@/features/integrations/tecniplast/sensors/SensorsPage.tsx'
import { SensorDetailPage } from '@/features/integrations/tecniplast/sensors/SensorDetailPage.tsx'
import { AppearanceStudio } from '@/features/system/settings/AppearanceStudio.tsx'
import { FeatureFlagStudioPage } from '@/features/system/admin/FeatureFlagStudioPage.tsx'
import { ObservatoryPage } from '@/features/integrations/tecniplast/observatory/ObservatoryPage.tsx'
import { Feature } from '@/feature-flags/Feature.tsx'
import { AtmpStudiesPage } from '@/features/integrations/atmp/AtmpStudiesPage.tsx'
import { AtmpStudyDetailPage } from '@/features/integrations/atmp/AtmpStudyDetailPage.tsx'
import { AtmpStudyFormPage } from '@/features/integrations/atmp/AtmpStudyFormPage.tsx'
import { AtmpFeatureDisabledPage } from '@/features/integrations/atmp/components/AtmpFeatureDisabledPage.tsx'
import { DvcFeatureDisabledPage } from '@/features/integrations/tecniplast/dvc/components/DvcFeatureDisabledPage.tsx'
import { NamVcgWorkbenchPage } from '@/features/integrations/tecniplast/dvc/NamVcgWorkbenchPage.tsx'
import { FairNavigatorPage } from '@/features/integrations/tecniplast/dvc/FairNavigatorPage.tsx'
import { ActivityExplorerPage } from '@/features/integrations/tecniplast/dvc/ActivityExplorerPage.tsx'
import { AnalyticsHubPage } from '@/features/integrations/tecniplast/dvc/AnalyticsHubPage.tsx'
import { CagesFeatureDisabledPage } from '@/features/core/cages/CagesFeatureDisabledPage.tsx'
import { ConnectedAppsPage } from '@/features/integrations/connected-apps/ConnectedAppsPage.tsx'
import { ConnectedAppDetailPage } from '@/features/integrations/connected-apps/ConnectedAppDetailPage.tsx'
import { ConnectedAppsShowcasePage } from '@/features/integrations/connected-apps/ConnectedAppsShowcasePage.tsx'
import { ReferenceHubPage } from '@/features/reference-hub/ReferenceHubPage.tsx'
import { ReferenceComparePage } from '@/features/reference-hub/ReferenceComparePage.tsx'
import { GuidelinesHubPage } from '@/features/guidelines/GuidelinesHubPage.tsx'
import { GuidelineDetailPage } from '@/features/guidelines/GuidelineDetailPage.tsx'
import { MappIndexPage } from '@/features/system/metadatapp/MappIndexPage.tsx'
import { MappResourceListPage } from '@/features/system/metadatapp/MappResourceListPage.tsx'
import { MappResourceDetailPage } from '@/features/system/metadatapp/MappResourceDetailPage.tsx'
import { MappResourceEditPage } from '@/features/system/metadatapp/MappResourceEditPage.tsx'
import { MappResourceCreatePage } from '@/features/system/metadatapp/MappResourceCreatePage.tsx'
import { SettingsLayout } from '@/features/system/settings/SettingsLayout.tsx'
import { ProfilePage } from '@/features/system/settings/ProfilePage.tsx'
import { DocumentationPage } from '@/features/system/settings/DocumentationPage.tsx'
import { ApiKeysPage } from '@/features/system/settings/ApiKeysPage.tsx'
import { AiProvidersPage } from '@/features/system/settings/AiProvidersPage.tsx'
import { CsvImportPage } from '@/features/system/import/CsvImportPage.tsx'
import { DataEntryGridPage } from '@/features/system/data-entry/DataEntryGridPage.tsx'
import { ZefixDashboard } from '@/features/zefix/ZefixDashboard.tsx'
import { LinesPage } from '@/features/zefix/LinesPage.tsx'
import { LineDetailPage } from '@/features/zefix/LineDetailPage.tsx'
import { BatchesPage } from '@/features/zefix/BatchesPage.tsx'
import { BatchDetailPage } from '@/features/zefix/BatchDetailPage.tsx'
import { SystemsPage } from '@/features/zefix/SystemsPage.tsx'
import { RoomsPage } from '@/features/zefix/RoomsPage.tsx'
import { AlertsPage } from '@/features/zefix/AlertsPage.tsx'
import { LocationExplorer } from '@/features/zefix/LocationExplorer.tsx'
import { DataCurationModule } from '@/features/curation/DataCurationModule.tsx'
import { SmartImportHubPage } from '@/features/curation/SmartImportHubPage.tsx'
import { TemplateCrosswalkStudioPage } from '@/features/template-crosswalks/TemplateCrosswalkStudioPage.tsx'
import { DvcMetadataDashboard } from '@/features/dvc/DvcMetadataDashboard.tsx'
import { ExportPage } from '@/features/export/ExportPage.tsx'
import { AssistantPage } from '@/features/ai-assistant'
import { WellFairPage } from '@/features/wellfair/WellFairPage.tsx'
import { HcmMetadataFormPage } from '@/features/hcm-metadata-form/HcmMetadataFormPage.tsx'
import { DatasetsAliasRoute } from '@/features/system/routing/LegacyMetaAliases.tsx'
import {
  ProjectsAliasListRoute,
  ProjectsAliasNewRoute,
  ProjectsAliasDetailRoute,
  ProjectsAliasEditRoute,
  ProjectsAliasAnimalsRoute,
  ProjectsAliasAnimalDetailRoute,
} from '@/features/core/investigations/ProjectsAliasRoutes.tsx'
import { StudiesPage } from '@/features/core/studies/StudiesPage.tsx'
import { StudyViewPage } from '@/features/core/studies/StudyViewPage.tsx'
import { StudyEditPage } from '@/features/core/studies/StudyEditPage.tsx'
import { SamplesPage } from '@/features/core/samples/SamplesPage.tsx'
import { SampleViewPage } from '@/features/core/samples/SampleViewPage.tsx'
import { SampleEditPage } from '@/features/core/samples/SampleEditPage.tsx'
import { SubjectsPage } from '@/features/core/subjects/SubjectsPage.tsx'
import { SubjectViewPage } from '@/features/core/subjects/SubjectViewPage.tsx'
import { SubjectEditPage } from '@/features/core/subjects/SubjectEditPage.tsx'
import { AssaysPage } from '@/features/core/assays/AssaysPage.tsx'
import { AssayViewPage } from '@/features/core/assays/AssayViewPage.tsx'
import { AssayEditPage } from '@/features/core/assays/AssayEditPage.tsx'
import { InvestigationsPage } from '@/features/core/investigations/InvestigationsPage.tsx'
import { NewInvestigationPage } from '@/features/core/investigations/NewInvestigationPage.tsx'
import { InvestigationViewPage } from '@/features/core/investigations/InvestigationViewPage.tsx'
import { InvestigationEditPage } from '@/features/core/investigations/InvestigationEditPage.tsx'
import { InvestigationAssignAnimalsPage } from '@/features/core/investigations/InvestigationAssignAnimalsPage.tsx'
import { AnimalDetailPage } from '@/features/core/animals/AnimalDetailPage.tsx'
import { useRole } from '@/app/role-context.tsx'
import { isAdmin } from '@/lib/rbac.ts'

const NotFound = () => (
  <div className="rounded-2xl border border-line bg-surface p-10 shadow-subtle">
    <h1 className="font-display text-2xl">Page not found</h1>
    <p className="mt-2 text-sm text-slate-500">
      This area is still being routed. Try Investigations, Studies, Samples, or Calendar.
    </p>
  </div>
)

const MetaGuard = ({ children, fallback }: { children?: ReactNode; fallback?: ReactNode }) => (
  <Feature flag="metadatapp-feat.enabled" fallback={fallback ?? <Navigate to="/" replace />}>
    {children ?? <Outlet />}
  </Feature>
)

const NonMetaGuard = ({ children, fallback }: { children?: ReactNode; fallback?: ReactNode }) => (
  <Feature flag="non-metadatapp-feat.enabled" fallback={fallback ?? <Navigate to="/" replace />}>
    {children ?? <Outlet />}
  </Feature>
)

const AdminGuard = ({ children, fallback }: { children?: ReactNode; fallback?: ReactNode }) => {
  const { role } = useRole()

  if (!isAdmin(role)) {
    return fallback ?? <Navigate to="/" replace />
  }

  return <>{children ?? <Outlet />}</>
}

const TpCageAliasRoute = () => {
  const { id } = useParams()
  return <Navigate to={id ? `/cages/${id}` : '/cages'} replace />
}

export const router = createBrowserRouter([
  {
    path: '/auth/callback',
    element: <AuthCallbackPage />,
  },
  {
    path: '/__ops',
    element: <OpsModePage />,
  },
  {
    path: '/',
    element: (
      <AuthGuard>
        <AppLayout />
      </AuthGuard>
    ),
    children: [
      { index: true, element: <DashboardPage /> },

      { path: 'studies', element: <StudiesPage /> },
      { path: 'studies/new', element: <Navigate to="/metadata/experiments/new" replace /> },
      { path: 'studies/:studyId', element: <StudyViewPage /> },
      { path: 'studies/:studyId/edit', element: <StudyEditPage /> },
      { path: 'projects', element: <ProjectsAliasListRoute /> },
      { path: 'projects/new', element: <ProjectsAliasNewRoute /> },
      { path: 'projects/:projectId', element: <ProjectsAliasDetailRoute /> },
      { path: 'projects/:projectId/edit', element: <ProjectsAliasEditRoute /> },
      { path: 'projects/:projectId/animals', element: <ProjectsAliasAnimalsRoute /> },
      { path: 'projects/:projectId/animals/:animalId', element: <ProjectsAliasAnimalDetailRoute /> },
      { path: 'samples', element: <SamplesPage /> },
      { path: 'samples/new', element: <Navigate to="/metadata/weight_measurements/new" replace /> },
      { path: 'samples/:sampleId', element: <SampleViewPage /> },
      { path: 'samples/:sampleId/edit', element: <SampleEditPage /> },
      { path: 'subjects', element: <SubjectsPage /> },
      { path: 'subjects/new', element: <Navigate to="/metadata/subjects/new" replace /> },
      { path: 'subjects/:subjectId', element: <SubjectViewPage /> },
      { path: 'subjects/:subjectId/edit', element: <SubjectEditPage /> },
      { path: 'assays', element: <AssaysPage /> },
      { path: 'assays/new', element: <Navigate to="/metadata/procedures/new" replace /> },
      { path: 'assays/:assayId', element: <AssayViewPage /> },
      { path: 'assays/:assayId/edit', element: <AssayEditPage /> },
      { path: 'datasets', element: <DatasetsAliasRoute /> },
      { path: 'datasets/:datasetId', element: <DatasetsAliasRoute /> },
      { path: 'datasets/:datasetId/edit', element: <DatasetsAliasRoute /> },
      { path: 'users', element: <UsersPage /> },
      { path: 'users/new', element: <UserCreatePage /> },
      { path: 'users/:userId', element: <UserViewPage /> },
      { path: 'users/:userId/edit', element: <UserMetaEditPage /> },
      { path: 'organizations', element: <OrganizationsAliasListRoute /> },
      { path: 'organizations/:organizationId', element: <OrganizationsAliasDetailRoute /> },
      { path: 'organizations/:organizationId/edit', element: <OrganizationsAliasEditRoute /> },
      { path: 'calendar', element: <CalendarPage /> },
      { path: 'assistant', element: <AssistantPage /> },
      { path: 'ai', element: <AssistantPage /> },
      { path: 'audit', element: <AdminGuard><AuditPage /></AdminGuard> },
      { path: 'smart-import', element: <SmartImportHubPage /> },
      { path: 'hcm/metadata-form', element: <HcmMetadataFormPage /> },
      {
        path: 'curation/*',
        element: <Feature flag="feature.curationWorkflow.enabled" fallback={<Navigate to="/investigations" replace />}><DataCurationModule /></Feature>,
      },
      {
        path: 'template-crosswalks',
        element: <Feature flag="feature.curationWorkflow.enabled" fallback={<Navigate to="/investigations" replace />}><TemplateCrosswalkStudioPage /></Feature>,
      },
      { path: 'zefix', element: <ZefixDashboard /> },
      { path: 'zefix/lines', element: <LinesPage /> },
      { path: 'zefix/lines/:lineID', element: <LineDetailPage /> },
      { path: 'zefix/batches', element: <BatchesPage /> },
      { path: 'zefix/batches/:batchID', element: <BatchDetailPage /> },
      { path: 'zefix/systems', element: <SystemsPage /> },
      { path: 'zefix/rooms', element: <RoomsPage /> },
      { path: 'zefix/alerts', element: <AlertsPage /> },
      { path: 'zefix/location', element: <LocationExplorer /> },
      { path: 'admin/feature-flags', element: <AdminGuard><FeatureFlagStudioPage /></AdminGuard> },
      {
        path: 'settings',
        element: <SettingsLayout />,
        children: [
          { index: true, element: <Navigate to="/settings/profile" replace /> },
          { path: 'profile', element: <ProfilePage /> },
          { path: 'ai-provider', caseSensitive: true, element: <Navigate to="/settings/AI-providers" replace /> },
          { path: 'ai-proveider', caseSensitive: true, element: <Navigate to="/settings/AI-providers" replace /> },
          { path: 'api-keys', element: <AdminGuard fallback={<Navigate to="/settings/profile" replace />}><ApiKeysPage /></AdminGuard> },
          { path: 'AI-providers', caseSensitive: true, element: <AdminGuard fallback={<Navigate to="/settings/profile" replace />}><AiProvidersPage /></AdminGuard> },
          { path: 'ai-providers', caseSensitive: true, element: <Navigate to="/settings/AI-providers" replace /> },
          { path: 'appearance', element: <AppearanceStudio /> },
          { path: 'docs', element: <DocumentationPage /> },
          { path: '*', element: <Navigate to="/settings/profile" replace /> },
        ],
      },

      {
        element: <MetaGuard />,
        children: [
          { path: 'wellfair', element: <WellFairPage /> },
          { path: 'investigations', element: <InvestigationsPage /> },
          { path: 'investigations/new', element: <NewInvestigationPage /> },
          { path: 'investigations/:investigationId', element: <InvestigationViewPage /> },
          { path: 'investigations/:investigationId/edit', element: <InvestigationEditPage /> },
          { path: 'investigations/:investigationId/animals', element: <InvestigationAssignAnimalsPage /> },
          { path: 'investigations/:investigationId/animals/:animalId', element: <AnimalDetailPage /> },
          { path: 'export', element: <ExportPage /> },
          { path: 'metadata', element: <MappIndexPage /> },
          { path: 'metadata/:resource', element: <MappResourceListPage /> },
          { path: 'metadata/:resource/new', element: <MappResourceCreatePage /> },
          { path: 'metadata/:resource/:id', element: <MappResourceDetailPage /> },
          { path: 'metadata/:resource/:id/edit', element: <MappResourceEditPage /> },
          {
            path: 'cages',
            element: <Feature flag="cages.enabled" fallback={<CagesFeatureDisabledPage />}><CagesPage /></Feature>,
          },
          {
            path: 'cages/new',
            element: <Feature flag="cages.enabled" fallback={<CagesFeatureDisabledPage />}><Navigate to="/metadata/cages/new" replace /></Feature>,
          },
          {
            path: 'cages/:id',
            element: <Feature flag="cages.enabled" fallback={<CagesFeatureDisabledPage />}><CageDetailPage /></Feature>,
          },
          {
            path: 'connected-apps',
            element: <Feature flag="feature.connectedApps" fallback={<Navigate to="/investigations" replace />}><ConnectedAppsPage /></Feature>,
          },
          {
            path: 'connected-apps/showcase',
            element: <Feature flag="feature.connectedApps" fallback={<Navigate to="/investigations" replace />}><ConnectedAppsShowcasePage /></Feature>,
          },
          {
            path: 'guidelines',
            element: <Feature flag="feature.guidelinesReporting" fallback={<Navigate to="/investigations" replace />}><GuidelinesHubPage /></Feature>,
          },
          {
            path: 'guidelines/:templateId',
            element: <Feature flag="feature.guidelinesReporting" fallback={<Navigate to="/investigations" replace />}><GuidelineDetailPage /></Feature>,
          },
          {
            path: 'reference-hub',
            element: <Feature flag="feature.referenceHub" fallback={<Navigate to="/investigations" replace />}><ReferenceHubPage /></Feature>,
          },
          {
            path: 'reference-hub/compare',
            element: <Feature flag="feature.referenceHub" fallback={<Navigate to="/investigations" replace />}><ReferenceComparePage /></Feature>,
          },
          {
            path: 'connected-apps/:appId',
            element: <Feature flag="feature.connectedApps" fallback={<Navigate to="/investigations" replace />}><ConnectedAppDetailPage /></Feature>,
          },
          {
            path: 'import/csv',
            element: <Feature flag="import.enabled" fallback={<Navigate to="/investigations" replace />}><CsvImportPage /></Feature>,
          },
          {
            path: 'data-entry',
            element: <Feature flag="dataEntry.enabled" fallback={<Navigate to="/investigations" replace />}><DataEntryGridPage /></Feature>,
          },
        ],
      },

      {
        element: <NonMetaGuard />,
        children: [
          { path: 'tp', element: <OperationsOverviewPage /> },
          {
            path: 'observatory',
            element: <Feature flag="feature.observatory" fallback={<Navigate to="/tp" replace />}><ObservatoryPage /></Feature>,
          },
          { path: 'tp/map', element: <FacilityMapPage /> },
          { path: 'tp/alarms', element: <AlarmsPage /> },
          { path: 'tp/conditions', element: <ConditionsPage /> },
          { path: 'tp/rooms/:roomId', element: <RoomDetailPage /> },
          { path: 'tp/cages/:id', element: <TpCageAliasRoute /> },
          { path: 'tp/subjects/:subjectId', element: <SubjectViewPage /> },
          { path: 'tp/sensors', element: <SensorsPage /> },
          { path: 'tp/sensors/:sensorId', element: <SensorDetailPage /> },
          {
            path: 'atmp',
            element: <Feature flag="atmp.enabled" fallback={<AtmpFeatureDisabledPage />}><AtmpStudiesPage /></Feature>,
          },
          {
            path: 'atmp/new',
            element: (
              <Feature flag="atmp.enabled" fallback={<AtmpFeatureDisabledPage />}>
                <Feature flag="atmp.enabled" fallback={<AtmpFeatureDisabledPage mode="form" />}>
                  <AtmpStudyFormPage mode="create" />
                </Feature>
              </Feature>
            ),
          },
          {
            path: 'atmp/:investigationID',
            element: <Feature flag="atmp.enabled" fallback={<AtmpFeatureDisabledPage />}><AtmpStudyDetailPage /></Feature>,
          },
          {
            path: 'atmp/:investigationID/edit',
            element: (
              <Feature flag="atmp.enabled" fallback={<AtmpFeatureDisabledPage />}>
                <Feature flag="atmp.enabled" fallback={<AtmpFeatureDisabledPage mode="form" />}>
                  <AtmpStudyFormPage mode="edit" />
                </Feature>
              </Feature>
            ),
          },
          {
            path: 'nam-vcg',
            element: (
              <Feature flag="dvc.namVcg.enabled" fallback={<DvcFeatureDisabledPage mode="nam" />}>
                <NamVcgWorkbenchPage />
              </Feature>
            ),
          },
          {
            path: 'fair-navigator',
            element: (
              <Feature flag="dvc.namVcg.enabled" fallback={<DvcFeatureDisabledPage mode="nam" />}>
                <FairNavigatorPage />
              </Feature>
            ),
          },
          {
            path: 'dvc/activity',
            element: <ActivityExplorerPage />,
          },
          {
            path: 'dvc/analytics',
            element: <AnalyticsHubPage />,
          },
          {
            path: 'dvc/metadata-fusion',
            element: <DvcMetadataDashboard />,
          },
          {
            path: 'hcm/comparison',
            element: <DvcMetadataDashboard mode="hcm" />,
          },
        ],
      },

      { path: '*', element: <NotFound /> },
    ],
  },
])
