<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Gateway\ModelRequest;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Governance\AiTraceContext;
use App\AI\Governance\HumanApprovalState;
use App\AI\Runtime\Dto\AiChatRequest;
use App\AI\Runtime\Dto\AiSuggestRequest;

final readonly class AssistantTaskMapper
{
    public function __construct(
        private AiFeatureFlags $featureFlags,
    ) {
    }

    public function mapStatus(): AiTraceContext
    {
        return $this->createTrace(
            taskType: AssistantTask::STATUS,
            promptVersion: 'assistant-status.v1',
            outputSchemaVersion: 'ai.assistant.status.v1',
            route: '/ai/status',
            action: 'status',
            context: [],
        );
    }

    public function mapChat(AiChatRequest $request): MappedAssistantTask
    {
        $trace = $this->createTrace(
            taskType: AssistantTask::CHAT,
            promptVersion: 'assistant-chat.v1',
            outputSchemaVersion: 'ai.assistant.chat.v1',
            route: '/ai/chat',
            action: 'chat',
            context: $request->context,
        );

        $payload = [
            'messages' => array_map(static fn ($message) => $message->jsonSerialize(), $request->messages),
            'context' => $request->context,
        ];

        return new MappedAssistantTask(
            action: 'chat',
            request: new ModelRequest(
                requestId: $trace->requestId,
                taskType: AssistantTask::CHAT,
                promptVersion: $trace->promptVersion,
                outputSchemaVersion: $trace->outputSchemaVersion,
                input: $payload,
                contextSources: $trace->contextSources,
            ),
            trace: $trace,
        );
    }

    public function mapAgenticChat(AiChatRequest $request): MappedAssistantTask
    {
        $trace = $this->createTrace(
            taskType: AssistantTask::AGENTIC_CHAT,
            promptVersion: 'assistant-agentic-chat.v1',
            outputSchemaVersion: 'ai.assistant.agentic-chat.v1',
            route: '/ai/agentic-chat',
            action: 'agentic_chat',
            context: $request->context,
        );

        $payload = [
            'messages' => array_map(static fn ($message) => $message->jsonSerialize(), $request->messages),
            'context' => $request->context,
        ];

        return new MappedAssistantTask(
            action: 'agentic_chat',
            request: new ModelRequest(
                requestId: $trace->requestId,
                taskType: AssistantTask::AGENTIC_CHAT,
                promptVersion: $trace->promptVersion,
                outputSchemaVersion: $trace->outputSchemaVersion,
                input: $payload,
                contextSources: $trace->contextSources,
            ),
            trace: $trace,
        );
    }

    public function mapSuggest(AiSuggestRequest $request): MappedAssistantTask
    {
        $trace = $this->createTrace(
            taskType: $request->action,
            promptVersion: $this->promptVersionFor($request->action),
            outputSchemaVersion: 'ai.assistant.suggest.v1',
            route: '/ai/suggest',
            action: 'suggest',
            context: $request->context,
            contextType: $request->contextType,
        );

        return new MappedAssistantTask(
            action: 'suggest',
            request: new ModelRequest(
                requestId: $trace->requestId,
                taskType: $request->action,
                promptVersion: $trace->promptVersion,
                outputSchemaVersion: $trace->outputSchemaVersion,
                input: [
                    'action' => $request->action,
                    'contextType' => $request->contextType,
                    'context' => $request->context,
                    'prompt' => $request->prompt,
                ],
                contextSources: $trace->contextSources,
            ),
            trace: $trace,
        );
    }

    /**
     * Maps an "AI Compare & Link" request (selected reference results + their
     * grounded ontology candidates) to a model task for the Reference Hub.
     *
     * @param list<array<string, mixed>>                                                                             $items
     * @param array<string, list<array{iri: string, label: string, ontology: string, score: float, source: string}>> $grounded
     */
    public function mapStandardize(array $items, array $grounded): MappedAssistantTask
    {
        $trace = $this->createTrace(
            taskType: AssistantTask::STANDARDIZE_REFERENCES,
            promptVersion: 'standardize-references.v1',
            outputSchemaVersion: 'ai.reference.standardize.v1',
            route: '/references/standardize',
            action: 'standardize',
            context: [],
        );

        return new MappedAssistantTask(
            action: 'standardize',
            request: new ModelRequest(
                requestId: $trace->requestId,
                taskType: AssistantTask::STANDARDIZE_REFERENCES,
                promptVersion: $trace->promptVersion,
                outputSchemaVersion: $trace->outputSchemaVersion,
                input: [
                    'items' => $items,
                    'groundedCandidates' => $grounded,
                ],
                contextSources: $trace->contextSources,
            ),
            trace: $trace,
        );
    }

    /**
     * Maps a schema comparison request (extracted field lists from each selected
     * reference result) to a model task for the Reference Hub compare-schemas flow.
     *
     * @param list<array{sourceRef: string, sourceName: string, type: string, fields: list<array<string, mixed>>}> $extractedSchemas
     */
    public function mapCompareSchemas(array $extractedSchemas): MappedAssistantTask
    {
        $trace = $this->createTrace(
            taskType: AssistantTask::COMPARE_SCHEMAS,
            promptVersion: 'compare-schemas.v1',
            outputSchemaVersion: 'ai.reference.compare-schemas.v1',
            route: '/references/compare-schemas',
            action: 'compare_schemas',
            context: [],
        );

        return new MappedAssistantTask(
            action: 'compare_schemas',
            request: new ModelRequest(
                requestId: $trace->requestId,
                taskType: AssistantTask::COMPARE_SCHEMAS,
                promptVersion: $trace->promptVersion,
                outputSchemaVersion: $trace->outputSchemaVersion,
                input: [
                    'schemas' => $extractedSchemas,
                ],
                contextSources: $trace->contextSources,
            ),
            trace: $trace,
        );
    }

    /**
     * Maps a crosswalk mapping-refinement request (an extracted field + the
     * deterministic candidate suggestions) to a model task for the Crosswalk Studio.
     *
     * @param array<string, mixed> $payload
     */
    public function mapRefineCrosswalkMapping(array $payload): MappedAssistantTask
    {
        $trace = $this->createTrace(
            taskType: AssistantTask::REFINE_CROSSWALK_MAPPING,
            promptVersion: 'refine-crosswalk-mapping.v1',
            outputSchemaVersion: 'ai.crosswalk.mapping.v1',
            route: '/crosswalks/suggest',
            action: 'refine',
            context: [],
        );

        return new MappedAssistantTask(
            action: 'refine',
            request: new ModelRequest(
                requestId: $trace->requestId,
                taskType: AssistantTask::REFINE_CROSSWALK_MAPPING,
                promptVersion: $trace->promptVersion,
                outputSchemaVersion: $trace->outputSchemaVersion,
                input: $payload,
                contextSources: $trace->contextSources,
            ),
            trace: $trace,
        );
    }

    /**
     * Maps a guideline per-field draft request (§7.3) to a model task. The
     * payload carries the field id, label, section context, severity, the
     * PREPARE planning prompt or EQIPD guidance, the crosswalk sibling ids as
     * context_keys, and the existing values of any filled context keys.
     *
     * @param array<string, mixed> $payload
     */
    public function mapDraftGuidelineField(array $payload): MappedAssistantTask
    {
        $trace = $this->createTrace(
            taskType: AssistantTask::DRAFT_GUIDELINE_FIELD,
            promptVersion: 'draft-guideline-field.v1',
            outputSchemaVersion: 'ai.guideline.field.v1',
            route: '/guidelines/ai-draft',
            action: 'draft',
            context: [],
        );

        return new MappedAssistantTask(
            action: 'draft',
            request: new ModelRequest(
                requestId: $trace->requestId,
                taskType: AssistantTask::DRAFT_GUIDELINE_FIELD,
                promptVersion: $trace->promptVersion,
                outputSchemaVersion: $trace->outputSchemaVersion,
                input: $payload,
                contextSources: $trace->contextSources,
            ),
            trace: $trace,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createTrace(
        string $taskType,
        string $promptVersion,
        string $outputSchemaVersion,
        string $route,
        string $action,
        array $context,
        ?string $contextType = null,
    ): AiTraceContext {
        $resourceType = isset($context['resourceType']) && \is_string($context['resourceType']) ? $context['resourceType'] : null;
        $resourceId = isset($context['resourceId']) && \is_scalar($context['resourceId']) ? (string) $context['resourceId'] : null;
        $sources = [];

        if (null !== $contextType && '' !== $contextType) {
            $sources[] = 'context:' . $contextType;
        }

        if (null !== $resourceType) {
            $sources[] = 'resource:' . $resourceType;
        }

        if (null !== $resourceType && null !== $resourceId) {
            $sources[] = 'resource:' . $resourceType . ':' . $resourceId;
        }

        return AiTraceContext::create(
            taskType: $taskType,
            provider: $this->featureFlags->provider(),
            model: $this->featureFlags->defaultModel(),
            promptVersion: $promptVersion,
            outputSchemaVersion: $outputSchemaVersion,
            humanApprovalState: $this->featureFlags->humanApprovalRequired() ? HumanApprovalState::Required : HumanApprovalState::Approved,
            contextSources: $sources,
            action: $action,
            route: $route,
            resourceType: $resourceType,
            resourceId: $resourceId,
        );
    }

    private function promptVersionFor(string $taskType): string
    {
        return match ($taskType) {
            AssistantTask::SUGGEST_FORM_INPUTS => 'suggest-form-inputs.v1',
            AssistantTask::EXPLAIN_MISSING_METADATA => 'explain-missing-metadata.v1',
            AssistantTask::DRAFT_QUERY_FILTERS => 'draft-query-filters.v1',
            AssistantTask::GENERATE_EXPORT_CONTENT => 'generate-export-content.v1',
            default => 'assistant-task.v1',
        };
    }
}
