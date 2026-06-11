---
title: "Decision: AI Tool Dispatch is Read-Only"
type: decision
updated: 2026-04-09
source_prs: [199, 209, 210]
related: [features/ai-mcp.md, tech-debt.md]
---

# Decision: AI Tool Dispatch is Read-Only

## Status
Active — under monitoring (see tech debt)

## Context
The AI assistant needs access to backend capabilities (FAIR checking, sensor data, curation suggestions).
A decision was needed on whether AI-driven tool calls could mutate backend state or only read it.

## Decision
**All AI tool dispatch via `McpBridgeService` is read-only.** No state mutations from AI-driven flows.
Any tool registered with the bridge must be validated as non-mutating before registration.

## Established by
- PR #199: `McpBridgeService` with explicit read-only validation; `SensorAgentClientInterface` abstraction
- PR #209: FAIR checker registered as read-only MCP tool
- PR #210: Curation capabilities accessible via assistant but mutations go through explicit user-confirmed flows

## Consequences

**Enables:**
- Safe initial posture for AI integration; no risk of AI-triggered data corruption
- Progressive tool addition with clear governance (each tool requires explicit read-only validation)
- Auditable: all tool calls go through a single bridge with logging capability

**Constrains:**
- AI-assisted workflows that need to write (e.g., applying a curation suggestion) require a separate explicit user action
- Cannot do "one-click AI apply" without changing this policy

## Known risk

The read-only constraint is enforced **by convention**, not by a policy layer. Each tool implementor
must assert read-only behavior manually. This is a known tech debt item — see [tech-debt.md](../tech-debt.md).

**Resolution path:** Add an explicit authorization decorator on `McpBridgeService` that enforces
read-only at the bridge level, independent of individual tool implementations.
