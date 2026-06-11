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
