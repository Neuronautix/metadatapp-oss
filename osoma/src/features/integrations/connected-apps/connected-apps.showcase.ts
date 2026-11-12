import type { AppCode, ConnectedApp } from './connected-apps.types.ts'

export type ConnectedAppDirection = 'Inbound' | 'Outbound' | 'Bidirectional' | 'Validation' | 'Proxy'

export type ConnectedAppShowcase = {
    code: AppCode
    name: string
    role: string
    direction: ConnectedAppDirection
    summary: string
    liveSignal: string
    metadatappObjects: string[]
    features: string[]
    websiteAngle: string
}

export const connectedAppShowcases: ConnectedAppShowcase[] = [
    {
        code: 'elabftw',
        name: 'eLabFTW',
        role: 'Electronic lab notebook',
        direction: 'Bidirectional',
        summary: 'Synchronizes protocol and experiment metadata between the lab notebook and Metadatapp studies.',
        liveSignal: 'Experiment updates, protocol links, and notebook references are exchanged through backend sync jobs.',
        metadatappObjects: ['Studies', 'Experiments', 'Protocols', 'External links'],
        features: ['Import eLabFTW experiments', 'Push Metadatapp records back to eLabFTW', 'Preserve notebook provenance in study metadata'],
        websiteAngle: 'Researchers keep using their notebook while Metadatapp turns the record into FAIR-ready metadata.',
    },
    {
        code: 'softmouse',
        name: 'SoftMouse',
        role: 'Mouse colony management',
        direction: 'Inbound',
        summary: 'Imports colony, cage, animal, and study data into Metadatapp so biological context follows the experiment.',
        liveSignal: 'SoftMouse animal, cage, and study identifiers are mapped onto Metadatapp resources.',
        metadatappObjects: ['Subjects', 'Cages', 'Studies', 'Weight measurements'],
        features: ['Import subjects and cage assignments', 'Import study/task data', 'Maintain SoftMouse external identifiers'],
        websiteAngle: 'Colony management data becomes structured research metadata instead of staying isolated in animal facility software.',
    },
    {
        code: 'fair3r',
        name: 'FAIR3R',
        role: 'FAIR data repository',
        direction: 'Outbound',
        summary: 'Publishes investigations and studies as repository-ready FAIR datasets with DataCite/FDF exchange payloads.',
        liveSignal: 'Metadatapp validates repository payloads, lists datasets, shows dataset details, and can push investigations.',
        metadatappObjects: ['Investigations', 'Studies', 'Subjects', 'FAIR datasets'],
        features: ['FAIR schema exchange', 'Dataset validation', 'Repository publication workflow'],
        websiteAngle: 'FAIR publication is built into the metadata workflow instead of being a separate end-of-project chore.',
    },
    {
        code: 'osf',
        name: 'OSF',
        role: 'Open science repository',
        direction: 'Inbound',
        summary: 'Connects repository projects and external resource links to Metadatapp studies.',
        liveSignal: 'Connection validation confirms user context before repository metadata is linked into studies.',
        metadatappObjects: ['Studies', 'Repository links', 'Datasets'],
        features: ['Test OSF connection', 'Import project metadata', 'Attach repository provenance'],
        websiteAngle: 'Open science workspace metadata is connected to the same graph as protocols, animals, assays, and datasets.',
    },
    {
        code: 'preclinicaltrials',
        name: 'PreclinicalTrials.eu',
        role: 'Preclinical study registry',
        direction: 'Inbound',
        summary: 'Imports public preclinical trial protocol metadata into investigations.',
        liveSignal: 'The integration lists public protocols and maps registry metadata into Metadatapp investigations.',
        metadatappObjects: ['Investigations', 'Study protocols', 'Registry links'],
        features: ['Test registry connection', 'Import non-embargoed protocols', 'Keep protocol registration provenance'],
        websiteAngle: 'Registered protocol metadata can become the starting point for a complete FAIR investigation record.',
    },
    {
        code: 'tecniplast',
        name: 'Tecniplast DVC',
        role: 'Home-cage monitoring and DVC analytics',
        direction: 'Bidirectional',
        summary: 'Connects DVC metrics, cage activity extracts, circadian summaries, and facility context to Metadatapp.',
        liveSignal: 'DVC tasks submit extraction requests, poll task state, download results, and import time-series evidence.',
        metadatappObjects: ['Cages', 'Subjects', 'Variables', 'Time series', 'Circadian metadata'],
        features: ['Metric import', 'DVC task submission', 'ZIP download and data import', 'Activity and circadian dashboards'],
        websiteAngle: 'High-volume home-cage signals are converted into interpretable metadata and reusable evidence.',
    },
    {
        code: 'sensor_agent',
        name: 'Sovereign Sensor Agent',
        role: 'Live environmental sensor proxy',
        direction: 'Proxy',
        summary: 'Streams live Raspberry Pi sensor readings through Metadatapp without exposing devices directly to the frontend.',
        liveSignal: 'Temperature, humidity, and alarm-like observations flow through a backend proxy and MCP read tools.',
        metadatappObjects: ['Sensor readings', 'Alarms', 'Environmental observations'],
        features: ['Live sensor panel', 'Backend-only device access', 'Read-only AI assistant tools'],
        websiteAngle: 'Environmental context can be demonstrated live and tied to study metadata without direct browser-to-device calls.',
    },
    {
        code: 'protocolio',
        name: 'protocols.io',
        role: 'Protocol repository',
        direction: 'Validation',
        summary: 'Validates API credentials for future protocol repository synchronization.',
        liveSignal: 'Metadatapp tests the configured token against the protocols.io user profile endpoint.',
        metadatappObjects: ['Protocols', 'External links'],
        features: ['API token validation', 'User profile confirmation', 'Ready for protocol import extension'],
        websiteAngle: 'Protocol repositories can be connected as trusted external provenance sources.',
    },
    {
        code: 'cedar',
        name: 'CEDAR',
        role: 'Metadata templates',
        direction: 'Validation',
        summary: 'Validates CEDAR API access as a foundation for template-driven metadata capture.',
        liveSignal: 'Metadatapp checks the configured API key against the CEDAR resource API.',
        metadatappObjects: ['Metadata templates', 'Structured forms'],
        features: ['API key validation', 'Template-service readiness', 'Future template import path'],
        websiteAngle: 'Community metadata templates can guide structured capture directly inside the research workflow.',
    },
    {
        code: 'bioportal',
        name: 'BioPortal',
        role: 'Ontology lookup',
        direction: 'Validation',
        summary: 'Validates ontology API access for future terminology lookup and semantic enrichment.',
        liveSignal: 'Metadatapp checks BioPortal credentials through the backend proxy.',
        metadatappObjects: ['Ontology terms', 'Semantic annotations', 'Variables'],
        features: ['API key validation', 'Ontology-service readiness', 'Future controlled-vocabulary lookup'],
        websiteAngle: 'Ontology connections prepare Metadatapp for semantic annotation and stronger interoperability.',
    },
    {
        code: 'nih_cde',
        name: 'NIH CDE Repository',
        role: 'Metadata standardization',
        direction: 'Inbound',
        summary: 'Provides a live browser for the NIH Common Data Elements repository. Users can search CDEs, inspect permissible values, and import elements directly into the local database for use as standardized metadata fields.',
        liveSignal: 'Metadatapp proxies NIH CDE search and element-detail calls in real time. Imported CDEs are stored with their source, version, definition, permissible values, and raw JSON.',
        metadatappObjects: ['CDE elements', 'Variables', 'Form fields', 'Metadata templates'],
        features: ['Live CDE browser with search and pagination', 'Permissible values inspector', 'One-click import to local DB', 'Public API — no token required'],
        websiteAngle: 'NIH CDEs bring clinical-style metadata standardization to preclinical research, enabling semantic alignment across species, cohorts, and data types.',
    },
    {
        code: 'jax_phenome',
        name: 'JAX Phenome (MPD)',
        role: 'Mouse phenotype data repository',
        direction: 'Inbound',
        summary: 'Provides a live browser for the Jackson Laboratory Mouse Phenome Database. Users can search curated mouse phenotype measures and strains for physiology and behavior genetics/genomics research.',
        liveSignal: 'Metadatapp proxies MPD measure search and measure/strain detail calls in real time so the catalogue can be explored without exposing the upstream API to the browser.',
        metadatappObjects: ['Phenotype measures', 'Strains', 'Variables', 'Ontology terms'],
        features: ['Live measure browser with search', 'Strain catalogue', 'Public API — no token required'],
        websiteAngle: 'Curated mouse phenotype data becomes browsable reference metadata for physiology and behavior studies, aligning preclinical variables with an established community resource.',
    },
    {
        code: 'impc',
        name: 'IMPReSS (IMPC)',
        role: 'Standardized phenotyping screens',
        direction: 'Inbound',
        summary: 'Provides a live browser for IMPReSS, the International Mouse Phenotyping Resource of Standardised Screens. Users can search standardized phenotyping procedures (Open Field, Acoustic Startle/PPI, Grip Strength…) and inspect their parameters and ontology mappings for behavioral studies.',
        liveSignal: 'Metadatapp proxies the IMPReSS catalogue in real time so standardized procedures and parameters can be explored without exposing the upstream API to the browser.',
        metadatappObjects: ['Procedures', 'Parameters', 'Variables', 'Ontology terms'],
        features: ['Live procedure browser with search', 'Pipeline catalogue', 'Parameter and ontology inspector', 'Public API — no token required'],
        websiteAngle: 'IMPReSS brings community-agreed standardized phenotyping screens into the research workflow, so behavioral variables align with the same procedures and parameters used across the mouse phenotyping community.',
    },
]

export const metadatappNetConnectedAppsContent = {
    headline: 'Metadatapp connects the research stack into one FAIR metadata graph.',
    subheadline:
        'Protocols, colony data, repositories, live sensors, home-cage analytics, and FAIR publication workflows communicate through Metadatapp instead of staying in separate tools.',
    valueProps: [
        'Backend-mediated integrations keep external credentials and device access away from the browser.',
        'External identifiers are preserved, so every imported record stays traceable to its source system.',
        'FAIR assessment, export, and publication workflows can use connected evidence as soon as it lands in Metadatapp.',
    ],
    interactivePrompts: [
        'Show an eLabFTW protocol becoming a Metadatapp study, then a FAIR3R dataset.',
        'Show SoftMouse animal and cage records flowing into a DVC activity dashboard.',
        'Show CEDAR and BioPortal as semantic services that enrich variables and forms.',
    ],
}

export const findConnectedAppShowcase = (code: AppCode) =>
    connectedAppShowcases.find((item) => item.code === code)

export const mergeConnectedAppShowcases = (apps: ConnectedApp[] = []) =>
    connectedAppShowcases.map((showcase) => {
        const app = apps.find((item) => item.code === showcase.code)
        return { showcase, app }
    })
