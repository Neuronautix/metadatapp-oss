import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import fs from 'node:fs'
import path from 'node:path'

const has = (value: string, needle: string) => value.indexOf(needle) !== -1

const chunkSegment = (id: string, prefix: string) => {
  const start = id.indexOf(prefix)
  if (start === -1) return undefined

  const remainder = id.slice(start + prefix.length)
  const [segment] = remainder.split('/')
  return segment || undefined
}

const vendorChunk = (id: string) => {
  if (!has(id, 'node_modules')) return undefined
  if (has(id, '/react-dom/')) return 'react-stack'
  if (has(id, '/react/')) return 'react-stack'
  if (has(id, '/scheduler/')) return 'react-stack'
  if (has(id, '/react-router')) return 'react-router'
  if (has(id, '/@tanstack/')) return 'tanstack'
  if (has(id, '/react-hook-form/')) return 'forms'
  if (has(id, '/@hookform/')) return 'forms'
  if (has(id, '/@radix-ui/')) return 'radix-ui'
  if (has(id, '/lucide-react/')) return 'icons'
  if (has(id, '/msw/')) return 'msw'
  if (has(id, '/zod/')) return 'zod'
  if (has(id, '/jszip/')) return 'jszip'
  if (has(id, '/papaparse/')) return 'csv'
  if (has(id, '/recharts/')) return 'charts'
  if (has(id, '/d3-')) return 'charts'
  if (has(id, '/ml-')) return 'analytics'
  if (has(id, '/simple-statistics/')) return 'analytics'
  return 'vendor'
}

const appChunk = (id: string) => {
  if (has(id, '/src/features/system/dashboard/')) return 'feature-app'
  if (has(id, '/src/features/system/audit/')) return 'feature-app'
  if (has(id, '/src/features/system/timeline/')) return 'feature-app'
  if (has(id, '/src/features/system/calendar/')) return 'feature-app'
  if (has(id, '/src/features/system/users/')) return 'feature-app'
  if (has(id, '/src/features/system/organizations/')) return 'feature-app'
  if (has(id, '/src/features/system/metadatapp/')) return 'feature-metadatapp'
  if (has(id, '/src/features/integrations/tecniplast/')) return 'feature-app'
  if (has(id, '/src/features/dvc/')) return 'feature-app'
  if (has(id, '/src/features/integrations/atmp/')) return 'feature-atmp'
  if (has(id, '/src/features/integrations/connected-apps/')) return 'feature-app'

  const featureGroup = chunkSegment(id, '/src/features/')
  if (featureGroup === 'core') return 'feature-app'
  if (featureGroup === 'system') return 'feature-app'
  if (featureGroup === 'zefix') return 'feature-zefix'
  if (featureGroup === 'curation') return 'feature-curation'
  if (featureGroup === 'export') return 'feature-export'
  if (featureGroup === 'ai-assistant') return 'feature-ai-assistant'

  const domainGroup = chunkSegment(id, '/src/domain/')
  if (domainGroup === 'curation') return 'feature-curation'
  if (domainGroup === 'dvc') return 'feature-app'
  if (domainGroup === 'nam') return 'feature-app'
  if (domainGroup === 'atmp') return 'feature-atmp'
  if (domainGroup === 'zefish') return 'feature-zefix'
  return undefined
}

const apiProxy = {
  target: process.env.VITE_PROXY_API_TARGET || 'https://metadatapp.test',
  changeOrigin: true,
  secure: false,
  rewrite: (path: string) => path.replace(/^\/api/, ''),
  headers: {
    origin: 'https://osoma.metadatapp.test',
  },
  configure: (proxy: { on: (event: string, handler: (...args: any[]) => void) => void }) => {
    proxy.on('proxyReq', (proxyReq) => {
      proxyReq.setHeader('host', 'metadatapp.test')
      proxyReq.setHeader('origin', 'https://osoma.metadatapp.test')
    })
  },
}

const resolveBuildOutDir = () => {
  const preferred = 'dist'
  const preferredAssets = path.join(preferred, 'assets')

  try {
    if (fs.existsSync(preferredAssets)) {
      fs.accessSync(preferredAssets, fs.constants.W_OK)
    } else if (fs.existsSync(preferred)) {
      fs.accessSync(preferred, fs.constants.W_OK)
    }

    return preferred
  } catch {
    return 'dist-local'
  }
}

const outDir = resolveBuildOutDir()

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': new URL('./src', import.meta.url).pathname,
    },
  },
  server: {
    allowedHosts: true,
    proxy: {
      // Proxy API requests to metadatapp backend
      // We use /api prefix to avoid collision with client-side routes
      '/api': apiProxy,
    },
  },
  preview: {
    host: '0.0.0.0',
    port: 4173,
    strictPort: true,
    allowedHosts: true,
    proxy: {
      '/api': apiProxy,
    },
  },
  build: {
    outDir,
    chunkSizeWarningLimit: 650,
    rollupOptions: {
      output: {
        manualChunks(id) {
          return vendorChunk(id) ?? appChunk(id)
        },
      },
    },
  },
})
