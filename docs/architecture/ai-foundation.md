# AI Foundation

## Purpose

Metadatapp treats AI as a controlled subsystem with explicit boundaries. AI features may assist curation, but they must not bypass validation, audit, or human review.

## System boundaries

The canonical backend namespaces for AI work are:

- `api/src/AI/Mcp/` for MCP-facing tool policies and contracts
- `api/src/AI/Gateway/` for provider-agnostic model access
- `api/src/AI/Curation/` for structured curation request and proposal objects
- `api/src/AI/Governance/` for validation, traceability, approval, and fail-closed controls
- `api/src/AI/Evals/` for evaluation thresholds and regression contracts
- `api/src/AI/Runtime/` for sandbox execution policy

## Allowed data flows

1. **MCP -> Curation input**: metadata reads, schemas, vocabularies, and ontology context may be collected through MCP or the API.
2. **Curation -> Gateway**: curation services build structured requests and call `App\AI\Gateway\ModelGatewayInterface` only.
3. **Gateway -> Governance**: model responses are normalized into structured outputs before any downstream decision.
4. **Governance -> Human review**: patch proposals, rationale, and provenance are validated and reviewed before write-back.
5. **Approved write-back -> existing application write paths**: approved changes must go through existing Symfony/API Platform validation and audit paths.

## Disallowed data flows

- Domain or controller code calling a provider SDK directly
- Raw LLM text writing to production entities
- Sandbox/code execution enabled by default
- Write-enabled MCP tools without explicit approval and audit controls

## Approved write paths

AI output may only reach persistent records when all of the following are true:

- the output is structured as a patch proposal
- schema and business-rule validation succeed
- provenance is present for every suggested edit
- human approval is recorded
- the final write uses standard application processors/services

## Provider abstraction rules

- Provider selection is configured centrally through AI feature flags
- Business code depends on `ModelGatewayInterface`, never on provider clients
- Providers must emit request IDs, provider name, model name, and prompt version for every invocation
- Unsupported or unconfigured providers must fail closed

## Prompt and policy storage rules

- Prompt files live under `prompts/system/` and `prompts/tasks/`
- Prompt filenames are versioned, for example `metadata-curation-assistant.v1.md`
- Policies live under `policies/ai/`
- Prompts and policies are reviewed in pull requests like code
- Prompt changes must identify the output schema version they target

## Logging and traceability

Every AI action must be able to emit structured log context with at least:

- `request_id`
- `task_type`
- `provider`
- `model`
- `prompt_version`
- `context_sources`
- `output_schema_version`
- `evaluator_result`
- `human_approval_state`

## Feature flags and config boundaries

The baseline AI configuration is defined through environment-backed flags:

- provider selection
- enabled task types
- sandbox enabled/disabled
- human approval required
- write actions enabled/disabled

Default behavior is fail-closed:

- write actions are disabled
- human approval is required
- sandbox execution is disabled

## Human-in-the-loop requirement

Curation write-back requires a human reviewer to accept, edit, or reject each proposal. Reviewer attribution and approval state must be captured in the application audit trail.

## Sandbox policy

Sandbox execution is optional and reserved for workflows that require code execution or autonomous tool use. Sandbox tasks must be isolated, scoped to an approved task type, and disabled unless explicitly enabled by configuration.
