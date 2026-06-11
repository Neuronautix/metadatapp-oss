<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Gateway\GatewayStatus;
use App\AI\Gateway\GatewayStatusAwareInterface;
use App\AI\Gateway\ModelGatewayInterface;
use App\AI\Gateway\ModelGatewayRegistry;
use App\AI\Gateway\ModelResponse;
use App\AI\Gateway\NullModelGateway;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Governance\AiTraceContext;
use App\AI\Mcp\McpBridgeService;
use App\AI\Mcp\McpToolCallResult;
use App\AI\Runtime\Dto\AiChatRequest;
use App\AI\Runtime\Dto\AiChatResponse;
use App\AI\Runtime\Dto\AiStatusResponse;
use App\AI\Runtime\Dto\AiSuggestRequest;
use App\AI\Runtime\Dto\AiSuggestResponse;
use Psr\Log\LoggerInterface;

final readonly class AiAssistantService
{
    public function __construct(
        private AiFeatureFlags $featureFlags,
        private AssistantTaskMapper $taskMapper,
        private ModelGatewayRegistry $gatewayRegistry,
        private NullModelGateway $nullGateway,
        private McpBridgeService $mcpBridge,
        private LoggerInterface $logger,
    ) {
    }

    public function status(): AiStatusResponse
    {
        $trace = $this->taskMapper->mapStatus();
        $status = $this->statusForCurrentGateway($trace);
        $trace = $trace->withProvider($status->provider, $status->model);

        $this->logger->info('AI action completed', [
            'trace' => $trace->toLogContext(),
            'provider_status' => $status,
        ]);

        return new AiStatusResponse($status, $trace);
    }

    public function chat(AiChatRequest $request): AiChatResponse
    {
        $mappedTask = $this->taskMapper->mapChat($request);
        $this->guardTask($mappedTask->request->taskType);

        $mcpResults = $this->mcpBridge->callApprovedTools(
            $mappedTask->request->taskType,
            $mappedTask->request->input,
        );

        $response = $this->currentGateway()->invoke($mappedTask->request);
        $status = $this->statusFromModelResponse($response);
        $trace = $mappedTask->trace->withProvider($response->provider, $response->model);

        $this->logger->info('AI action completed', [
            'trace' => $trace->toLogContext(),
            'provider_status' => $status,
            'warning_count' => \count($response->warnings),
            'mcp_tool_calls' => \count($mcpResults),
        ]);

        $mcpProvenance = array_map(static fn (McpToolCallResult $r): array => $r->toProvenanceEntry(), $mcpResults);

        return new AiChatResponse(
            reply: (string) ($response->output['content'] ?? ''),
            status: $status,
            trace: $trace,
            provenance: [...$mcpProvenance, ...$response->provenance],
            warnings: $response->warnings,
            structuredOutput: isset($response->output['structured_output']) && \is_array($response->output['structured_output']) ? $response->output['structured_output'] : [],
        );
    }

    public function suggest(AiSuggestRequest $request): AiSuggestResponse
    {
        $mappedTask = $this->taskMapper->mapSuggest($request);
        $this->guardTask($mappedTask->request->taskType);

        $response = $this->currentGateway()->invoke($mappedTask->request);
        $status = $this->statusFromModelResponse($response);
        $trace = $mappedTask->trace->withProvider($response->provider, $response->model);
        $suggestions = isset($response->output['suggestions']) && \is_array($response->output['suggestions'])
            ? array_values(array_filter($response->output['suggestions'], static fn (mixed $entry): bool => \is_array($entry)))
            : [];

        $this->logger->info('AI action completed', [
            'trace' => $trace->toLogContext(),
            'provider_status' => $status,
            'task_action' => $request->action,
            'warning_count' => \count($response->warnings),
        ]);

        return new AiSuggestResponse(
            action: $request->action,
            summary: (string) ($response->output['content'] ?? ''),
            status: $status,
            trace: $trace,
            suggestions: $suggestions,
            structuredOutput: isset($response->output['structured_output']) && \is_array($response->output['structured_output']) ? $response->output['structured_output'] : [],
            provenance: $response->provenance,
            warnings: $response->warnings,
            reviewRequired: true,
            canApplyDirectly: false,
        );
    }

    private function guardTask(string $taskType): void
    {
        if (!$this->featureFlags->isAssistantEnabled()) {
            throw new \LogicException('AI assistant is disabled.');
        }

        if (!$this->featureFlags->allowsTask($taskType)) {
            throw new \LogicException(\sprintf('AI task "%s" is not enabled.', $taskType));
        }
    }

    private function currentGateway(): ModelGatewayInterface
    {
        if (!$this->featureFlags->isAssistantEnabled()) {
            return $this->nullGateway;
        }

        return $this->gatewayRegistry->getDefaultGateway();
    }

    private function statusForCurrentGateway(AiTraceContext $trace): GatewayStatus
    {
        if (!$this->featureFlags->isAssistantEnabled()) {
            return $this->nullGateway->status($trace);
        }

        if (!$this->gatewayRegistry->hasConfiguredGateway()) {
            return new GatewayStatus(
                enabled: true,
                available: false,
                configured: false,
                realProvider: false,
                provider: $this->featureFlags->provider(),
                model: $this->featureFlags->defaultModel(),
                message: \sprintf('Configured AI provider "%s" is not available.', $this->featureFlags->provider()),
                capabilities: $this->enabledCapabilities(),
            );
        }

        $gateway = $this->gatewayRegistry->getDefaultGateway();
        if ($gateway instanceof GatewayStatusAwareInterface) {
            return $gateway->status($trace, $this->enabledCapabilities());
        }

        return new GatewayStatus(
            enabled: true,
            available: true,
            configured: true,
            realProvider: false,
            provider: $this->featureFlags->provider(),
            model: $this->featureFlags->defaultModel(),
            message: 'AI gateway is configured.',
            capabilities: $this->enabledCapabilities(),
        );
    }

    private function statusFromModelResponse(ModelResponse $response): GatewayStatus
    {
        return new GatewayStatus(
            enabled: true,
            available: $response->available,
            configured: true,
            realProvider: 'mock' !== $response->provider,
            provider: $response->provider,
            model: $response->model,
            message: '' !== $response->message ? $response->message : 'AI gateway invoked successfully.',
            capabilities: $this->enabledCapabilities(),
        );
    }

    /**
     * @return list<string>
     */
    private function enabledCapabilities(): array
    {
        return array_values(array_intersect(
            AssistantTask::interactiveCapabilities(),
            $this->featureFlags->enabledTasks(),
        ));
    }
}
