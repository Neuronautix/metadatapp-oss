<?php

declare(strict_types=1);

namespace App\AI\Gateway;

final readonly class ModelRequest
{
    /**
     * @param array<string, mixed> $input
     * @param list<string>         $contextSources
     */
    public function __construct(
        public string $requestId,
        public string $taskType,
        public string $promptVersion,
        public string $outputSchemaVersion,
        public array $input,
        public array $contextSources = [],
    ) {
    }
}
