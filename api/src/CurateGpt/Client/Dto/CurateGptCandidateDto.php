<?php

declare(strict_types=1);

namespace App\CurateGpt\Client\Dto;

/**
 * A single candidate alignment returned by CurateGPT for a submitted term.
 */
class CurateGptCandidateDto
{
    public function __construct(
        public readonly string $id = '',
        public readonly string $label = '',
        public readonly float $score = 0.0,
        /** Additional context fields returned by CurateGPT (definition, synonyms, …). */
        public readonly array $metadata = [],
    ) {
    }
}
