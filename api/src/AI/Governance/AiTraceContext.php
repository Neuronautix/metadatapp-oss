<?php

declare(strict_types=1);

namespace App\AI\Governance;

final readonly class AiTraceContext implements \JsonSerializable
{
    /**
     * @param list<string> $contextSources
     */
    public function __construct(
        public string $requestId,
        public string $taskType,
        public string $provider,
        public string $model,
        public string $promptVersion,
        public array $contextSources,
        public string $outputSchemaVersion,
        public string $evaluatorResult,
        public HumanApprovalState $humanApprovalState,
        public ?string $action = null,
        public ?string $route = null,
        public ?string $resourceType = null,
        public ?string $resourceId = null,
        public ?\DateTimeImmutable $recordedAt = null,
    ) {
    }

    public static function create(
        string $taskType,
        string $provider,
        string $model,
        string $promptVersion,
        string $outputSchemaVersion,
        HumanApprovalState $humanApprovalState,
        array $contextSources = [],
        ?string $action = null,
        ?string $route = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        string $evaluatorResult = 'not_evaluated',
        ?string $requestId = null,
        ?\DateTimeImmutable $recordedAt = null,
    ): self {
        return new self(
            requestId: $requestId ?? bin2hex(random_bytes(8)),
            taskType: $taskType,
            provider: $provider,
            model: $model,
            promptVersion: $promptVersion,
            contextSources: array_values(array_unique(array_filter($contextSources, static fn (mixed $value): bool => \is_string($value) && '' !== $value))),
            outputSchemaVersion: $outputSchemaVersion,
            evaluatorResult: $evaluatorResult,
            humanApprovalState: $humanApprovalState,
            action: $action,
            route: $route,
            resourceType: $resourceType,
            resourceId: $resourceId,
            recordedAt: $recordedAt ?? new \DateTimeImmutable(),
        );
    }

    public function withProvider(string $provider, string $model, ?string $evaluatorResult = null): self
    {
        return new self(
            requestId: $this->requestId,
            taskType: $this->taskType,
            provider: $provider,
            model: $model,
            promptVersion: $this->promptVersion,
            contextSources: $this->contextSources,
            outputSchemaVersion: $this->outputSchemaVersion,
            evaluatorResult: $evaluatorResult ?? $this->evaluatorResult,
            humanApprovalState: $this->humanApprovalState,
            action: $this->action,
            route: $this->route,
            resourceType: $this->resourceType,
            resourceId: $this->resourceId,
            recordedAt: $this->recordedAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        return [
            'request_id' => $this->requestId,
            'task_type' => $this->taskType,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_version' => $this->promptVersion,
            'context_sources' => $this->contextSources,
            'output_schema_version' => $this->outputSchemaVersion,
            'evaluator_result' => $this->evaluatorResult,
            'human_approval_state' => $this->humanApprovalState->value,
            'action' => $this->action,
            'route' => $this->route,
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'recorded_at' => $this->recordedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'traceId' => $this->requestId,
            'taskType' => $this->taskType,
            'provider' => $this->provider,
            'model' => $this->model,
            'promptVersion' => $this->promptVersion,
            'contextSources' => $this->contextSources,
            'outputSchemaVersion' => $this->outputSchemaVersion,
            'evaluatorResult' => $this->evaluatorResult,
            'humanApprovalState' => $this->humanApprovalState->value,
            'action' => $this->action,
            'route' => $this->route,
            'resourceType' => $this->resourceType,
            'resourceId' => $this->resourceId,
            'recordedAt' => $this->recordedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
