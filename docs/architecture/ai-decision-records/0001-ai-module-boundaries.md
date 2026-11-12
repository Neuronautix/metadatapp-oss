# ADR 0001: Establish explicit AI module boundaries

- **Status:** Accepted
- **Date:** 2026-03-23

## Context

Metadatapp already exposes an MCP server, but the repository did not yet separate gateway, curation, governance, evaluation, and runtime concerns.

## Decision

AI-related backend code will live under `api/src/AI/` with explicit sub-namespaces for `Mcp`, `Gateway`, `Curation`, `Governance`, `Evals`, and `Runtime`.

## Consequences

- Future AI work has a default home in the backend
- Provider adapters can be swapped without changing business code
- Governance and evaluation code remain distinct from prompting and transport concerns
