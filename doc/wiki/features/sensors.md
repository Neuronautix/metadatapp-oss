---
title: "Feature: Sensor Agent Integration"
type: feature
updated: 2026-04-09
source_prs: [186, 196, 199]
related: [features/ai-mcp.md, decisions/backend-as-source-of-truth.md]
---

# Feature: Sensor Agent Integration

## Status
Demo — functional integration demonstrated; not production-hardened

## Summary
Live sensor data (e.g., from a Raspberry Pi Sovereign Sensor Agent) can be consumed through Metadatapp's
backend proxy and displayed in Osoma panels. The backend exposes sensor tools via the MCP bridge for
AI assistant access. Alarm models handle threshold-based alerting.

## Key PRs (chronological)

| PR | Date | What changed |
|----|------|--------------|
| #186 | 2026-04-02 | Live sensor demo: backend API proxy, alarm model, MCP tools, Osoma panels |
| #196 | 2026-04-03 | Align Osoma Sovereign Sensor Agent demo with Raspberry Pi SSA API |
| #199 | 2026-04-03 | `SensorAgentClientInterface` abstraction in MCP bridge |

## Architecture

```
Raspberry Pi / SSA
  └── Sensor data (temperature, humidity, etc.)

Backend
  └── API proxy (PR #186) — never exposed to frontend directly
  └── Alarm model (PR #186) — threshold-based alerts
  └── SensorAgentClientInterface (PR #199) — decouples bridge from transport
  └── MCP tools registered with McpBridgeService (PR #186, #199)

Frontend (Osoma)
  └── Sensor panels (PR #186) — consume via backend API

AI Assistant
  └── Sensor tools via MCP bridge → read-only sensor queries
```

## Current capabilities

- Backend proxy for live sensor data
- Alarm model with threshold triggers
- Osoma sensor display panels
- Sensor data accessible as read-only MCP tools from the AI assistant
- Abstracted client interface (allows mocking for tests)

## Known limitations & tech debt

- **Demo quality only:** Failover, reconnection on sensor loss, and alarm escalation not production-hardened.
  See [tech-debt.md](../tech-debt.md).
- **No persistence:** Sensor readings are not stored; historical data not available.
- **No alert notification delivery:** Alarm model exists but no notification channel (email, webhook) connected.

## Future opportunities

- Persistent sensor reading storage for trend analysis
- Alert notification delivery (email, Slack, webhook)
- FAIR-relevant environmental metadata (temperature logs) feeding into study metadata
- Support for additional sensor types beyond Raspberry Pi SSA

## Related

- [features/ai-mcp.md](ai-mcp.md) — sensor tools registered with MCP bridge
- [decisions/backend-as-source-of-truth.md](../decisions/backend-as-source-of-truth.md) — sensor API never called from frontend
