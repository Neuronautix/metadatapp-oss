# AI Pipeline

## Canonical pipeline

1. **Collect context**
   - Read metadata resources, schemas, and controlled vocabularies through approved APIs or MCP tools.
2. **Build structured request**
   - Convert the task into a `CurationRequest` with a request ID, prompt version, task type, and context sources.
3. **Invoke the model gateway**
   - Call `ModelGatewayInterface` with provider-agnostic request/response objects.
4. **Validate and govern**
   - Validate schema shape, domain rules, provenance coverage, and confidence thresholds.
5. **Require human review for writes**
   - Expose patch proposals for accept/edit/reject decisions before any persistence step.
6. **Write back through standard application paths**
   - Use existing processors/services so normal authorization, validation, and audit hooks still apply.
7. **Record traces and evaluation output**
   - Store trace metadata and compare outcomes against evaluation baselines.


## Seed traceable workflow

The repository now includes a baseline traceable workflow for **metadata normalization**:

- prompt: `prompts/tasks/metadata-normalization.v1.md`
- output schema: `ai.patch-proposal.v1`
- governance policy: `policies/ai/curation-governance-policy.yaml`
- eval dataset: `evals/datasets/metadata-normalization-smoke.yaml`
- eval case: `evals/cases/metadata-normalization-smoke.yaml`
- example report: `evals/reports/metadata-normalization-smoke.example.md`
- structured log fields: `App\AI\Governance\AiTraceContext`

Future workflows should provide the same chain of prompt -> policy -> eval case -> reportable trace metadata.

## Output contract

Curation workflows produce structured outputs only:

- request metadata (`request_id`, `task_type`, `prompt_version`)
- rationale
- provenance/evidence references
- patch proposal operations
- evaluator result
- human approval state

## Minimum acceptance metrics

The initial governance baseline for new AI curation workflows is:

- **Structured output validity:** `>= 0.99`
- **Schema validation pass rate:** `1.00`
- **Policy validation pass rate:** `1.00`
- **Provenance coverage for proposed edits:** `1.00`
- **Human approval rate for write-back:** `1.00`

If any required metric is missing or below threshold, the workflow must fail closed.

## Evaluation requirements

- Add or update at least one eval case for every new AI workflow
- Keep benchmark inputs under `evals/datasets/`
- Keep executable or declarative eval cases under `evals/cases/`
- Keep generated summaries under `evals/reports/`
- Prefer deterministic validators before LLM-as-judge fallbacks

## Observability requirements

Every run should be traceable end to end with:

- a stable request ID
- provider/model/prompt version metadata
- references to context sources used to build the prompt
- output schema version
- evaluator decision
- human approval state

## Sandbox escalation path

Use the runtime sandbox only when a workflow needs code execution, batch automation, or autonomous tool use. Metadata suggestion, normalization, and review should stay outside the sandbox by default.
