<?php

declare(strict_types=1);

namespace App\AI\Runtime;

use App\AI\Gateway\ModelGatewayInterface;
use App\AI\Gateway\ModelRequest;
use App\AI\Gateway\ModelResponse;
use App\AI\Governance\AiFeatureFlags;
use App\AI\Mcp\McpToolCallResult;
use App\AI\Mcp\ToolAccessPolicy;
use Psr\Log\LoggerInterface;

/**
 * Bounded multi-step tool-use ("agentic") loop over the approved MCP tools.
 *
 * Given an initial {@see ModelRequest}, it repeatedly:
 *   1. invokes the gateway,
 *   2. parses tool-call requests from the structured model output
 *      (`ModelResponse->output['tool_calls']`, a list of `{name, arguments}`),
 *   3. authorizes each call against the tool registry ({@see ToolAccessPolicy}) —
 *      write tools are dispatched only when {@see AiFeatureFlags::allowsWriteBack()}
 *      is true,
 *   4. dispatches each approved call via the {@see ToolDispatcherInterface},
 *   5. feeds the results back into a follow-up request,
 * and stops when the model returns no further tool calls (a final answer) or the
 * iteration cap is reached.
 *
 * Governance: everything is OFF by default. Callers must check
 * {@see AiFeatureFlags::isAgenticModeEnabled()} and degrade to single-shot chat
 * when it is false; this class assumes the flag has already been checked but still
 * enforces the per-call write gate as defence in depth.
 *
 * Note on live providers: the loop is driven entirely by the deterministic
 * structured-output contract (`output['tool_calls']`). Wiring a live LLM provider
 * into this loop requires the gateway to surface `tool_calls` in that same shape —
 * a follow-up once a provider gateway emits native tool-call deltas.
 */
final readonly class ToolUseOrchestrator
{
    private const DEFAULT_MAX_ITERATIONS = 5;

    /**
     * @param list<ToolAccessPolicy> $toolRegistry
     */
    public function __construct(
        private AiFeatureFlags $featureFlags,
        private ToolDispatcherInterface $dispatcher,
        private array $toolRegistry,
        private AgenticToolCatalog $catalog,
        private LoggerInterface $logger,
        private int $maxIterations = self::DEFAULT_MAX_ITERATIONS,
    ) {
    }

    public function run(ModelGatewayInterface $gateway, ModelRequest $initialRequest): AgenticChatResult
    {
        $maxIterations = max(1, $this->maxIterations);
        $toolProvenance = [];
        $warnings = [];
        // The running tool transcript (assistant tool_use turns + tool_result turns)
        // is replayed to the gateway each iteration so a provider that speaks native
        // tool-use (e.g. Anthropic) can continue the conversation with valid pairing.
        $transcript = [];
        $response = $gateway->invoke($this->withAgenticContext($initialRequest, $transcript));
        $iterations = 1;

        while (true) {
            $toolCalls = $this->parseToolCalls($response);

            // No tool calls means the model produced its final answer.
            if ([] === $toolCalls) {
                break;
            }

            // Iteration cap: stop dispatching further tools and surface a warning.
            if ($iterations >= $maxIterations) {
                $warnings[] = \sprintf('Agentic tool-use loop stopped after reaching the %d-iteration cap.', $maxIterations);
                $this->logger->warning('Agentic loop hit iteration cap.', ['max_iterations' => $maxIterations]);

                return new AgenticChatResult(
                    finalResponse: $response,
                    toolProvenance: $toolProvenance,
                    iterations: $iterations,
                    iterationCapReached: true,
                    warnings: $warnings,
                );
            }

            $toolResults = [];
            foreach ($toolCalls as $call) {
                [$result, $warning] = $this->authorizeAndDispatch($call['name'], $call['arguments']);

                if (null !== $warning) {
                    $warnings[] = $warning;
                }

                if (null !== $result) {
                    $toolProvenance[] = $result->toProvenanceEntry();
                }

                // Every tool_use the model emitted MUST get a matching tool_result —
                // including refused/unknown calls — or a native tool-use provider
                // rejects the next turn. Refusals become an error result.
                $toolResults[] = [
                    'id' => $call['id'],
                    'name' => $call['name'],
                    'success' => null !== $result && $result->success,
                    'data' => null !== $result ? $result->data : [],
                    'error' => null !== $result ? $result->error : ($warning ?? 'Tool was not executed.'),
                ];
            }

            $transcript[] = ['role' => 'assistant', 'toolUse' => array_map(
                static fn (array $c): array => ['id' => $c['id'], 'name' => $c['name'], 'arguments' => $c['arguments']],
                $toolCalls,
            )];
            $transcript[] = ['role' => 'tool', 'toolResults' => $toolResults];

            $response = $gateway->invoke($this->withAgenticContext($initialRequest, $transcript));
            ++$iterations;
        }

        return new AgenticChatResult(
            finalResponse: $response,
            toolProvenance: $toolProvenance,
            iterations: $iterations,
            iterationCapReached: false,
            warnings: $warnings,
        );
    }

    /**
     * Augment the request with the available tool definitions and the running tool
     * transcript, so a native tool-use gateway can advertise tools and continue the
     * conversation. Gateways that don't support tools simply ignore both.
     *
     * @param list<array<string, mixed>> $transcript
     */
    private function withAgenticContext(ModelRequest $request, array $transcript): ModelRequest
    {
        $input = $request->input;
        $input['tools'] = $this->catalog->definitions($this->toolRegistry);
        $input['toolTranscript'] = $transcript;

        return new ModelRequest(
            requestId: $request->requestId,
            taskType: $request->taskType,
            promptVersion: $request->promptVersion,
            outputSchemaVersion: $request->outputSchemaVersion,
            input: $input,
            contextSources: $request->contextSources,
        );
    }

    /**
     * Authorizes a single tool call against the registry, then dispatches it.
     *
     * @param array<string, mixed> $arguments
     *
     * @return array{0: ?McpToolCallResult, 1: ?string} the result (or null when
     *                                                  refused/unknown) and an
     *                                                  optional warning message
     */
    private function authorizeAndDispatch(string $toolName, array $arguments): array
    {
        $policy = $this->findPolicy($toolName);

        if (null === $policy) {
            $this->logger->warning('Agentic loop: tool not in registry, skipping.', ['tool' => $toolName]);

            return [null, \sprintf('Tool "%s" is not registered and was skipped.', $toolName)];
        }

        // Write/mutating tools require human approval + write-back to be enabled.
        if ($policy->allowsWrite && !$this->featureFlags->allowsWriteBack()) {
            $this->logger->warning('Agentic loop: refused write tool, write-back disabled.', ['tool' => $toolName]);

            return [null, \sprintf('Write tool "%s" was refused because write actions require approval and are disabled.', $toolName)];
        }

        if (!$policy->allowsRead && !$policy->allowsWrite) {
            return [null, \sprintf('Tool "%s" is not callable and was skipped.', $toolName)];
        }

        return [$this->dispatcher->dispatch($toolName, $arguments), null];
    }

    /**
     * @return list<array{id: string, name: string, arguments: array<string, mixed>}>
     */
    private function parseToolCalls(ModelResponse $response): array
    {
        $raw = $response->output['tool_calls'] ?? null;
        if (!\is_array($raw)) {
            return [];
        }

        $calls = [];
        foreach ($raw as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            $name = $entry['name'] ?? null;
            if (!\is_string($name) || '' === trim($name)) {
                continue;
            }

            $arguments = $entry['arguments'] ?? [];

            $calls[] = [
                'id' => trim((string) ($entry['id'] ?? '')),
                'name' => trim($name),
                'arguments' => \is_array($arguments) ? $arguments : [],
            ];
        }

        return $calls;
    }

    private function findPolicy(string $toolName): ?ToolAccessPolicy
    {
        foreach ($this->toolRegistry as $policy) {
            if ($policy->toolName === $toolName) {
                return $policy;
            }
        }

        return null;
    }
}
