<?php

declare(strict_types=1);

namespace App\AI\Gateway;

use App\AI\Governance\AiTraceContext;
use App\AI\Runtime\AssistantActionPlanner;
use App\AI\Runtime\AssistantTask;

final readonly class MockModelGateway implements ModelGatewayInterface, GatewayStatusAwareInterface
{
    public function __construct(
        private AssistantActionPlanner $planner,
        private string $modelName = 'mock-assistant.v1',
    ) {
    }

    public function invoke(ModelRequest $request): ModelResponse
    {
        $planned = $this->planner->plan($request->taskType, $request->input);

        return new ModelResponse(
            provider: 'mock',
            model: $this->modelName,
            outputSchemaVersion: $request->outputSchemaVersion,
            output: [
                'content' => (string) ($planned['content'] ?? ''),
                'suggestions' => $this->asList($planned['suggestions'] ?? []),
                'structured_output' => $this->asArray($planned['structured_output'] ?? []),
            ],
            available: true,
            message: 'Using the deterministic mock AI gateway.',
            provenance: $this->asList($planned['provenance'] ?? []),
            warnings: [],
        );
    }

    public function status(AiTraceContext $traceContext, array $capabilities = []): GatewayStatus
    {
        return new GatewayStatus(
            enabled: true,
            available: true,
            configured: true,
            realProvider: false,
            provider: 'mock',
            model: $this->modelName,
            message: 'Using the deterministic mock AI gateway.',
            capabilities: [] !== $capabilities ? $capabilities : AssistantTask::interactiveCapabilities(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function asArray(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function asList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => \is_array($item)));
    }
}
