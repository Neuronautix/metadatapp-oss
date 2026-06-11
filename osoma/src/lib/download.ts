/**
 * Trigger a browser download for a Blob by creating a temporary anchor element.
 * The element is appended to the document body and removed after the click to
 * avoid leaking DOM nodes. The object URL is revoked once the download starts.
 */
export function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}
