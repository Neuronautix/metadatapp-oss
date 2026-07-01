import { resolveApiAssetUrl } from '@/lib/api.ts'
import type { AppCode } from './connected-apps.types.ts'

const LOCAL_LOGO_BASE = '/Connected Apps Logo'

const localLogoByAppCode: Partial<Record<AppCode, string>> = {
    elabftw: `${LOCAL_LOGO_BASE}/Elabftw.png`,
    fair3r: `${LOCAL_LOGO_BASE}/fair3R.png`,
    osf: `${LOCAL_LOGO_BASE}/OSF.png`,
    softmouse: `${LOCAL_LOGO_BASE}/softmouse.png`,
    protocolio: `${LOCAL_LOGO_BASE}/Protocols.io.png`,
    preclinicaltrials: `${LOCAL_LOGO_BASE}/pct.eu.jpg`,
    cedar: `${LOCAL_LOGO_BASE}/CEDAR.jpeg`,
    bioportal: `${LOCAL_LOGO_BASE}/Bioportal.png`,
}

export const resolveConnectedAppLogoUrl = (appCode: AppCode, apiLogoUrl?: string | null): string | null => {
    const localLogoUrl = localLogoByAppCode[appCode]
    if (localLogoUrl) {
        return localLogoUrl
    }

    return apiLogoUrl ? resolveApiAssetUrl(apiLogoUrl) : null
}
