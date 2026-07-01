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
        // Agentic chat drives a real tool-use loop ({@see \App\AI\Runtime\ToolUseOrchestrator}),
        // which the planner can't express. Emit a deterministic tool-call sequence
        // instead so the loop actually runs (and produces a tool trail) under
        // AI_MODEL_PROVIDER=mock — mirroring the Anthropic tool-use contract.
        if (AssistantTask::AGENTIC_CHAT === $request->taskType) {
            return $this->agenticResponse($request);
        }

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

    /**
     * Deterministic agentic turn: issue the next planned tool call, or — once the
     * plan is exhausted (all tool turns are in the transcript) — return the final
     * answer. The orchestrator dispatches the calls against the real tools, so the
     * result reflects the actual library/search just like a real provider would.
     */
    private function agenticResponse(ModelRequest $request): ModelResponse
    {
        $message = $this->lastUserMessage($request->input);
        $transcript = \is_array($request->input['toolTranscript'] ?? null) ? array_values($request->input['toolTranscript']) : [];
        $plan = $this->planTools($message);
        $issued = $this->countIssuedTurns($transcript);

        $output = $issued < \count($plan)
            ? ['content' => '', 'tool_calls' => [[
                'id' => 'mock-tu-' . ($issued + 1),
                'name' => $plan[$issued],
                'arguments' => $this->argumentsFor($plan[$issued], $message, $transcript),
            ]]]
            : ['content' => $this->finalAnswer($plan, $transcript)];

        return new ModelResponse(
            provider: 'mock',
            model: $this->modelName,
            outputSchemaVersion: $request->outputSchemaVersion,
            output: $output,
            available: true,
            message: 'Using the deterministic mock AI gateway.',
        );
    }

    /**
     * @return list<string> ordered tool names to call for the message intent
     */
    private function planTools(string $message): array
    {
        // Leading word-boundary only: these are prefixes (harmoni → harmonize,
        // standardi → standardize, librar → library), so a trailing \b would fail
        // mid-word.
        return match (true) {
            1 === preg_match('/\b(import|save|add)/', $message) => ['list_my_references', 'import_reference'],
            1 === preg_match('/\b(standardi|harmoni|cluster|canonical|link|reconcile)/', $message) => ['list_my_references', 'standardize_references'],
            1 === preg_match('/\b(librar|my reference|saved)/', $message) => ['list_my_references'],
            default => ['search_reference_resources'],
        };
    }

    /**
     * @param list<mixed> $transcript
     *
     * @return array<string, mixed>
     */
    private function argumentsFor(string $tool, string $message, array $transcript): array
    {
        return match ($tool) {
            'search_reference_resources' => ['query' => '' !== $message ? $message : 'reference'],
            'list_my_references' => ['limit' => 50],
            'standardize_references' => ['referenceIds' => implode(',', $this->libraryReferenceIds($transcript))],
            'import_reference' => ['items' => $this->searchResults($transcript)],
            default => [],
        };
    }

    /**
     * @param list<string> $plan
     * @param list<mixed>  $transcript
     */
    private function finalAnswer(array $plan, array $transcript): string
    {
        $standardize = $this->toolResultData($transcript, 'standardize_references');
        if (null !== $standardize) {
            $clusters = \is_array($standardize['clusters'] ?? null) ? $standardize['clusters'] : [];

            return \sprintf(
                'I linked your references into %d concept%s and harmonized their metadata fields. Review the standardized records before importing.',
                \count($clusters),
                1 === \count($clusters) ? '' : 's',
            );
        }

        return \sprintf('I ran %s to answer that.', implode(', ', $plan));
    }

    /**
     * Number of assistant (tool-issuing) turns already in the transcript.
     *
     * @param list<mixed> $transcript
     */
    private function countIssuedTurns(array $transcript): int
    {
        $count = 0;
        foreach ($transcript as $turn) {
            if (\is_array($turn) && 'assistant' === ($turn['role'] ?? null)) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Federated ids from the most recent list_my_references result in the transcript.
     *
     * @param list<mixed> $transcript
     *
     * @return list<string>
     */
    private function libraryReferenceIds(array $transcript): array
    {
        $data = $this->toolResultData($transcript, 'list_my_references');
        $references = \is_array($data['references'] ?? null) ? $data['references'] : [];

        $ids = [];
        foreach ($references as $reference) {
            $referenceId = \is_array($reference) ? trim((string) ($reference['referenceId'] ?? '')) : '';
            if ('' !== $referenceId) {
                $ids[] = $referenceId;
            }
        }

        return $ids;
    }

    /**
     * @param list<mixed> $transcript
     *
     * @return list<array<string, mixed>>
     */
    private function searchResults(array $transcript): array
    {
        $data = $this->toolResultData($transcript, 'search_reference_resources');

        return $this->asList($data['results'] ?? []);
    }

    /**
     * The result `data` for the most recent successful call to the given tool.
     *
     * @param list<mixed> $transcript
     *
     * @return array<string, mixed>|null
     */
    private function toolResultData(array $transcript, string $toolName): ?array
    {
        foreach (array_reverse($transcript) as $turn) {
            if (!\is_array($turn) || 'tool' !== ($turn['role'] ?? null)) {
                continue;
            }
            foreach (\is_array($turn['toolResults'] ?? null) ? $turn['toolResults'] : [] as $result) {
                if (\is_array($result) && $toolName === ($result['name'] ?? null) && \is_array($result['data'] ?? null)) {
                    return $result['data'];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function lastUserMessage(array $input): string
    {
        $messages = \is_array($input['messages'] ?? null) ? $input['messages'] : [];
        foreach (array_reverse($messages) as $message) {
            if (\is_array($message) && 'user' === ($message['role'] ?? null)) {
                $content = trim((string) ($message['content'] ?? ''));
                if ('' !== $content) {
                    return mb_strtolower($content);
                }
            }
        }

        return '';
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
