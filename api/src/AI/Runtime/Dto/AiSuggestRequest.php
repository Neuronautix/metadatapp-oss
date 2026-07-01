<?php

declare(strict_types=1);

namespace App\AI\Runtime\Dto;

use App\AI\Runtime\AssistantTask;

final readonly class AiSuggestRequest
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $action,
        public string $contextType,
        public array $context,
        public ?string $prompt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $action = isset($payload['action']) && \is_string($payload['action']) ? trim($payload['action']) : '';
        if (!\in_array($action, [
            AssistantTask::SUGGEST_FORM_INPUTS,
            AssistantTask::EXPLAIN_MISSING_METADATA,
            AssistantTask::DRAFT_QUERY_FILTERS,
            AssistantTask::GENERATE_EXPORT_CONTENT,
        ], true)) {
            throw new \InvalidArgumentException('Unsupported AI suggestion action.');
        }

        $contextType = isset($payload['contextType']) && \is_string($payload['contextType']) ? trim($payload['contextType']) : '';
        if ('' === $contextType) {
            throw new \InvalidArgumentException('AI suggestion requests must include a contextType.');
        }

        $context = $payload['context'] ?? null;
        if (!\is_array($context)) {
            throw new \InvalidArgumentException('AI suggestion context must be an object.');
        }

        $prompt = isset($payload['prompt']) && \is_string($payload['prompt']) ? trim($payload['prompt']) : null;

        return new self($action, $contextType, $context, '' === $prompt ? null : $prompt);
    }
}
