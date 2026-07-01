import { usersHandlers } from './handlers/users.ts'
import { investigationsHandlers } from './handlers/investigations.ts'
import { studiesHandlers } from './handlers/studies.ts'
import { samplesHandlers } from './handlers/samples.ts'
import { subjectsHandlers } from './handlers/subjects.ts'
import { assaysHandlers } from './handlers/assays.ts'
import { datasetsHandlers } from './handlers/datasets.ts'
import { organizationsHandlers } from './handlers/organizations.ts'
import { calendarHandlers } from './handlers/calendar.ts'
import { auditHandlers } from './handlers/audit.ts'
import { searchHandlers } from './handlers/search.ts'
import { tecniplastHandlers } from './handlers/tecniplast.ts'
import { animalsHandlers } from './handlers/animals.ts'
import { featureFlagsHandlers } from './handlers/featureFlags.ts'
import { scenarioHandlers } from './handlers/scenario.ts'
import { atmpHandlers } from './handlers/atmp.ts'
import { dvcHandlers } from './handlers/dvc.ts'
import { cagesHandlers } from './handlers/cages.ts'
import { dvcIntegrationHandlers } from './handlers/dvcIntegration.ts'
import { connectedAppsHandlers } from './handlers/connectedApps.ts'
import { subjectCurationHandlers } from './handlers/subjectCuration.ts'
import { lookupHandlers } from './handlers/lookups.ts'
import { demoSensorsHandlers } from './handlers/demoSensors.ts'
import { aiAssistantHandlers } from './handlers/aiAssistant.ts'
import { aiProviderHandlers } from './handlers/aiProvider.ts'
import { canonicalFormsHandlers } from './handlers/canonical-forms.ts'
import { referenceHubHandlers } from './handlers/referenceHub.ts'
import { guidelinesHandlers } from './handlers/guidelines.ts'
import { http, passthrough } from 'msw'

export const handlers = [
  http.all('*', ({ request }) => {
    if (request.headers.get('x-bypass-mock') === 'true') {
      return passthrough()
    }
  }),
  ...investigationsHandlers,
  ...studiesHandlers,
  ...samplesHandlers,
  ...subjectsHandlers,
  ...assaysHandlers,
  ...datasetsHandlers,
  ...usersHandlers,
  ...organizationsHandlers,
  ...calendarHandlers,
  ...auditHandlers,
  ...searchHandlers,
  ...tecniplastHandlers,
  ...animalsHandlers,
  ...featureFlagsHandlers,
  ...scenarioHandlers,
  ...atmpHandlers,
  ...dvcHandlers,
  ...cagesHandlers,
  ...dvcIntegrationHandlers,
  ...connectedAppsHandlers,
  ...subjectCurationHandlers,
  ...demoSensorsHandlers,
  ...aiAssistantHandlers,
  ...aiProviderHandlers,
  ...lookupHandlers,
  ...canonicalFormsHandlers,
  ...referenceHubHandlers,
  ...guidelinesHandlers,
]
