<?php

declare(strict_types=1);

namespace App\Crosswalk\Cde;

/**
 * A provider-agnostic CDE Form result normalized to the
 * {@see \App\Entity\ExternalForm} shape, preserving raw payload + provenance.
 */
final readonly class FormCandidate
{
    /**
     * @param array<array-key, mixed> $provenance
     * @param array<array-key, mixed> $rawPayload
     */
    public function __construct(
        public string $source,
        public string $title,
        public ?string $externalId = null,
        public ?string $externalUrl = null,
        public ?string $version = null,
        public ?string $description = null,
        public array $provenance = [],
        public array $rawPayload = [],
    ) {
    }
}
