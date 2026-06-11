<?php

declare(strict_types=1);

namespace App\CurateGpt\Client\Dto;

/**
 * Request payload sent to CurateGPT for a single term search.
 */
class CurateGptSearchRequestDto
{
    public function __construct(
        /** The term text to search for. */
        public readonly string $query,
        /** Maximum number of candidates to retrieve. */
        public readonly int $limit = 5,
        /** Optional collection name to restrict the search scope. */
        public readonly ?string $collection = null,
    ) {
    }
}
