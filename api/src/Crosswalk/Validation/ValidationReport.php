<?php

declare(strict_types=1);

namespace App\Crosswalk\Validation;

/**
 * The result of validating a crosswalk's semantic completeness. Counts, a list of
 * issues, and a 0..100 completeness score. JSON-serializable via {@see toArray()}.
 */
final readonly class ValidationReport
{
    /**
     * @param list<array{code: string, severity: string, message: string, fieldRef?: string}> $issues
     */
    public function __construct(
        public int $fieldsExtracted,
        public int $fieldsMappedToCdes,
        public int $unmappedFields,
        public int $numericFields,
        public int $numericFieldsWithUnits,
        public int $categoricalFields,
        public int $categoricalFieldsWithControlledValues,
        public int $fieldsRequiringReview,
        public array $issues,
        public int $semanticCompletenessScore,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'counts' => [
                'fieldsExtracted' => $this->fieldsExtracted,
                'fieldsMappedToCdes' => $this->fieldsMappedToCdes,
                'unmappedFields' => $this->unmappedFields,
                'numericFields' => $this->numericFields,
                'numericFieldsWithUnits' => $this->numericFieldsWithUnits,
                'categoricalFields' => $this->categoricalFields,
                'categoricalFieldsWithControlledValues' => $this->categoricalFieldsWithControlledValues,
                'fieldsRequiringReview' => $this->fieldsRequiringReview,
            ],
            'issues' => $this->issues,
            'semanticCompletenessScore' => $this->semanticCompletenessScore,
        ];
    }
}
