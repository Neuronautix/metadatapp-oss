// Resolves the published User & Integration Guide URL.
//
// Configurable at build time via VITE_DOCS_URL (VITE_* values are baked into the
// bundle, so changing this requires a frontend rebuild). Defaults to the public
// documentation domain.
const DEFAULT_DOCS_URL = 'https://doc.metadatapp.net'

export function getDocsBaseUrl(): string {
  const configured = import.meta.env.VITE_DOCS_URL?.trim()
  return (configured || DEFAULT_DOCS_URL).replace(/\/+$/, '')
}

// Build a link to a page within the guide, e.g. docsUrl('getting-started.html').
export function docsUrl(path = ''): string {
  const base = getDocsBaseUrl()
  if (!path) return base
  return `${base}/${path.replace(/^\/+/, '')}`
}
