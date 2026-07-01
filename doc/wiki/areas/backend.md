---
title: "Area: Backend"
type: area
updated: 2026-05-05
source_prs: [109, 115, 123, 139, 149, 177, 178, 186, 194, 199, 202, 209, 210, 244]
related: [decisions/api-platform-conventions.md, decisions/backend-as-source-of-truth.md, decisions/domain-driven-workflow-entities.md, decisions/ai-mcp-read-only.md]
---

# Area: Backend

## Stack

| Component | Version / Tool |
|-----------|---------------|
| Language | PHP 8.4 (strict types required on every file) |
| Framework | Symfony 7.4 |
| API layer | API Platform 4.3 |
| ORM | Doctrine ORM 3.x |
| Server | FrankenPHP |
| Task runner | Castor |

## Key conventions

1. **`declare(strict_types=1)` at the top of every PHP file** — non-negotiable.
2. **State processors and providers, not controllers** — see [decisions/api-platform-conventions.md](../decisions/api-platform-conventions.md).
3. **Entities use `#[ApiResource]` + Doctrine attributes; UUIDs via `UuidGenerator`.**
4. **Tenant/user scoping via `AccountAwareInterface` and `UserAwareInterface`** — every resource must scope to the right tenant.
5. **Tests use `ApiTestCase` + Foundry factories + `ResetDatabase`** — never instantiate entities manually.
6. **All external API access is backend-only** — see [decisions/backend-as-source-of-truth.md](../decisions/backend-as-source-of-truth.md).

## Directory structure

| Directory | Purpose |
|-----------|---------|
| `Entity/` | Doctrine entities |
| `State/Provider/` | Custom API Platform read behavior |
| `State/Processor/` | Custom API Platform write behavior |
| `ConnectedApps/Apps/` | External system integrations (Zefix, elabFTW, sensors, …) |
| `Curation/` | LLM-based curation via CurateGPT |
| `Security/` | Auth and tenant isolation |
| `DataFixtures/Factory/` | Foundry factories |

## Recent significant changes

| PR | Date | Change |
|----|------|--------|
| #244 | 2026-05-05 | Connected resource link resource + export propagation, encrypted user-scoped AI provider credentials behind `SecretStoreInterface`, circadian metrics/reporting expansion, open-source-readiness hardening |
| #210 | 2026-04-09 | Full curation workflow entities (`SessionImport`, `Proposal`, `PatchReview`) + `WorkflowProcessor` |
| #209 | 2026-04-09 | `FairReportController`, `FairReportPdfService`, FAIR MCP tool |
| #202 | 2026-04-09 | elabFTW sync service |
| #199 | 2026-04-03 | `McpBridgeService` + `SensorAgentClientInterface` |
| #194 | 2026-04-03 | API consistency pass |
| #186 | 2026-04-02 | Sensor API proxy + alarm model |
| #149 | 2026-03-24 | MCP validation stabilization (Mercure + IMPC schema) |
| #139 | 2026-03-23 | API Platform 4.3 + MCP integration + serializer modernization |

## Active features (backend-heavy)

- [features/fair-checking.md](../features/fair-checking.md)
- [features/curation.md](../features/curation.md)
- [features/zefix.md](../features/zefix.md)
- [features/elabftw.md](../features/elabftw.md)
- [features/ai-mcp.md](../features/ai-mcp.md)
- [features/sensors.md](../features/sensors.md)
- [features/metadata-standards.md](../features/metadata-standards.md)

## Known debt

- MCP read-only enforcement is convention-based, not policy-enforced — see [tech-debt.md](../tech-debt.md)
- Import session negative-path test coverage missing
- elabFTW API coupling risk
- `SecretStoreInterface` is in place, but no managed secret-store adapter is shipped yet
