<?php

declare(strict_types=1);

namespace App\Curation;

use App\Enum\CurationTaskType;

final readonly class ValidatedCurationSuggestion
{
    public function __construct(
        public CurationTaskType $taskType,
        public string $fieldName,
        public mixed $proposedValue,
        public string $reason,
        public ?string $ontologyId = null,
        public ?float $confidence = null,
        public ?string $warningCode = null,
        public ?array $providerMetadata = null,
    ) {
    }
}
