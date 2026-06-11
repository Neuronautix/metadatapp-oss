import { useState, useRef, useEffect, type ReactNode } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { Send, Bot, User, Sparkles, Loader2, AlertCircle } from 'lucide-react'
import { getAiStatus, sendAiChat } from '../assistant.api'
import { callMcpTool } from '../api/mcpApi'
import type { AiChatMessage } from '../assistant.types'
import { AiStatusBadge } from './AiStatusBadge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'

const SUGGESTIONS = [
  'How can you help with metadata review?',
  'Draft search filters from a free-text request',
  'Explain what missing metadata means',
  'Generate export-oriented structured content',
  'List my studies',
  'Show me my mice',
  'Show me the latest cages',
  'FAIR score for my latest study',
  'Show the latest SSA sensor reading',
  'Is the Raspberry Pi sensor healthy?',
  'List zebrafish batches',
] as const

const LOOKUP_INTENT_PATTERN = /\b(list|show|find|get|latest|recent|which)\b/
const STUDY_PATTERN = /\b(experiments?|investigations?|stud(?:y|ies))\b/
const FAIR_ASSESSMENT_PATTERN = /\b(fair score|fair check|fair assessment)\b/
const FAIR_DIMENSION_PATTERN = /\b(findable|accessible|interoperable|reusable)\b/
const SENSOR_PATTERN = /\b(ssa|sovereign sensor agent|raspberry|raspberry pi|sensor|telemetry|temperature|humidity|pressure|alarm|alert|threshold|health|heartbeat|reading|observation|history|trend)\b/

type McpToolRequest = {
  name: string
  args: Record<string, string | number>
}

const getEntityName = (toolName: string): string => {
  switch (toolName) {
    case 'list_mice': return 'mice'
    case 'list_zebrafish': return 'zebrafish subjects'
    default: return toolName.split('_')[1] ?? toolName
  }
}

const resolveSensorMcpToolRequest = (lowercaseMessage: string): McpToolRequest | null => {
  if (!SENSOR_PATTERN.test(lowercaseMessage)) {
    return null
  }

  if (/\b(history|trend|last hour|rolling)\b/.test(lowercaseMessage)) {
    return { name: 'get_live_sensor_history', args: { rangeMinutes: 60, maxPoints: 60 } }
  }

  if (/\b(health|healthy|status|online|uptime|heartbeat)\b/.test(lowercaseMessage)) {
    return { name: 'get_live_sensor_health', args: {} }
  }

  if (/\b(alarm|alert|threshold|limit|breach)\b/.test(lowercaseMessage)) {
    return { name: 'get_live_sensor_threshold_status', args: {} }
  }

  const metrics = ['temperature', 'humidity', 'pressure'].filter((metric) => lowercaseMessage.includes(metric))
  if (metrics.length === 1) {
    return { name: 'get_live_sensor_metric', args: { metric: metrics[0] } }
  }

  return { name: 'get_live_sensor_observation', args: {} }
}

const resolveMcpToolRequest = (content: string): McpToolRequest | null => {
  const lowercaseMessage = content.toLowerCase()
  const uuidMatch = lowercaseMessage.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i)
  const asksForFairAssessment =
    FAIR_ASSESSMENT_PATTERN.test(lowercaseMessage) ||
    (FAIR_DIMENSION_PATTERN.test(lowercaseMessage) && STUDY_PATTERN.test(lowercaseMessage))

  if (asksForFairAssessment) {
    return uuidMatch
      ? { name: 'get_fair_assessment', args: { id: uuidMatch[0] } }
      : { name: 'list_studies', args: {} }
  }

  const sensorRequest = resolveSensorMcpToolRequest(lowercaseMessage)
  if (sensorRequest) {
    return sensorRequest
  }

  if (!LOOKUP_INTENT_PATTERN.test(lowercaseMessage)) {
    return null
  }

  if (lowercaseMessage.includes('zebrafish') || lowercaseMessage.includes('fish') || lowercaseMessage.includes('batch')) {
    return { name: 'list_zebrafish', args: { species: 'zebrafish' } }
  }

  if (
    lowercaseMessage.includes('mice') ||
    lowercaseMessage.includes('mouse') ||
    lowercaseMessage.includes('subject') ||
    (lowercaseMessage.includes('animal') && !lowercaseMessage.includes('zebrafish'))
  ) {
    return { name: 'list_mice', args: { species: 'mouse' } }
  }

  if (STUDY_PATTERN.test(lowercaseMessage)) {
    return { name: 'list_studies', args: {} }
  }

  if (lowercaseMessage.includes('cage') || lowercaseMessage.includes('tank')) {
    return { name: 'list_cages', args: {} }
  }

  if (lowercaseMessage.includes('project')) {
    return { name: 'list_investigations', args: {} }
  }

  return null
}

type FairCriterion = {
  id: string
  label: string
  description: string
  pass: boolean
  note?: string
}

type FairAssessmentResult = {
  studyName?: string
  name?: string
  score?: {
    total?: number
    findable?: number
    accessible?: number
    interoperable?: number
    reusable?: number
  }
  criteria?: Record<string, FairCriterion[]>
}

type McpContentItem = {
  text?: string
  [key: string]: unknown
}

type AssistantReplyPayload = {
  content?: unknown
  suggestions?: unknown
}

const fencedJsonPattern = /^```(?:json)?\s*([\s\S]*?)\s*```$/i

function normalizeAssistantReply(reply: unknown): string {
  if (typeof reply !== 'string') {
    return typeof reply === 'object' && reply !== null
      ? normalizeAssistantReplyPayload(reply as AssistantReplyPayload)
      : ''
  }

  const trimmed = reply.trim()
  const jsonCandidate = trimmed.match(fencedJsonPattern)?.[1] ?? trimmed

  if (!jsonCandidate.startsWith('{')) {
    return reply
  }

  try {
    const parsed = JSON.parse(jsonCandidate) as AssistantReplyPayload
    return normalizeAssistantReplyPayload(parsed) || reply
  } catch {
    return reply
  }
}

function normalizeAssistantReplyPayload(payload: AssistantReplyPayload): string {
  const content = typeof payload.content === 'string' ? payload.content.trim() : ''
  const suggestions = Array.isArray(payload.suggestions)
    ? payload.suggestions.filter((suggestion): suggestion is string => typeof suggestion === 'string' && suggestion.trim().length > 0)
    : []

  if (suggestions.length === 0) {
    return content
  }

  return `${content}\n\nSuggested next steps:\n${suggestions.map((suggestion) => `- ${suggestion}`).join('\n')}`.trim()
}

function renderInlineMarkdown(text: string) {
  const parts = text.split(/(\*\*[^*]+\*\*)/g)
  return parts.map((part, index) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return <strong key={index}>{part.slice(2, -2)}</strong>
    }

    return <span key={index}>{part}</span>
  })
}

function MessageContent({ content }: { content: string }) {
  const lines = content.split('\n')
  const elements: ReactNode[] = []
  let listItems: string[] = []

  const flushList = () => {
    if (listItems.length === 0) return

    elements.push(
      <ul key={`list-${elements.length}`} className="my-2 list-disc space-y-1 pl-5">
        {listItems.map((item, index) => (
          <li key={index}>{renderInlineMarkdown(item)}</li>
        ))}
      </ul>
    )
    listItems = []
  }

  lines.forEach((line, index) => {
    const trimmed = line.trim()
    if (trimmed.startsWith('- ')) {
      listItems.push(trimmed.slice(2))
      return
    }

    flushList()

    if (trimmed === '') {
      elements.push(<div key={`space-${index}`} className="h-2" />)
      return
    }

    elements.push(
      <p key={index} className="my-1">
        {renderInlineMarkdown(line)}
      </p>
    )
  })

  flushList()

  return <>{elements}</>
}

type McpToolResult = {
  content?: McpContentItem[]
  structuredContent?: unknown
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
  value !== null && typeof value === 'object' && !Array.isArray(value)

const parseJsonText = (text: string): unknown => {
  try {
    return JSON.parse(text)
  } catch {
    return null
  }
}

const normalizeMcpCollectionItems = (value: unknown): McpContentItem[] => {
  if (Array.isArray(value)) {
    return value
      .filter((item): item is McpContentItem => isRecord(item))
  }

  if (!isRecord(value)) {
    return []
  }

  const collectionKeys = ['hydra:member', 'member', 'items', 'data', 'results', 'records', '@graph']
  for (const key of collectionKeys) {
    const candidate = value[key]
    if (Array.isArray(candidate)) {
      return candidate.filter((item): item is McpContentItem => isRecord(item))
    }
  }

  if ('name' in value || 'label' in value || 'id' in value || '@id' in value || 'text' in value) {
    return [value]
  }

  return []
}

const readMcpCollectionTotal = (value: unknown): number | null => {
  if (!isRecord(value)) {
    return null
  }

  for (const key of ['hydra:totalItems', 'totalItems', 'total', 'count']) {
    const candidate = value[key]
    if (typeof candidate === 'number') {
      return candidate
    }
  }

  return null
}

const getMcpLookupItems = (result: McpToolResult | null | undefined): McpContentItem[] => {
  const structuredItems = normalizeMcpCollectionItems(result?.structuredContent)
  if (structuredItems.length > 0) {
    return structuredItems
  }

  for (const item of result?.content ?? []) {
    if (typeof item.text === 'string') {
      const parsed = parseJsonText(item.text)
      const parsedItems = normalizeMcpCollectionItems(parsed)
      if (parsedItems.length > 0) {
        return parsedItems
      }
    }

    const directItems = normalizeMcpCollectionItems(item)
    if (directItems.length > 0) {
      return directItems
    }
  }

  return []
}

const getMcpLookupTotal = (result: McpToolResult | null | undefined, items: McpContentItem[]): number => {
  const structuredTotal = readMcpCollectionTotal(result?.structuredContent)
  if (structuredTotal !== null) {
    return structuredTotal
  }

  for (const item of result?.content ?? []) {
    if (typeof item.text === 'string') {
      const parsedTotal = readMcpCollectionTotal(parseJsonText(item.text))
      if (parsedTotal !== null) {
        return parsedTotal
      }
    }

    const directTotal = readMcpCollectionTotal(item)
    if (directTotal !== null) {
      return directTotal
    }
  }

  return items.length
}

const getMcpItemLabel = (item: McpContentItem): string => {
  for (const key of ['name', 'label', 'title', 'externalId', 'id', '@id', 'text']) {
    const value = item[key]
    if (typeof value === 'string' && value.trim() !== '') {
      return value
    }
  }

  return 'Unnamed'
}

const parseFirstMcpPayload = <T extends Record<string, unknown>>(result: McpToolResult | null | undefined): T | null => {
  const first = result?.content?.[0]
  if (!first) {
    return null
  }

  if (typeof first.text === 'string') {
    const parsed = parseJsonText(first.text)
    return isRecord(parsed) ? parsed as T : { message: first.text } as unknown as T
  }

  return first as T
}

function formatFairAssessmentReply(result: McpToolResult | null | undefined): string {
  const content = result?.content
  if (!content || !Array.isArray(content) || content.length === 0) {
    return "I couldn't retrieve the FAIR assessment. Please make sure the API is running."
  }

  try {
    const raw = content[0]
    const data: FairAssessmentResult = typeof raw?.text === 'string' ? JSON.parse(raw.text) : raw
    const studyName = data?.studyName ?? data?.name ?? 'the study'
    const score = data?.score ?? {}
    const total = score.total ?? 0
    const findable = score.findable ?? 0
    const accessible = score.accessible ?? 0
    const interoperable = score.interoperable ?? 0
    const reusable = score.reusable ?? 0

    let passed = 0
    let total15 = 0
    const criteria = data?.criteria ?? {}
    for (const pillar of Object.values(criteria)) {
      if (Array.isArray(pillar)) {
        for (const criterion of pillar) {
          total15++
          if (criterion.pass) passed++
        }
      }
    }

    return (
      `FAIR Assessment for "${studyName}" — Overall score: ${total}/100\n` +
      `  Findable: ${findable}/100 · Accessible: ${accessible}/100 · Interoperable: ${interoperable}/100 · Reusable: ${reusable}/100\n` +
      `  ${passed} of ${total15} FAIR criteria pass. ` +
      (total >= 75 ? 'This study is in good FAIR shape! ✅' :
       total >= 50 ? 'There is room to improve FAIR compliance.' :
       'Significant improvements are needed to reach FAIR compliance.')
    )
  } catch {
    return "I retrieved FAIR data but couldn't parse the response. Please check the API."
  }
}

function formatLiveSensorReply(toolName: string, result: McpToolResult | null | undefined): string {
  const data = parseFirstMcpPayload(result)
  if (!data) {
    return "I couldn't retrieve live SSA sensor data. Please make sure the API and Raspberry Pi sensor agent are reachable."
  }

  const message = typeof data.message === 'string' && data.message ? ` ${data.message}` : ''

  if (toolName === 'get_live_sensor_health') {
    const status = typeof data.status === 'string' ? data.status : 'unknown'
    const reachable = data.upstreamReachable === true ? 'reachable' : 'unreachable'
    const fresh = data.isFresh === true ? 'fresh' : 'not fresh'
    return `SSA sensor health is ${status}; upstream is ${reachable} and the latest observation is ${fresh}.${message}`
  }

  if (toolName === 'get_live_sensor_threshold_status') {
    const overallStatus = typeof data.overallStatus === 'string' ? data.overallStatus : 'unknown'
    const activeAlarms = Array.isArray(data.activeAlarms) ? data.activeAlarms : []
    if (activeAlarms.length === 0) {
      return `SSA sensor threshold status is ${overallStatus}. No active live sensor alarms are reported.${message}`
    }

    const alarmText = activeAlarms
      .slice(0, 3)
      .map((alarm) => {
        if (alarm && typeof alarm === 'object' && 'message' in alarm && typeof alarm.message === 'string') {
          return alarm.message
        }
        return 'Active alarm'
      })
      .join('; ')
    return `SSA sensor threshold status is ${overallStatus}. Active alarms: ${alarmText}.${message}`
  }

  if (toolName === 'get_live_sensor_metric') {
    const metric = typeof data.metric === 'string' ? data.metric : 'metric'
    const value = typeof data.value === 'number' ? data.value : null
    const unit = typeof data.unit === 'string' ? data.unit : ''
    const alarmLevel = typeof data.alarmLevel === 'string' ? data.alarmLevel : 'unknown'
    return value === null
      ? `SSA ${metric} is not currently available.${message}`
      : `Latest SSA ${metric}: ${value}${unit ? ` ${unit}` : ''}. Alarm level: ${alarmLevel}.${message}`
  }

  if (toolName === 'get_live_sensor_history') {
    const points = Array.isArray(data.points) ? data.points.length : 0
    const rangeMinutes = typeof data.rangeMinutes === 'number' ? data.rangeMinutes : 60
    return `SSA sensor history returned ${points} point${points === 1 ? '' : 's'} over the last ${rangeMinutes} minutes.${message}`
  }

  const sensorId = typeof data.sensorId === 'string' && data.sensorId ? data.sensorId : 'SSA sensor'
  const observedAt = typeof data.observedAt === 'string' && data.observedAt ? ` at ${data.observedAt}` : ''
  const values = [
    typeof data.temperatureC === 'number' ? `${data.temperatureC} °C` : null,
    typeof data.humidityPct === 'number' ? `${data.humidityPct}% RH` : null,
    typeof data.pressureHpa === 'number' ? `${data.pressureHpa} hPa` : null,
  ].filter(Boolean)

  return values.length > 0
    ? `Latest ${sensorId} reading${observedAt}: ${values.join(', ')}.${message}`
    : `Latest ${sensorId} reading is available, but no numeric temperature, humidity, or pressure values were returned.${message}`
}

export function AssistantChat() {
  const [messages, setMessages] = useState<AiChatMessage[]>([
    {
      role: 'assistant',
      content: "Hello! I'm your Metadatapp AI assistant. I can draft review guidance, explain metadata gaps, build query filters, prepare export-ready structure, and help explore studies, cages, investigations, mice, zebrafish batches, and FAIR scores.",
    },
  ])
  const [input, setInput] = useState('')
  const [isToolLoading, setIsToolLoading] = useState(false)
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const statusQuery = useQuery({
    queryKey: ['ai-status'],
    queryFn: getAiStatus,
    staleTime: 30_000,
  })
  const chatMutation = useMutation({
    mutationFn: (nextMessages: AiChatMessage[]) =>
      sendAiChat({
        messages: nextMessages,
      }),
  })
  const isResponding = chatMutation.isPending || isToolLoading

  const scrollToBottom = () => {
    if (typeof messagesEndRef.current?.scrollIntoView === 'function') {
      messagesEndRef.current.scrollIntoView({ behavior: 'smooth' })
    }
  }

  useEffect(() => {
    scrollToBottom()
  }, [messages])

  const handleSend = async (content: string) => {
    if (!content.trim() || isResponding) return

    const newMessages = [...messages, { role: 'user', content } as AiChatMessage]
    setMessages(newMessages)
    setInput('')

    const mcpRequest = resolveMcpToolRequest(content)

    if (mcpRequest) {
      setIsToolLoading(true)
    }

    try {
      if (mcpRequest) {
        const response = await callMcpTool(mcpRequest.name, mcpRequest.args)

        if (mcpRequest.name === 'get_fair_assessment') {
          const reply = formatFairAssessmentReply(response.result)
          setMessages([...newMessages, { role: 'assistant', content: reply }])
        } else if (mcpRequest.name.startsWith('get_live_sensor_')) {
          const reply = formatLiveSensorReply(mcpRequest.name, response.result)
          setMessages([...newMessages, { role: 'assistant', content: reply }])
        } else {
          const items = getMcpLookupItems(response.result)
          const count = getMcpLookupTotal(response.result, items)
          const entityName = getEntityName(mcpRequest.name)

          if (count === 0) {
            setMessages([...newMessages, { role: 'assistant', content: `I couldn't find any ${entityName}.` }])
            return
          }

          let reply = `I found ${count} ${entityName} in the system.`

          if (items.length > 0) {
            const examples = items
              .slice(0, 3)
              .map(getMcpItemLabel)
            reply += ` Here are some examples: ${examples.join(', ')}.`
          }

          if (mcpRequest.name === 'list_studies' && FAIR_ASSESSMENT_PATTERN.test(content.toLowerCase())) {
            reply += " To check the FAIR score for a specific study, share its UUID and I'll run the FAIR assessment."
          }

          setMessages([...newMessages, { role: 'assistant', content: reply }])
        }
      } else {
        const response = await chatMutation.mutateAsync(newMessages)
        setMessages([...newMessages, { role: 'assistant', content: normalizeAssistantReply(response.reply) }])
      }
    } catch (error) {
      const raw = error instanceof Error ? error.message : ''
      const friendlyContent = raw.includes('401')
        ? 'Your session has expired. Please reload the page and try again.'
        : mcpRequest
          ? 'I encountered an error while trying to reach the lab systems. Please make sure the API is running.'
          : 'I could not reach the AI gateway.'

      setMessages([
        ...newMessages,
        { role: 'assistant', content: friendlyContent },
      ])
    } finally {
      if (mcpRequest) {
        setIsToolLoading(false)
      }
    }
  }

  return (
    <div className="flex flex-col h-[600px] max-w-4xl mx-auto border rounded-2xl overflow-hidden bg-white shadow-xl">
      <div className="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="bg-cyan-500 p-2 rounded-lg">
            <Sparkles size={20} className="text-white" />
          </div>
          <div>
            <h2 className="font-bold">AI Assistant</h2>
            <p className="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Governed preview-only workflow</p>
          </div>
        </div>
        {statusQuery.data ? <AiStatusBadge status={statusQuery.data.status} /> : null}
      </div>

      <div className="flex-1 overflow-y-auto p-6 space-y-4 bg-slate-50">
        {statusQuery.data ? (
          <div className="rounded-2xl border border-line bg-white p-4 text-sm text-slate-600">
            <div className="flex items-start gap-2">
              {!statusQuery.data.status.available ? <AlertCircle className="mt-0.5 h-4 w-4 text-warning" /> : null}
              <div>
                <p className="font-medium text-slate-900">Provider status</p>
                <p>{statusQuery.data.status.message}</p>
                <p className="mt-2 text-xs text-slate-400">Trace ID: {statusQuery.data.trace.traceId}</p>
              </div>
            </div>
          </div>
        ) : null}
        {messages.map((message, index) => (
          <div
            key={index}
            className={`flex gap-3 ${message.role === 'user' ? 'flex-row-reverse' : ''}`}
          >
            <Avatar className={`h-8 w-8 ${message.role === 'assistant' ? 'bg-cyan-600' : 'bg-slate-300'}`}>
              <AvatarFallback className="text-white">
                {message.role === 'assistant' ? <Bot size={16} /> : <User size={16} />}
              </AvatarFallback>
            </Avatar>
            <div
              className={`max-w-[80%] rounded-2xl px-4 py-2 text-sm whitespace-pre-line ${
                message.role === 'assistant'
                  ? 'bg-white border rounded-tl-none text-slate-700'
                  : 'bg-cyan-600 text-white rounded-tr-none'
              }`}
            >
              <MessageContent content={message.content} />
            </div>
          </div>
        ))}
        {isResponding && (
          <div className="flex gap-3">
            <Avatar className="h-8 w-8 bg-cyan-600">
              <AvatarFallback className="text-white">
                <Bot size={16} />
              </AvatarFallback>
            </Avatar>
            <div className="bg-white border rounded-2xl rounded-tl-none px-4 py-2">
              <Loader2 size={16} className="animate-spin text-cyan-600" />
            </div>
          </div>
        )}
        <div ref={messagesEndRef} />
      </div>

      <div className="p-4 border-t bg-white">
        <div className="flex flex-wrap gap-2 mb-4 justify-center">
          {SUGGESTIONS.map((suggestion) => (
            <Button
              key={suggestion}
              variant="outline"
              size="sm"
              className="rounded-full text-[11px] h-7"
              onClick={() => handleSend(suggestion)}
              disabled={isResponding}
            >
              {suggestion}
            </Button>
          ))}
        </div>
        <form
          className="relative flex gap-2"
          onSubmit={(e) => {
            e.preventDefault()
            handleSend(input)
          }}
        >
          <Input
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder="Ask how to use AI-assisted review or request a FAIR check..."
            className="flex-1 rounded-xl pr-12 h-12"
            disabled={isResponding}
          />
          <Button
            type="submit"
            disabled={!input.trim() || isResponding}
            className="absolute right-1 top-1 h-10 w-10 p-0 rounded-lg"
            aria-label="Send message"
          >
            <Send size={18} />
          </Button>
        </form>
      </div>
    </div>
  )
}
