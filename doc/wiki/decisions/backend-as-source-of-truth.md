---
title: "Decision: Backend as Source of Truth for External APIs"
type: decision
updated: 2026-04-09
source_prs: [109, 115, 186, 196, 202]
related: [features/zefix.md, features/elabftw.md, features/sensors.md, areas/backend.md]
---

# Decision: Backend as Source of Truth for External APIs

## Status
Active

## Context
Metadatapp integrates with multiple external systems (Zefix, elabFTW, live sensors, Connected Apps).
A decision was needed on whether the frontend could call these APIs directly or whether all access
must be mediated by the backend.

## Decision
**All external API access goes through the backend.** The frontend never calls external system APIs directly.
This applies to: Zefix, elabFTW, sensor agents, Connected App logo fetching, and any future integrations.

## Established by
- PR #109: Backend serves Connected App logos (previously fetched client-side from external domains)
- PR #115: elabFTW HTTP client is a backend service; frontend has no knowledge of elabFTW endpoints
- PR #186: Sensor API proxy in the backend; frontend consumes backend endpoints only
- PR #202: elabFTW sync is a backend service

## Consequences

**Enables:**
- Centralized authentication and credential management (external API keys never reach the browser)
- Consistent caching and rate limiting at the backend boundary
- Audit trails for all external API interactions
- Easier mocking for tests (mock the backend service, not the external API)
- Frontend stays decoupled from external API evolution

**Constrains:**
- All external integrations require a backend service layer (more code)
- Real-time data from external APIs requires backend-to-frontend push (Mercure/WebSocket) or polling

## See also

This decision is also stated explicitly in `CLAUDE.md`:
> Do not call Connected Apps APIs directly from the frontend
