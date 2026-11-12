<?php

declare(strict_types=1);

namespace App\AI\Gateway;

use App\AI\Governance\AiTraceContext;
use App\AI\Runtime\AssistantActionPlanner;
use App\AI\Runtime\AssistantTask;
use App\CurateGpt\Client\CurateGptClientInterface;
use App\CurateGpt\Client\Dto\CurateGptCandidateDto;
use App\CurateGpt\Client\Dto\CurateGptSearchRequestDto;

final readonly class CurateGptModelGateway implements ModelGatewayInterface, GatewayStatusAwareInterface
{
    private const DEFAULT_LOOKUP_QUERY = 'mouse';

    public function __construct(
        private CurateGptClientInterface $client,
        private AssistantActionPlanner $planner,
        private string $modelName = 'curategpt-search.v1',
    ) {
    }

    public function invoke(ModelRequest $request): ModelResponse
    {
        $insights = $this->buildInsights($this->extractQuery($request));
        $planned = $this->planner->plan($request->taskType, $request->input, $insights);
        $warning = $this->firstWarning($insights);

        return new ModelResponse(
            provider: 'curate_gpt',
            model: $this->modelName,
            outputSchemaVersion: $request->outputSchemaVersion,
            output: [
                'content' => (string) ($planned['content'] ?? ''),
                'suggestions' => $this->asList($planned['suggestions'] ?? []),
                'structured_output' => $this->asArray($planned['structured_output'] ?? []),
            ],
            available: null === $warning,
            message: null === $warning
                ? 'CurateGPT provider responded through the AI gateway.'
                : 'Configured provider could not be reached: ' . $warning,
            provenance: $this->asList($planned['provenance'] ?? []),
            warnings: null === $warning ? [] : [$warning],
        );
    }

    public function status(AiTraceContext $traceContext, array $capabilities = []): GatewayStatus
    {
        try {
            $this->lookup(self::DEFAULT_LOOKUP_QUERY);
        } catch (\RuntimeException $exception) {
            return new GatewayStatus(
                enabled: true,
                available: false,
                configured: true,
                realProvider: true,
                provider: 'curate_gpt',
                model: $this->modelName,
                message: 'Configured provider could not be reached: ' . $exception->getMessage(),
                capabilities: [] !== $capabilities ? $capabilities : ['provider_status'],
            );
        }

        return new GatewayStatus(
            enabled: true,
            available: true,
            configured: true,
            realProvider: true,
            provider: 'curate_gpt',
            model: $this->modelName,
            message: 'CurateGPT provider is reachable through the AI gateway.',
            capabilities: [] !== $capabilities ? $capabilities : AssistantTask::interactiveCapabilities(),
        );
    }

    private function extractQuery(ModelRequest $request): string
    {
        if (AssistantTask::CHAT === $request->taskType) {
            $messages = $request->input['messages'] ?? null;
            if (\is_array($messages)) {
                foreach (array_reverse($messages) as $message) {
                    if (\is_array($message) && 'user' === ($message['role'] ?? null)) {
                        $content = trim((string) ($message['content'] ?? ''));
                        if ('' !== $content) {
                            return $content;
                        }
                    }
                }
            }

            return self::DEFAULT_LOOKUP_QUERY;
        }

        $prompt = trim((string) ($request->input['prompt'] ?? ''));
        if ('' !== $prompt) {
            return $prompt;
        }

        $context = $request->input['context'] ?? null;
        if (!\is_array($context)) {
            return self::DEFAULT_LOOKUP_QUERY;
        }

        foreach (['species', 'label', 'title', 'resourceLabel'] as $key) {
            if (isset($context[$key]) && \is_scalar($context[$key]) && '' !== trim((string) $context[$key])) {
                return (string) $context[$key];
            }
        }

        $fields = $context['fields'] ?? null;
        if (\is_array($fields)) {
            foreach (['species', 'name', 'label'] as $key) {
                if (isset($fields[$key]) && \is_scalar($fields[$key]) && '' !== trim((string) $fields[$key])) {
                    return (string) $fields[$key];
                }
            }
        }

        return self::DEFAULT_LOOKUP_QUERY;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildInsights(string $query): array
    {
        $normalizedQuery = trim($query);

        try {
            $candidates = $this->lookup('' !== $normalizedQuery ? $normalizedQuery : self::DEFAULT_LOOKUP_QUERY);

            return [[
                'label' => 'CurateGPT ontology lookup',
                'value' => '' !== $normalizedQuery ? $normalizedQuery : self::DEFAULT_LOOKUP_QUERY,
                'detail' => [] !== $candidates
                    ? implode(', ', array_map(static fn (array $candidate): string => (string) $candidate['label'], $candidates))
                    : 'No ranked ontology candidates returned.',
                'candidates' => $candidates,
            ]];
        } catch (\RuntimeException $exception) {
            return [[
                'label' => 'CurateGPT ontology lookup',
                'value' => '' !== $normalizedQuery ? $normalizedQuery : self::DEFAULT_LOOKUP_QUERY,
                'detail' => 'Provider lookup failed.',
                'warning' => $exception->getMessage(),
            ]];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lookup(string $query): array
    {
        $results = $this->client->search(new CurateGptSearchRequestDto(
            query: $query,
            limit: 3,
            collection: 'ncbitaxon',
        ));

        return array_values(array_map(static fn (CurateGptCandidateDto $candidate): array => [
            'id' => $candidate->id,
            'label' => $candidate->label,
            'score' => $candidate->score,
        ], $results));
    }

    private function firstWarning(array $insights): ?string
    {
        $warning = $insights[0]['warning'] ?? null;

        return \is_string($warning) && '' !== $warning ? $warning : null;
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
