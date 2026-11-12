<?php

declare(strict_types=1);

namespace App\AI\Curation;

final readonly class CurationRequest
{
    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $contextSources
     */
    public function __construct(
        public string $requestId,
        public string $taskType,
        public string $promptVersion,
        public array $payload,
        public array $contextSources = [],
    ) {
    }
}
