# Evolution Report - PR #199

## Merge metadata

- Date: 2026-04-03
- PR: #199
- Title: feat: AI MCP bridge – backend-internal read-only tool dispatch for /ai/chat
- Branch: Neuronautix/copilot/ai-mcp-integration
- Contributors: copilot
- Reviewer(s): TBD

## What was merged

- Added backend MCP bridge service to dispatch read-only tool calls from AI chat flows.
- Integrated bridge behavior into AI runtime assistant service and configuration wiring.
- Added sensor-agent client abstraction and interface to decouple bridge logic from concrete transport.
- Added API and unit tests that validate MCP bridge behavior and AI controller integration.

## What it brings

- Enables AI chat endpoints to query internal capabilities through a controlled backend tool-dispatch path.
- Creates a clear extension seam for adding additional read-only MCP tools without controller sprawl.
- Improves confidence in MCP behavior through dedicated bridge unit coverage.

## Benefits

- User benefit: AI assistant responses can leverage live internal data/tools more reliably.
- Product benefit: Unlocks assistant capabilities that depend on backend-integrated context retrieval.
- Engineering benefit: Interface-driven bridge design reduces coupling and improves testability.
- Operational benefit: Read-only dispatch model provides a safer initial security posture for MCP rollout.

## Long-term vision

- Strategic theme: Safe AI platform integration through explicit backend orchestration.
- Horizon impact: Long term foundation for progressive MCP tool expansion.
- Future opportunities unlocked: Multi-tool orchestration, richer assistant workflows, and scoped tool governance by role.

## Risks and tradeoffs

- Read-only constraints must remain enforced as new tools are added to avoid privilege creep.
- Tool selection and keyword matching logic should be monitored for false positives/negatives in production prompts.

## Follow-up actions

- [ ] Add explicit allowlist documentation for MCP tools exposed to AI chat (owner: backend AI maintainers, target: 2026-04-18)
- [ ] Add telemetry for bridge tool call frequency and failures (owner: platform maintainers, target: 2026-04-25)

## References

- Merge commit: d06d9f72dd4046e4f7a415f6c96507b64d2bdd7b
- Key files: api/src/AI/Mcp/McpBridgeService.php, api/src/AI/Runtime/AiAssistantService.php, api/src/Service/SensorAgentClientInterface.php, api/tests/Unit/AI/Mcp/McpBridgeServiceTest.php
