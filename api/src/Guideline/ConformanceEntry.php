<?php

declare(strict_types=1);

namespace App\Guideline;

/**
 * One per-field conformance entry — the stable contract of §6.1.
 *
 * `status` is exactly one of `satisfied|partial|missing`. `satisfiedBy` is one
 * of `{column: name}`, `{metadata: id}`, `{metadata: id, via_crosswalk: true}`,
 * or null. Downstream report and score code reads these verbatim (§9).
 */
final readonly class ConformanceEntry
{
    public const string SATISFIED = 'satisfied';
    public const string PARTIAL = 'partial';
    public const string MISSING = 'missing';

    /**
     * @param array{column: string}|array{metadata: string}|array{metadata: string, via_crosswalk: true}|null $satisfiedBy
     */
    public function __construct(
        public string $standard,
        public ?string $section,
        public ?string $arriveSection,
        public ?string $prepareSection,
        public ?string $eqipdSection,
        public string $fieldId,
        public string $status,
        public ?array $satisfiedBy,
        public string $severity,
        public bool $isColumnField,
    ) {
    }

    /**
     * @return array{
     *     standard: string,
     *     section: string|null,
     *     arrive_section: string|null,
     *     prepare_section: string|null,
     *     eqipd_section: string|null,
     *     field_id: string,
     *     status: string,
     *     satisfied_by: array<string, mixed>|null,
     *     severity: string,
     *     is_column_field: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'standard' => $this->standard,
            'section' => $this->section,
            'arrive_section' => $this->arriveSection,
            'prepare_section' => $this->prepareSection,
            'eqipd_section' => $this->eqipdSection,
            'field_id' => $this->fieldId,
            'status' => $this->status,
            'satisfied_by' => $this->satisfiedBy,
            'severity' => $this->severity,
            'is_column_field' => $this->isColumnField,
        ];
    }
}
