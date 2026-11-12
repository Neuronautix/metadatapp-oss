<?php

declare(strict_types=1);

namespace App\AI\Mcp;

use App\Service\SensorAgentClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Backend-internal bridge that dispatches approved read-only MCP tool calls
 * during AI assistant chat requests. Results are surfaced as provenance entries
 * in the /ai/chat response; no write path is exposed.
 */
final readonly class McpBridgeService
{
    private const CHAT_TASK_TYPE = 'assistant_chat';

    /**
     * Maps specific intent keywords to the corresponding tool.
     *
     * @var array<string, string>
     */
    private const KEYWORD_TOOL_MAP = [
        // Health-specific queries
        'health' => 'get_live_sensor_health',
        'healthy' => 'get_live_sensor_health',
        'online' => 'get_live_sensor_health',
        'uptime' => 'get_live_sensor_health',
        'heartbeat' => 'get_live_sensor_health',
        // Observation-specific queries
        'temperature' => 'get_live_sensor_observation',
        'humidity' => 'get_live_sensor_observation',
        'pressure' => 'get_live_sensor_observation',
        'reading' => 'get_live_sensor_observation',
        'observation' => 'get_live_sensor_observation',
        'latest' => 'get_live_sensor_observation',
        'metric' => 'get_live_sensor_observation',
        // Threshold/alarm-specific queries
        'threshold' => 'get_live_sensor_threshold_status',
        'alarm' => 'get_live_sensor_threshold_status',
        'alert' => 'get_live_sensor_threshold_status',
        'limit' => 'get_live_sensor_threshold_status',
        'breach' => 'get_live_sensor_threshold_status',
    ];

    /**
     * Generic sensor keywords that trigger the safest single-tool fallback.
     *
     * @var list<string>
     */
    private const GENERIC_SENSOR_KEYWORDS = ['sensor', 'live'];

    /**
     * Sanitized error string returned to the client when a tool call fails.
     */
    private const TOOL_CALL_FAILED_MESSAGE = 'Tool call failed.';

    /**
     * @param list<ToolAccessPolicy> $approvedTools
     */
    public function __construct(
        private bool $enabled,
        private array $approvedTools,
        private SensorAgentClientInterface $sensorAgent,
        private LoggerInterface $logger,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Resolves tool intent from the request input, enforces read-only and
     * no-human-approval-required access policy on each candidate tool, and
     * returns one result per dispatched call.
     *
     * @param array<string, mixed> $input
     *
     * @return list<McpToolCallResult>
     */
    public function callApprovedTools(string $taskType, array $input): array
    {
        if (!$this->enabled) {
            return [];
        }

        if (self::CHAT_TASK_TYPE !== $taskType) {
            $this->logger->debug('MCP bridge: task type is not supported.', ['task_type' => $taskType]);

            return [];
        }

        $targetTools = $this->resolveIntent($input);
        if ([] === $targetTools) {
            return [];
        }

        $results = [];

        foreach ($targetTools as $toolName) {
            $policy = $this->findApprovedPolicy($toolName);

            if (null === $policy) {
                $this->logger->debug('MCP bridge: tool not in approved list, skipping.', ['tool' => $toolName, 'task_type' => $taskType]);
                continue;
            }

            if (!$policy->allowsRead || $policy->allowsWrite) {
                $this->logger->warning('MCP bridge: skipping tool that is not read-only.', ['tool' => $toolName]);
                continue;
            }

            if ($policy->requiresHumanApproval) {
                $this->logger->warning('MCP bridge: skipping tool that requires human approval.', ['tool' => $toolName, 'task_type' => $taskType]);
                continue;
            }

            $results[] = $this->callTool($toolName);
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return list<string>
     */
    private function resolveIntent(array $input): array
    {
        $lastMessage = $this->extractLastUserMessage($input);
        if ('' === $lastMessage) {
            return [];
        }

        $lower = strtolower($lastMessage);

        // Specific-keyword detection comes first so only relevant tools are dispatched.
        $specificToolSet = [];
        foreach (self::KEYWORD_TOOL_MAP as $keyword => $toolName) {
            if (!$this->containsWord($lower, $keyword)) {
                continue;
            }

            $specificToolSet[$toolName] = true;
        }

        if ([] !== $specificToolSet) {
            return array_keys($specificToolSet);
        }

        // Generic sensor queries fall back to the safest minimal read path.
        foreach (self::GENERIC_SENSOR_KEYWORDS as $keyword) {
            if ($this->containsWord($lower, $keyword)) {
                return ['get_live_sensor_observation'];
            }
        }

        return [];
    }

    private function callTool(string $toolName): McpToolCallResult
    {
        $start = microtime(true);

        try {
            $data = match ($toolName) {
                'get_live_sensor_health' => $this->sensorAgent->getHealth(),
                'get_live_sensor_observation' => $this->sensorAgent->getLatestObservation(),
                'get_live_sensor_threshold_status' => $this->sensorAgent->getThresholdStatus(),
                default => throw new \InvalidArgumentException(\sprintf('No backend handler for MCP tool "%s".', $toolName)),
            };

            $duration = (int) round((microtime(true) - $start) * 1000);

            $this->logger->info('MCP bridge: tool call succeeded.', [
                'tool' => $toolName,
                'duration_ms' => $duration,
            ]);

            return new McpToolCallResult(
                toolName: $toolName,
                success: true,
                data: $data,
                callDurationMs: $duration,
            );
        } catch (\Throwable $exception) {
            $duration = (int) round((microtime(true) - $start) * 1000);

            $this->logger->warning('MCP bridge: tool call failed.', [
                'tool' => $toolName,
                'exception_class' => $exception::class,
                'error' => $exception->getMessage(),
                'duration_ms' => $duration,
            ]);

            return new McpToolCallResult(
                toolName: $toolName,
                success: false,
                error: self::TOOL_CALL_FAILED_MESSAGE,
                callDurationMs: $duration,
            );
        }
    }

    private function findApprovedPolicy(string $toolName): ?ToolAccessPolicy
    {
        foreach ($this->approvedTools as $policy) {
            if ($policy->toolName === $toolName) {
                return $policy;
            }
        }

        return null;
    }

    /**
     * Returns true when $haystack contains $word as a whole word (Unicode word boundary).
     */
    private function containsWord(string $haystack, string $word): bool
    {
        return 1 === preg_match('/\b' . preg_quote($word, '/') . '\b/u', $haystack);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function extractLastUserMessage(array $input): string
    {
        $messages = $input['messages'] ?? [];

        if (!\is_array($messages)) {
            return '';
        }

        foreach (array_reverse($messages) as $message) {
            if (!\is_array($message)) {
                continue;
            }

            if ('user' === ($message['role'] ?? null)) {
                return (string) ($message['content'] ?? '');
            }
        }

        return '';
    }
}
