<?php

declare(strict_types=1);

namespace App\AI\Gateway;

final readonly class ModelResponse
{
    /**
     * @param array<string, mixed>       $output
     * @param list<array<string, mixed>> $provenance
     * @param list<string>               $warnings
     */
    public function __construct(
        public string $provider,
        public string $model,
        public string $outputSchemaVersion,
        public array $output,
        public bool $available = true,
        public string $message = '',
        public array $provenance = [],
        public array $warnings = [],
    ) {
    }
}
