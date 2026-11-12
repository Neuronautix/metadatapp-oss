const DEFAULT_REALM = import.meta.env.VITE_OIDC_REALM || 'demo';

function isIpv4Address(hostname: string): boolean {
  return /^\d{1,3}(?:\.\d{1,3}){3}$/.test(hostname);
}

function isLocalHostname(hostname: string): boolean {
  return hostname === 'localhost'
    || hostname === '127.0.0.1'
    || hostname === '::1'
    || hostname === '[::1]';
}

function inferOidcServerUrl(): string {
  if (typeof window === 'undefined') {
    return 'https://auth.metadatapp.test/realms/demo';
  }

  const { protocol, hostname } = window.location;

  // Local development fallback
  if (isLocalHostname(hostname)) {
    return `${protocol}//auth.metadatapp.test/realms/${DEFAULT_REALM}`;
  }

  if (isIpv4Address(hostname)) {
    return `${protocol}//${hostname}/realms/${DEFAULT_REALM}`;
  }

  // If we are already on the auth subdomain, the path should be root-based
  if (hostname.startsWith('auth.')) {
    return `${protocol}//${hostname}/realms/${DEFAULT_REALM}`;
  }

  // Determine the auth host based on the current domain
  const hostSegments = hostname.split('.');
  const authHost = hostSegments.length >= 3
    ? `auth.${hostSegments.slice(1).join('.')}`
    : `auth.${hostname}`;

  // For production domains like osoma.metadatapp.net, 
  // we try to target 'auth.metadatapp.net' directly at the root.
  // Note: if the infrastructure requires a prefix (like /oidc/), 
  // it should be provided via VITE_OIDC_SERVER_URL.
  return `${protocol}//${authHost}/realms/${DEFAULT_REALM}`;
}

function resolveOidcServerUrl(envValue: string | undefined, fallback: string): string {
  const trimmedValue = envValue?.trim();
  if (!trimmedValue) {
    return fallback;
  }

  if (typeof window === 'undefined') {
    return trimmedValue;
  }

  try {
    const parsedUrl = new URL(trimmedValue);
    // If the env var points to localhost but we are in production, ignore it and use fallback
    if (isLocalHostname(parsedUrl.hostname) && !isLocalHostname(window.location.hostname)) {
      return fallback;
    }

    return parsedUrl.toString().replace(/\/$/, '');
  } catch {
    return fallback;
  }
}

export const OIDC_CLIENT_ID: string = import.meta.env.VITE_OIDC_CLIENT_ID || 'osoma';
const inferredOidcServerUrl = inferOidcServerUrl();

export const OIDC_SERVER_URL: string = resolveOidcServerUrl(import.meta.env.VITE_OIDC_SERVER_URL, inferredOidcServerUrl);
export const OIDC_SERVER_URL_INTERNAL: string = resolveOidcServerUrl(import.meta.env.VITE_OIDC_SERVER_URL_INTERNAL, OIDC_SERVER_URL);
