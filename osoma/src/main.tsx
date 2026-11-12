import React from 'react'
import { createRoot } from 'react-dom/client'
import { App } from './app/App.tsx'
import './index.css'

const startApp = () => {
  const root = document.getElementById('root')
  if (!root) {
    throw new Error('Root element #root not found')
  }

  createRoot(root).render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  )
}

const prepare = async () => {
  const { getDataMode, setDataMode } = await import('./lib/mode.ts')
  if (getDataMode() === 'mock') {
    const { worker } = await import('./mocks/browser.ts')
    try {
      await worker.start({
        serviceWorker: {
          url: '/mockServiceWorker.js',
          options: { scope: '/' },
        },
        onUnhandledRequest: 'bypass',
      })
    } catch (error) {
      // Service worker registration can fail in environments with invalid/self-signed
      // cert chains. In that case, automatically fall back to real mode so the app
      // remains usable instead of hard-failing at bootstrap.
      console.warn('MSW failed to start in mock mode. Falling back to real mode.', error)
      setDataMode('real')
      sessionStorage.setItem(
        'metadatapp_bootstrap_warning',
        'Mock mode disabled: Service Worker registration failed (SSL/certificate issue). Switched to real mode.'
      )
    }
  }
}

const renderBootstrapError = (error: unknown) => {
  const root = document.getElementById('root')
  if (!root) return

  const message = error instanceof Error ? error.message : 'Unknown startup error'

  const container = document.createElement('div')
  container.style.cssText =
    'font-family: Inter, system-ui, sans-serif; padding: 24px; max-width: 900px; margin: 0 auto; color: #0f172a;'

  const heading = document.createElement('h1')
  heading.style.cssText = 'font-size: 1.5rem; margin-bottom: 0.75rem;'
  heading.textContent = 'App bootstrap failed'
  container.appendChild(heading)

  const intro = document.createElement('p')
  intro.style.cssText = 'margin-bottom: 0.75rem;'
  intro.textContent =
    'Mock mode could not be initialized. The Service Worker may not be available on this environment.'
  container.appendChild(intro)

  const pre = document.createElement('pre')
  pre.style.cssText =
    'background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; overflow: auto;'
  pre.textContent = message
  container.appendChild(pre)

  const list = document.createElement('ol')
  list.style.cssText = 'margin-top: 0.75rem; line-height: 1.6;'

  const li1 = document.createElement('li')
  li1.append('Open ')
  const code1 = document.createElement('code')
  code1.textContent = '/mockServiceWorker.js'
  li1.appendChild(code1)
  li1.append(' in your browser and verify it returns HTTP 200.')
  list.appendChild(li1)

  const li2 = document.createElement('li')
  li2.textContent =
    'Open DevTools → Application → Service Workers and confirm one is registered for this origin.'
  list.appendChild(li2)

  const li3 = document.createElement('li')
  li3.append('Open ')
  const code3 = document.createElement('code')
  code3.textContent = '/__ops'
  li3.appendChild(code3)
  li3.append(' and switch Data Source to ')
  const strong3 = document.createElement('strong')
  strong3.textContent = 'Real'
  li3.appendChild(strong3)
  li3.append(' temporarily if needed.')
  list.appendChild(li3)

  container.appendChild(list)

  root.replaceChildren(container)
}

prepare()
  .then(startApp)
  .catch((error) => {
    console.error('Failed to initialize app:', error)
    renderBootstrapError(error)
  })
