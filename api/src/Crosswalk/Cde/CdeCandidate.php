<?php

declare(strict_types=1);

namespace App\Crosswalk\Cde;

/**
 * A provider-agnostic CDE result normalized to the {@see \App\Entity\CommonDataElement}
 * shape. Raw payload + provenance + source URL are preserved so the persisted CDE
 * keeps its lineage.
 */
final readonly class CdeCandidate
{
    /**
     * @param list<array<string, mixed>> $permissibleValues item shape {value, code?, ontologyTerm?}
     * @param list<array<string, mixed>> $ontologyMappings  item shape {iri, label?, source?}
     * @param array<array-key, mixed>    $provenance
     * @param array<array-key, mixed>    $rawPayload
     */
    public function __construct(
        public string $source,
        public string $label,
        public ?string $externalId = null,
        public ?string $externalUrl = null,
        public ?string $version = null,
        public ?string $definition = null,
        public ?string $questionText = null,
        public ?string $datatype = null,
        public ?string $unitConstraint = null,
        public ?string $status = null,
        public ?string $localKey = null,
        public array $permissibleValues = [],
        public array $ontologyMappings = [],
        public array $provenance = [],
        public array $rawPayload = [],
    ) {
    }
}
