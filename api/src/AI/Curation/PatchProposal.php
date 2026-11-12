<?php

declare(strict_types=1);

namespace App\AI\Curation;

final readonly class PatchProposal
{
    /**
     * @param list<array<string, mixed>> $operations
     * @param list<string>               $provenance
     */
    public function __construct(
        public array $operations,
        public string $rationale,
        public array $provenance,
        public string $schemaVersion = 'ai.patch-proposal.v1',
    ) {
        if ([] === $this->operations) {
            throw new \InvalidArgumentException('Patch proposals must contain at least one operation.');
        }

        if ([] === $this->provenance) {
            throw new \InvalidArgumentException('Patch proposals must include provenance.');
        }
    }
}
