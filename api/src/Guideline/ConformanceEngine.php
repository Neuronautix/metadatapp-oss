<?php

declare(strict_types=1);

namespace App\Guideline;

use App\Guideline\Schema\GuidelineTemplate;
use App\Guideline\Schema\RequiredColumn;

/**
 * The validation engine (§6). Given a resolved template (already merged via §3),
 * the available CSV columns, and the shared metadata dict, produces a conformance
 * report: one {@see ConformanceEntry} per field.
 *
 * Pass 1 — columns. Pass 2 Step A — direct metadata. Pass 2 Step B — crosswalk
 * auto-satisfaction (directional, only the declaring field upgraded; resolves
 * against a satisfied sibling OR a non-empty key in the shared metadata dict;
 * first hit wins; only ever upgrades, never demotes).
 */
final class ConformanceEngine
{
    /**
     * @param list<CsvColumn>      $columns  available CSV header columns
     * @param array<string, mixed> $metadata the shared metadata dict, keyed by field id
     *
     * @return list<ConformanceEntry>
     */
    public function evaluate(GuidelineTemplate $template, array $columns, array $metadata): array
    {
        $entries = [];

        // ---- Pass 1: columns ----
        $headerByLower = [];
        foreach ($columns as $column) {
            $headerByLower[mb_strtolower($column->name)] = $column;
        }

        foreach ([...$template->requiredColumns, ...$template->optionalColumns] as $spec) {
            $entries[] = $this->evaluateColumn($template, $spec, $columns, $headerByLower);
        }

        // ---- Pass 2 Step A: metadata direct ----
        // Track which field ids are satisfied so Step B can resolve siblings.
        $fieldSatisfied = [];
        $metaEntries = [];
        foreach ($template->requiredMetadata as $spec) {
            $satisfied = $this->isNonEmpty($metadata[$spec->id] ?? null);
            $status = $satisfied ? ConformanceEntry::SATISFIED : ConformanceEntry::MISSING;
            $satisfiedBy = $satisfied ? ['metadata' => $spec->id] : null;
            $fieldSatisfied[$spec->id] = $satisfied;

            $metaEntries[] = [
                'spec' => $spec,
                'status' => $status,
                'satisfied_by' => $satisfiedBy,
            ];
        }

        // ---- Pass 2 Step B: metadata crosswalk (cross-standard) ----
        foreach ($metaEntries as $i => $row) {
            $spec = $row['spec'];
            if (ConformanceEntry::SATISFIED === $row['status'] || [] === $spec->crosswalk) {
                continue;
            }
            foreach ($spec->crosswalk as $target) {
                if (($fieldSatisfied[$target] ?? false) || $this->isNonEmpty($metadata[$target] ?? null)) {
                    $metaEntries[$i]['status'] = ConformanceEntry::SATISFIED;
                    $metaEntries[$i]['satisfied_by'] = ['metadata' => $target, 'via_crosswalk' => true];
                    break;
                }
            }
        }

        foreach ($metaEntries as $row) {
            $spec = $row['spec'];
            $section = $spec->arriveSection ?? $spec->prepareSection ?? $spec->eqipdSection;
            $entries[] = new ConformanceEntry(
                standard: $template->name,
                section: $section,
                arriveSection: $spec->arriveSection,
                prepareSection: $spec->prepareSection,
                eqipdSection: $spec->eqipdSection,
                fieldId: $spec->id,
                status: $row['status'],
                satisfiedBy: $row['satisfied_by'],
                severity: $spec->severity,
                isColumnField: false,
            );
        }

        return $entries;
    }

    /**
     * @param list<CsvColumn>          $columns
     * @param array<string, CsvColumn> $headerByLower
     */
    private function evaluateColumn(
        GuidelineTemplate $template,
        RequiredColumn $spec,
        array $columns,
        array $headerByLower,
    ): ConformanceEntry {
        $match = $this->matchColumn($spec, $columns);

        if (null === $match) {
            return $this->columnEntry($template, $spec, ConformanceEntry::MISSING, null);
        }

        if (!$spec->unitRequired) {
            return $this->columnEntry($template, $spec, ConformanceEntry::SATISFIED, ['column' => $match->name]);
        }

        // unit_required: satisfied iff the matched column carries a unit OR the
        // companion unit_column is present; else partial.
        $hasUnit = $match->hasUnit();
        $companionPresent = null !== $spec->unitColumn && isset($headerByLower[mb_strtolower($spec->unitColumn)]);

        if ($hasUnit || $companionPresent) {
            return $this->columnEntry($template, $spec, ConformanceEntry::SATISFIED, ['column' => $match->name]);
        }

        return $this->columnEntry($template, $spec, ConformanceEntry::PARTIAL, ['column' => $match->name]);
    }

    /**
     * @param list<CsvColumn> $columns
     */
    private function matchColumn(RequiredColumn $spec, array $columns): ?CsvColumn
    {
        foreach ($columns as $column) {
            $haystack = mb_strtolower($column->name);
            foreach ($spec->namePatterns as $pattern) {
                if ($this->patternMatches($pattern, $haystack)) {
                    return $column;
                }
            }
        }

        return null;
    }

    private function patternMatches(string $pattern, string $lowerHeader): bool
    {
        // Regex when the pattern contains regex metacharacters; else case-insensitive substring.
        if (1 === preg_match('/[\\\^$.|?*+()\[\]{}]/', $pattern)) {
            $delimited = '/' . str_replace('/', '\/', $pattern) . '/i';
            $result = @preg_match($delimited, $lowerHeader);
            if (false !== $result) {
                return 1 === $result;
            }
        }

        return str_contains($lowerHeader, mb_strtolower($pattern));
    }

    /**
     * @param array{column: string}|null $satisfiedBy
     */
    private function columnEntry(GuidelineTemplate $template, RequiredColumn $spec, string $status, ?array $satisfiedBy): ConformanceEntry
    {
        return new ConformanceEntry(
            standard: $template->name,
            section: $spec->arriveSection,
            arriveSection: $spec->arriveSection,
            prepareSection: null,
            eqipdSection: null,
            fieldId: $spec->id,
            status: $status,
            satisfiedBy: $satisfiedBy,
            severity: $spec->severity,
            isColumnField: true,
        );
    }

    private function isNonEmpty(mixed $value): bool
    {
        if (null === $value) {
            return false;
        }
        if (\is_string($value)) {
            return '' !== trim($value);
        }
        if (\is_array($value)) {
            return [] !== $value;
        }

        return true;
    }
}
