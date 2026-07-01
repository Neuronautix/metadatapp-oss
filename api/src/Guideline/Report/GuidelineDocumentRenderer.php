<?php

declare(strict_types=1);

namespace App\Guideline\Report;

use App\Guideline\ConformanceEntry;
use App\Guideline\Schema\GuidelineTemplate;
use App\Guideline\Schema\RequiredMetadata;

/**
 * Produces the human-readable per-standard documents (§8.3): a study-plan /
 * declaration document and a checklist with ✅/⚠️/❌ and an action-items list.
 *
 * Values are resolved via crosswalk and annotated "(from ARRIVE: field)" when
 * the value was borrowed from a sibling field in the shared metadata dict.
 */
final readonly class GuidelineDocumentRenderer
{
    /**
     * @param array<string, mixed>                                                                              $metadata      the shared metadata dict
     * @param array{status: string, reviewed_by: string|null, reviewed_at: string|null, note: string|null}|null $reviewSummary the current report-level QC sign-off, or null when unknown
     *
     * @return array{plan: string, checklist: string}
     */
    public function render(GuidelineTemplate $template, ConformanceReport $report, array $metadata, ?array $reviewSummary = null): array
    {
        return [
            'plan' => $this->renderPlan($template, $report, $metadata, $reviewSummary),
            'checklist' => $this->renderChecklist($template, $report),
        ];
    }

    /**
     * @param array<string, mixed>                                                                              $metadata
     * @param array{status: string, reviewed_by: string|null, reviewed_at: string|null, note: string|null}|null $reviewSummary
     */
    public function renderPlan(GuidelineTemplate $template, ConformanceReport $report, array $metadata, ?array $reviewSummary = null): string
    {
        $entryByField = $this->indexEntries($report);
        $specByField = $this->indexSpecs($template);

        $lines = [];
        $lines[] = \sprintf('# %s — Study plan / declaration', $template->name);
        $lines[] = '';
        $lines[] = \sprintf('_Version %s. Generated %s._', $template->version, $this->now());
        $lines[] = '';
        $lines[] = $this->qcStampLine($reviewSummary);
        $lines[] = '';

        foreach ($this->groupBySection($template) as $section => $fieldIds) {
            $lines[] = \sprintf('## %s', $section);
            $lines[] = '';
            foreach ($fieldIds as $fieldId) {
                $spec = $specByField[$fieldId] ?? null;
                $entry = $entryByField[$fieldId] ?? null;
                $prompt = '';
                if (null !== $spec) {
                    $prompt = $spec->prompt ?? $spec->guidance ?? '';
                }
                $lines[] = \sprintf('### %s', $fieldId);
                if ('' !== $prompt) {
                    $lines[] = \sprintf('_%s_', $prompt);
                }
                $lines[] = $this->resolvedValueLine($fieldId, $entry, $metadata);
                $lines[] = '';
            }
        }

        $lines[] = '## Sign-off';
        $lines[] = '';
        $lines[] = '| Role | Name | Date | Signature |';
        $lines[] = '| --- | --- | --- | --- |';
        $lines[] = '| Responsible scientist |  |  |  |';
        $lines[] = '| Named veterinary surgeon |  |  |  |';
        $lines[] = '';

        return implode("\n", $lines);
    }

    public function renderChecklist(GuidelineTemplate $template, ConformanceReport $report): string
    {
        $lines = [];
        $lines[] = \sprintf('# %s — Checklist', $template->name);
        $lines[] = '';
        $lines[] = \sprintf(
            '_%d of %d items satisfied (%.1f%%). Generated %s._',
            $report->satisfiedCount(),
            $report->totalCount(),
            $report->satisfiedPercentage(),
            $this->now(),
        );
        $lines[] = '';
        $lines[] = '| Status | Field | Section | Note |';
        $lines[] = '| --- | --- | --- | --- |';

        $actionItems = [];
        foreach ($report->entries as $entry) {
            $icon = $this->statusIcon($entry->status);
            $note = $this->checklistNote($entry);
            $lines[] = \sprintf(
                '| %s | %s | %s | %s |',
                $icon,
                $entry->fieldId,
                $this->escapeCell($entry->section ?? ''),
                $this->escapeCell($note),
            );

            if (ConformanceEntry::SATISFIED !== $entry->status) {
                $actionItems[] = \sprintf('- [ ] **%s** (%s) — %s', $entry->fieldId, $entry->severity, $entry->status);
            }
        }

        $lines[] = '';
        $lines[] = '## Action items';
        $lines[] = '';
        if ([] === $actionItems) {
            $lines[] = '_All items satisfied._';
        } else {
            foreach ($actionItems as $item) {
                $lines[] = $item;
            }
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolvedValueLine(string $fieldId, ?ConformanceEntry $entry, array $metadata): string
    {
        // Direct value first.
        if (isset($metadata[$fieldId]) && $this->isNonEmpty($metadata[$fieldId])) {
            return $this->stringify($metadata[$fieldId]);
        }

        // Borrowed via crosswalk — annotate the source.
        if (null !== $entry
            && ConformanceEntry::SATISFIED === $entry->status
            && null !== $entry->satisfiedBy
            && ($entry->satisfiedBy['via_crosswalk'] ?? false) === true
            && isset($entry->satisfiedBy['metadata']) // @phpstan-ignore isset.offset
        ) {
            $sibling = (string) $entry->satisfiedBy['metadata'];
            $value = $metadata[$sibling] ?? null;
            $origin = $this->originLabel($sibling);

            return \sprintf(
                '%s _(from %s: %s)_',
                $this->isNonEmpty($value) ? $this->stringify($value) : '—',
                $origin,
                $sibling,
            );
        }

        return '_Missing_';
    }

    private function originLabel(string $fieldId): string
    {
        if (str_starts_with($fieldId, 'prepare_')) {
            return 'PREPARE';
        }
        if (str_starts_with($fieldId, 'eqipd_')) {
            return 'EQIPD';
        }

        return 'ARRIVE';
    }

    private function checklistNote(ConformanceEntry $entry): string
    {
        if (null !== $entry->satisfiedBy && ($entry->satisfiedBy['via_crosswalk'] ?? false) === true && isset($entry->satisfiedBy['metadata'])) { /** @phpstan-ignore isset.offset */
            $sibling = (string) $entry->satisfiedBy['metadata'];

            return \sprintf('via %s: %s', $this->originLabel($sibling), $sibling);
        }
        if (ConformanceEntry::PARTIAL === $entry->status) {
            return 'matched column, unit missing';
        }
        if (ConformanceEntry::MISSING === $entry->status) {
            return 'not yet provided';
        }

        return '';
    }

    /**
     * @param array{status: string, reviewed_by: string|null, reviewed_at: string|null, note: string|null}|null $reviewSummary
     */
    private function qcStampLine(?array $reviewSummary): string
    {
        if (null === $reviewSummary || 'not_reviewed' === $reviewSummary['status']) {
            return '> ⚠️ **QC status: Not yet reviewed.**';
        }

        if ('approved' === $reviewSummary['status']) {
            return \sprintf(
                '> ✅ **QC status: Approved** by %s on %s.',
                $reviewSummary['reviewed_by'] ?? 'unknown',
                $reviewSummary['reviewed_at'] ?? 'unknown date',
            );
        }

        return \sprintf(
            '> ⚠️ **QC status: Changes requested** by %s on %s.',
            $reviewSummary['reviewed_by'] ?? 'unknown',
            $reviewSummary['reviewed_at'] ?? 'unknown date',
        );
    }

    private function statusIcon(string $status): string
    {
        return match ($status) {
            ConformanceEntry::SATISFIED => '✅',
            ConformanceEntry::PARTIAL => '⚠️',
            default => '❌',
        };
    }

    /**
     * @return array<string, list<string>> section => field ids (multi-section membership)
     */
    private function groupBySection(GuidelineTemplate $template): array
    {
        $out = [];
        foreach ($template->requiredMetadata as $spec) {
            $sections = array_filter([$spec->arriveSection, $spec->prepareSection, $spec->eqipdSection]);
            if ([] === $sections) {
                $sections = ['(unsectioned)'];
            }
            foreach ($sections as $section) {
                $out[$section][] = $spec->id;
            }
        }
        foreach ($template->requiredColumns as $col) {
            $section = $col->arriveSection ?? '(columns)';
            $out[$section][] = $col->id;
        }

        return $out;
    }

    /**
     * @return array<string, ConformanceEntry>
     */
    private function indexEntries(ConformanceReport $report): array
    {
        $out = [];
        foreach ($report->entries as $entry) {
            $out[$entry->fieldId] = $entry;
        }

        return $out;
    }

    /**
     * @return array<string, RequiredMetadata>
     */
    private function indexSpecs(GuidelineTemplate $template): array
    {
        $out = [];
        foreach ($template->requiredMetadata as $spec) {
            $out[$spec->id] = $spec;
        }

        return $out;
    }

    private function escapeCell(string $value): string
    {
        return str_replace(['|', "\n"], ['\|', ' '], $value);
    }

    private function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return trim($value);
        }
        if (\is_array($value)) {
            return implode(', ', array_map(static fn (mixed $v): string => \is_scalar($v) ? (string) $v : json_encode($v, \JSON_THROW_ON_ERROR), $value));
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }

        return '';
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

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s \U\T\C');
    }
}
