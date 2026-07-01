---
title: "Feature: AI Assistant & MCP Bridge"
type: feature
updated: 2026-05-05
source_prs: [151, 197, 199, 209, 210, 244]
related: [features/fair-checking.md, features/curation.md, features/sensors.md, decisions/ai-mcp-read-only.md]
---

# Feature: AI Assistant & MCP Bridge

## Status
Active — read-only MCP bridge established; safer user-scoped provider configuration added

## Summary
The AI assistant provides LLM-driven capabilities (metadata suggestions, FAIR assessment, curation guidance)
through a controlled backend bridge. All AI tool dispatch goes through `McpBridgeService`, which validates
and constrains tool calls to read-only operations. No AI-driven flow can mutate state directly.

## Key PRs (chronological)

| PR | Date | What changed |
|----|------|--------------|
| #151 | 2026-03-24 | LLM subject curation + CurateGPT provider abstraction |
| #197 | 2026-04-03 | AI foundation vertical slice integration (details sparse in report) |
| #199 | 2026-04-03 | `McpBridgeService` for read-only tool dispatch; `SensorAgentClientInterface` abstraction; API + unit tests |
| #209 | 2026-04-09 | FAIR checker exposed as MCP tool via bridge |
| #210 | 2026-04-09 | AI assistant integration path for curation workflow capabilities |
| #244 | 2026-05-05 | Encrypted user-scoped OpenAI/Anthropic credentials, `SecretStoreInterface`, masked provider hints, Osoma AI Providers hardening |

## Architecture

```
AI Chat endpoint (AiAssistantService)
  └── McpBridgeService (PR #199)
        └── validates tool call: read-only only
        └── dispatches to registered tools:
              ├── FAIR checker tool (PR #209)
              ├── Sensor agent tools (PR #199, via SensorAgentClientInterface)
              └── Curation capability tools (PR #210)

CurateGPT provider (PR #151)
  └── generates Proposals from import sessions (separate from MCP bridge)
  └── abstracted behind curation provider interface

AI provider credential layer (PR #244)
  └── `LlmProviderCredential` entity stores per-user provider config
  └── `SecretStoreInterface` abstracts storage backend
  └── default implementation uses libsodium-encrypted database payloads
  └── API returns only masked hints, never raw keys
```

**Key constraint:** The bridge is the single entry point for all AI-to-backend tool calls.
Adding a new tool requires registering it with the bridge and explicitly asserting its read-only nature.

## Current capabilities

- AI chat endpoint with MCP tool dispatch
- FAIR check available from the assistant
- Sensor agent tools available from the assistant
- CurateGPT-driven metadata proposals (separate flow from MCP bridge)
- Per-user OpenAI and Anthropic provider settings in Osoma
- Encrypted provider key storage with environment fallback when no user key is configured
- Unit and API tests for bridge behavior and AI controller

## Known limitations & tech debt

- **Authorization escalation risk:** Read-only constraint is convention-enforced, not policy-enforced. See [tech-debt.md](../tech-debt.md).
- **Rate limiting:** No rate control on assistant-triggered tool calls (especially FAIR checks).
- **Tool selection accuracy:** Keyword matching for tool selection needs monitoring for false positives/negatives.
- **Multi-provider orchestration:** CurateGPT is the only LLM backend; multi-provider orchestration not implemented.
- **Secret backend abstraction incomplete:** `SecretStoreInterface` exists, but no managed secret-store adapter ships yet.

## Future opportunities

- Policy-enforced read-only layer (decorator on bridge, not per-tool)
- Write-capable tools with explicit confirmation step (human-in-the-loop)
- Tool registry UI to show which capabilities are available to the assistant
- Streaming responses for long-running FAIR assessments

## Related

- [features/fair-checking.md](fair-checking.md) — FAIR tool exposed via bridge
- [features/curation.md](curation.md) — curation capabilities accessible via assistant
- [features/sensors.md](sensors.md) — sensor agent tools registered with bridge
- [decisions/ai-mcp-read-only.md](../decisions/ai-mcp-read-only.md) — why all AI tool dispatch is read-only
