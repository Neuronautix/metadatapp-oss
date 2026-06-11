export type AppCode = 'elabftw' | 'fair3r' | 'osf' | 'protocolio' | 'preclinicaltrials' | 'cedar' | 'bioportal' | 'tecniplast' | 'sensor_agent'

export interface ConnectedApp {
    id: string
    '@id': string
    name: string
    code: AppCode
    description: string | null
    isActive: boolean
    lastSyncAt: string | null
    externalUrl?: string
    logoUrl?: string | null
    tokenHint?: string
    authenticationParameterHints?: Record<string, string>
    authenticationParameters?: Record<string, string | undefined>
    token?: string | null
    username?: string
    stats?: {
        animalsCount?: number
        cagesCount?: number
        studiesCount?: number
        assaysCount?: number
        datasetsCount?: number
        investigationsCount?: number
        fairComplianceScore?: number
    }
}

export interface ConnectedAppsResponse {
    'hydra:member': ConnectedApp[]
    'hydra:totalItems': number
}
