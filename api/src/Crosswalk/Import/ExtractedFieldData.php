<?php

declare(strict_types=1);

namespace App\Crosswalk\Import;

/**
 * A normalized, source-faithful representation of a single field extracted from
 * an ELN RO-Crate. Every node walked during import becomes one of these; nothing
 * is discarded so unmapped fields are still preserved.
 */
final readonly class ExtractedFieldData
{
    /**
     * @param list<array<string, mixed>> $ontologyTerms resolved DefinedTerm IRIs/labels
     * @param array<string, mixed>       $rawNode       the verbatim JSON-LD node
     */
    public function __construct(
        public string $label,
        public ?string $propertyId = null,
        public ?string $datatype = null,
        public ?string $exampleValue = null,
        public ?string $unit = null,
        public ?string $jsonldId = null,
        public ?string $jsonldPath = null,
        public array $ontologyTerms = [],
        public array $rawNode = [],
        public int $orderIndex = 0,
    ) {
    }
}
