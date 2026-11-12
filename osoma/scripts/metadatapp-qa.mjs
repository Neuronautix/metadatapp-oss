import fs from 'fs'
import path from 'path'

const specPath = path.join(process.cwd(), 'resources/metadatapp.json')
const spec = JSON.parse(fs.readFileSync(specPath, 'utf8'))

const args = process.argv.slice(2)
const limitArg = args.find((arg) => arg.startsWith('--limit='))
const limit = limitArg ? Number(limitArg.split('=')[1]) : null
const base = process.env.METADATAPP_BASE ?? 'http://localhost'
const token = process.env.METADATAPP_TOKEN
const headers = { Accept: 'application/ld+json' }
if (token) {
  headers.Authorization = `Bearer ${token}`
}

const entries = Object.entries(spec.paths)
  .filter(([route]) => !route.includes('{'))
  .map(([route, node]) => ({ route, node }))

const targets = limit ? entries.slice(0, limit) : entries

const getIdFromItem = (item) => {
  const raw = item?.id ?? item?.['@id']
  if (!raw) return null
  if (typeof raw === 'string') {
    const trimmed = raw.replace(/\/$/, '')
    const parts = trimmed.split('/')
    return parts[parts.length - 1]
  }
  return String(raw)
}

const fetchJson = async (url) => {
  const response = await fetch(url, { headers })
  const text = await response.text()
  let payload = null
  try {
    payload = text ? JSON.parse(text) : null
  } catch {
    payload = text
  }
  return { response, payload }
}

const pad = (value, length = 6) => String(value).padEnd(length)

console.log(`MetaDatAPI QA (${base})`)
console.log(`Token: ${token ? 'yes' : 'no'}`)
console.log(`Collections: ${targets.length}`)
console.log('---')

for (const entry of targets) {
  const url = `${base}${entry.route}`
  try {
    const { response, payload } = await fetchJson(url)
    const count = payload?.['hydra:totalItems'] ?? payload?.['hydra:member']?.length ?? null
    console.log(`${pad(response.status)} ${entry.route} ${count !== null ? `items:${count}` : ''}`)

    if (response.ok && payload?.['hydra:member'] && entry.node?.get) {
      const itemId = getIdFromItem(payload['hydra:member'][0])
      const itemPath = entry.node?.get && entry.node?.get ? `${entry.route}/{id}` : null
      const itemNode = entry.node?.get ? spec.paths[itemPath] : null
      if (itemId && itemNode?.get) {
        const itemUrl = `${base}${entry.route}/${itemId}`
        const { response: itemResponse } = await fetchJson(itemUrl)
        console.log(`${pad(itemResponse.status)} ${entry.route}/{id} -> ${itemId}`)
      }
    }
  } catch (error) {
    console.log(`${pad('ERR')} ${entry.route} ${(error && error.message) || error}`)
  }
}
