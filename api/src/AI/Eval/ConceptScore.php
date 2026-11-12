<?php

declare(strict_types=1);

namespace App\AI\Eval;

/**
 * Per-expected-cluster breakdown: which produced cluster best covered this gold
 * concept, how many of its members overlapped, and whether the produced cluster
 * carried the expected canonical IRI.
 */
final readonly class ConceptScore
{
    public function __construct(
        public string $expectedIri,
        public ?string $producedIri,
        public int $expectedMembers,
        public int $overlap,
        public bool $termMatched,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'expectedIri' => $this->expectedIri,
            'producedIri' => $this->producedIri,
            'expectedMembers' => $this->expectedMembers,
            'overlap' => $this->overlap,
            'termMatched' => $this->termMatched,
        ];
    }
}
