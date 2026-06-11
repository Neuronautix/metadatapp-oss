---
title: "Decision: API Platform 4.3 Conventions"
type: decision
updated: 2026-04-09
source_prs: [109, 123, 139, 149, 194, 209, 210]
related: [areas/backend.md, features/fair-checking.md, features/curation.md]
---

# Decision: API Platform 4.3 Conventions

## Status
Active

## Context
The backend needs a consistent pattern for exposing resources via REST/JSON-LD, handling validation,
and integrating with the Doctrine ORM. A decision was needed on how to structure API endpoints,
where to put read/write logic, and how to handle custom behavior.

## Decision

**Use API Platform 4.3 as the uniform REST layer.** Key conventions:

1. **State processors over controllers** — all custom write logic goes in `State/Processor/`. Custom controllers are rare and must be justified.
2. **State providers over repositories** — custom read logic goes in `State/Provider/`.
3. **Resources are Doctrine entities** — `#[ApiResource]` attribute on Doctrine entities; UUIDs via `UuidGenerator`.
4. **Serializer groups** — use serialization groups to control what is exposed per operation.
5. **Schema validation** — API responses are validated against external schemas (e.g., IMPC) where required (PR #149).
6. **MCP integration** — API Platform's serializer output is used directly by the MCP bridge (PR #123/139).

## Established by
- PR #123/139: API Platform 4.3 upgrade with serializer modernization; MCP integration
- PR #149: Schema validation against IMPC standards; MCP validation stability
- PR #194: API consistency pass enforcing these conventions across all resources
- PR #209/210: `FairReportController`, `FairAssessment` resource built following these patterns

## Consequences

**Enables:**
- Auto-generated OpenAPI documentation
- Consistent JSON-LD responses across all resources
- Declarative access control and validation
- Serializer-driven export to multiple standards (RO-Crate, Croissant, FAIR²)

**Constrains:**
- All new resources must follow the `State/Provider` + `State/Processor` pattern
- Avoid raw Symfony controllers; use them only when API Platform's abstraction is insufficient
- Every PHP file must start with `declare(strict_types=1)` (project-wide rule)

## Alternatives considered
Custom Symfony controllers were the previous pattern (legacy frontend code). The API Platform
approach was adopted to reduce boilerplate, improve documentation, and enable standard-compliant output.
