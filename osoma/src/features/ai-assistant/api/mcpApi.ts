import { AuthService } from '@/lib/auth'

const MCP_PROTOCOL_VERSION = '2025-06-18'
const MCP_SESSION_STORAGE_KEY = 'osoma.mcp.sessionId'

export type JsonRpcRequest = {
  jsonrpc: '2.0'
  id: number | string
  method: string
  params: any
}

export type JsonRpcResponse = {
  jsonrpc: '2.0'
  id: number | string
  result?: any
  error?: {
    code: number
    message: string
    data?: any
  }
}

type McpRequestOptions = {
  sessionId?: string
}

const isSessionExpiredError = (error: unknown): boolean => {
  if (!(error instanceof Error)) {
    return false
  }

  return (
    error.message.includes('MCP error 404') ||
    error.message.includes('MCP error 401') ||
    error.message.includes('Session not found or has expired')
  )
}

const readStoredSessionId = (): string | null => {
  if (typeof window === 'undefined') {
    return null
  }

  return window.sessionStorage.getItem(MCP_SESSION_STORAGE_KEY)
}

const storeSessionId = (sessionId: string | null): void => {
  if (typeof window === 'undefined') {
    return
  }

  if (sessionId) {
    window.sessionStorage.setItem(MCP_SESSION_STORAGE_KEY, sessionId)
    return
  }

  window.sessionStorage.removeItem(MCP_SESSION_STORAGE_KEY)
}

/**
 * MCP-specific fetch: uses raw fetch with application/json Content-Type
 * (not application/ld+json which apiFetch sends by default).
 * Also handles SSE-streamed responses from the MCP HTTP transport.
 */
async function mcpFetch(body: object, options: McpRequestOptions = {}): Promise<JsonRpcResponse> {
  const authService = AuthService.getInstance()
  const token = authService.getAccessToken()

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json, text/event-stream',
    'Mcp-Protocol-Version': MCP_PROTOCOL_VERSION,
  }

  if (token) {
    headers['Authorization'] = `Bearer ${token}`
  }

  if (options.sessionId) {
    headers['Mcp-Session-Id'] = options.sessionId
  }

  // The Vite preview proxy strips /api prefix and forwards to http://api
  const response = await fetch('/api/mcp', {
    method: 'POST',
    headers,
    body: JSON.stringify(body),
  })

  if (!response.ok) {
    const text = await response.text().catch(() => '')

    if (response.status === 404 && options.sessionId) {
      storeSessionId(null)
    }

    throw new Error(`MCP error ${response.status}: ${text || response.statusText}`)
  }

  const responseSessionId = response.headers.get('Mcp-Session-Id')
  if (responseSessionId) {
    storeSessionId(responseSessionId)
  }

  const contentType = response.headers.get('Content-Type') ?? ''

  // Handle SSE streams: read until we get a data line with valid JSON
  if (contentType.includes('text/event-stream')) {
    const text = await response.text()
    const dataLines = text.split('\n').filter(l => l.startsWith('data:'))
    for (const line of dataLines) {
      const json = line.replace(/^data:\s*/, '').trim()
      if (json) {
        try {
          return JSON.parse(json) as JsonRpcResponse
        } catch {
          // continue
        }
      }
    }
    return { jsonrpc: '2.0', id: 0, result: { content: [] } }
  }

  // Plain JSON response
  const text = await response.text()
  if (!text.trim()) {
    return { jsonrpc: '2.0', id: 0, result: { content: [] } }
  }
  return JSON.parse(text) as JsonRpcResponse
}

const sendInitializedNotification = async (sessionId: string): Promise<void> => {
  await mcpFetch(
    {
      jsonrpc: '2.0',
      method: 'notifications/initialized',
      params: {},
    },
    { sessionId }
  )
}

const initializeMcpSession = async (): Promise<string> => {
  await mcpFetch({
    jsonrpc: '2.0',
    id: `init-${Date.now()}`,
    method: 'initialize',
    params: {
      protocolVersion: MCP_PROTOCOL_VERSION,
      capabilities: {},
      clientInfo: {
        name: 'osoma-ai-assistant',
        version: '0.1.0',
      },
    },
  })

  const sessionId = readStoredSessionId()
  if (!sessionId) {
    throw new Error('MCP initialize succeeded but no session id was returned.')
  }

  await sendInitializedNotification(sessionId)

  return sessionId
}

const ensureMcpSession = async (): Promise<string> => {
  const existingSessionId = readStoredSessionId()

  if (existingSessionId) {
    return existingSessionId
  }

  return initializeMcpSession()
}

const withFreshMcpSession = async (callback: (sessionId: string) => Promise<JsonRpcResponse>): Promise<JsonRpcResponse> => {
  let sessionId = await ensureMcpSession()

  try {
    return await callback(sessionId)
  } catch (error) {
    if (!isSessionExpiredError(error)) {
      throw error
    }

    storeSessionId(null)
    sessionId = await initializeMcpSession()

    return callback(sessionId)
  }
}

export const callMcpTool = async (name: string, args: any = {}): Promise<JsonRpcResponse> => {
  return withFreshMcpSession((sessionId) =>
    mcpFetch({
      jsonrpc: '2.0',
      id: Date.now(),
      method: 'tools/call',
      params: {
        name,
        arguments: args,
      },
    }, { sessionId })
  )
}

export const listMcpTools = async (): Promise<JsonRpcResponse> => {
  return withFreshMcpSession((sessionId) =>
    mcpFetch({
      jsonrpc: '2.0',
      id: Date.now(),
      method: 'tools/list',
      params: {},
    }, { sessionId })
  )
}

export const resetMcpSession = (): void => {
  const sessionId = readStoredSessionId()
  storeSessionId(null)

  if (!sessionId) {
    return
  }

  void fetch('/api/mcp', {
    method: 'DELETE',
    headers: {
      'Mcp-Protocol-Version': MCP_PROTOCOL_VERSION,
      'Mcp-Session-Id': sessionId,
    },
  })
}
