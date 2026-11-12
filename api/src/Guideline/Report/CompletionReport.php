<?php

declare(strict_types=1);

namespace App\Guideline\Report;

use App\Guideline\ConformanceEntry;

/**
 * The completion roll-up (§8.2): enriched per-field totals for the fill workspace.
 *
 * `totals` splits satisfied into direct vs via-crosswalk; `by_severity` and
 * `by_section` aggregate the entries. CRITICAL (§8.2/§9): a field appears under
 * EACH of its arrive/prepare/eqipd sections simultaneously — multi-section
 * membership — so the same value reports in several section trees at once.
 */
final readonly class CompletionReport
{
    /**
     * @param list<ConformanceEntry> $entries
     * @param array<string, mixed>   $metadata the shared metadata dict (for current values)
     */
    public function __construct(
        public string $templateId,
        public string $standard,
        public array $entries,
        public array $metadata = [],
    ) {
    }

    /**
     * @return array{satisfied_direct: int, satisfied_via_crosswalk: int, partial: int, missing: int}
     */
    public function totals(): array
    {
        $totals = ['satisfied_direct' => 0, 'satisfied_via_crosswalk' => 0, 'partial' => 0, 'missing' => 0];
        foreach ($this->entries as $entry) {
            match ($entry->status) {
                ConformanceEntry::SATISFIED => $this->isViaCrosswalk($entry)
                    ? $totals['satisfied_via_crosswalk']++
                    : $totals['satisfied_direct']++,
                ConformanceEntry::PARTIAL => $totals['partial']++,
                default => $totals['missing']++,
            };
        }

        return $totals;
    }

    /**
     * @return array<string, array{satisfied: int, partial: int, missing: int}>
     */
    public function bySeverity(): array
    {
        $out = [];
        foreach ($this->entries as $entry) {
            $out[$entry->severity] ??= ['satisfied' => 0, 'partial' => 0, 'missing' => 0];
            $bucket = ConformanceEntry::SATISFIED === $entry->status
                ? 'satisfied'
                : (ConformanceEntry::PARTIAL === $entry->status ? 'partial' : 'missing');
            ++$out[$entry->severity][$bucket];
        }

        return $out;
    }

    /**
     * Multi-section membership: a field is counted under each non-null
     * arrive/prepare/eqipd section it declares (§8.2).
     *
     * @return array<string, array{satisfied: int, partial: int, missing: int, fields: list<string>}>
     */
    public function bySection(): array
    {
        $out = [];
        foreach ($this->entries as $entry) {
            foreach ($this->sectionsOf($entry) as $section) {
                $out[$section] ??= ['satisfied' => 0, 'partial' => 0, 'missing' => 0, 'fields' => []];
                $bucket = ConformanceEntry::SATISFIED === $entry->status
                    ? 'satisfied'
                    : (ConformanceEntry::PARTIAL === $entry->status ? 'partial' : 'missing');
                ++$out[$section][$bucket];
                $out[$section]['fields'][] = $entry->fieldId;
            }
        }

        return $out;
    }

    /**
     * @return array{
     *     template_id: string,
     *     standard: string,
     *     totals: array{satisfied_direct: int, satisfied_via_crosswalk: int, partial: int, missing: int},
     *     by_severity: array<string, array{satisfied: int, partial: int, missing: int}>,
     *     by_section: array<string, array{satisfied: int, partial: int, missing: int, fields: list<string>}>,
     *     fields: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        $fields = [];
        foreach ($this->entries as $entry) {
            $value = $this->metadata[$entry->fieldId] ?? null;
            $fields[] = [
                ...$entry->toArray(),
                'value' => $value,
                'has_value' => null !== $value && '' !== $value && [] !== $value,
                'satisfied_via_crosswalk' => $this->isViaCrosswalk($entry),
                'satisfying_sibling' => $this->satisfyingSibling($entry),
            ];
        }

        return [
            'template_id' => $this->templateId,
            'standard' => $this->standard,
            'totals' => $this->totals(),
            'by_severity' => $this->bySeverity(),
            'by_section' => $this->bySection(),
            'fields' => $fields,
        ];
    }

    private function isViaCrosswalk(ConformanceEntry $entry): bool
    {
        return null !== $entry->satisfiedBy && ($entry->satisfiedBy['via_crosswalk'] ?? false) === true;
    }

    private function satisfyingSibling(ConformanceEntry $entry): ?string
    {
        if ($this->isViaCrosswalk($entry) && isset($entry->satisfiedBy['metadata'])) {
            return (string) $entry->satisfiedBy['metadata'];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function sectionsOf(ConformanceEntry $entry): array
    {
        $sections = array_filter([
            $entry->arriveSection,
            $entry->prepareSection,
            $entry->eqipdSection,
        ], static fn (?string $s): bool => null !== $s && '' !== $s);

        if ([] === $sections) {
            return ['(unsectioned)'];
        }

        return array_values($sections);
    }
}
