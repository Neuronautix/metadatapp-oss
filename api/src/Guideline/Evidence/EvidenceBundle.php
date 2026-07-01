<?php

declare(strict_types=1);

namespace App\Guideline\Evidence;

use App\Guideline\Schema\RequiredMetadata;

/**
 * An ordered collection of {@see EvidenceItem}s gathered for a Study or
 * Investigation, tenant-scoped and deterministic.
 *
 * {@see self::forField()} resolves the items relevant to a single guideline
 * field: items mapped directly to the field id, items mapped to one of the
 * field's crosswalk siblings, and general computed/FAIR facts that provide
 * useful context for any field.
 */
final readonly class EvidenceBundle
{
    /**
     * @param list<EvidenceItem> $items
     */
    public function __construct(
        private array $items = [],
        private ?ReferenceEvidenceResolver $references = null,
    ) {
    }

    /**
     * @return list<EvidenceItem>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * Items relevant to a single field: direct field-id matches, crosswalk-sibling
     * matches, the deterministic `reference` citation(s) for the field's standard,
     * then general (field-less) computed/FAIR facts. Order is preserved and the
     * most specific (field-id) matches come first.
     *
     * The `reference` items are PUBLIC standard text (citations), produced lazily
     * here so they reach the LLM grounding payload and the evidence JSON — they
     * are deliberately NOT offered by {@see self::suggestionsForField()} (a
     * definition is never a one-click fill value).
     *
     * @return list<EvidenceItem>
     */
    public function forField(RequiredMetadata $field): array
    {
        $siblings = array_fill_keys($field->crosswalk, true);

        $direct = [];
        $sibling = [];
        $general = [];

        foreach ($this->items as $item) {
            if (null !== $item->fieldId && $item->fieldId === $field->id) {
                $direct[] = $item;

                continue;
            }
            if (null !== $item->fieldId && isset($siblings[$item->fieldId])) {
                $sibling[] = $item;

                continue;
            }
            if (null === $item->fieldId) {
                // General computed/FAIR facts: useful context for any field.
                $general[] = $item;
            }
        }

        $reference = null !== $this->references ? $this->references->forField($field) : [];

        return array_merge($direct, $sibling, $reference, $general);
    }

    /**
     * The best deterministic candidate VALUES for a field (no AI): a value-bearing
     * computed/history/connected/elabFTW item mapped to the field or a crosswalk
     * sibling. General field-less facts are NOT offered as direct values.
     *
     * @return list<EvidenceItem>
     */
    public function suggestionsForField(RequiredMetadata $field): array
    {
        $siblings = array_fill_keys($field->crosswalk, true);
        $suggestions = [];

        foreach ($this->items as $item) {
            if (null === $item->fieldId) {
                continue;
            }
            if ($item->fieldId !== $field->id && !isset($siblings[$item->fieldId])) {
                continue;
            }
            if ('' === trim($item->value)) {
                continue;
            }
            $suggestions[] = $item;
        }

        return $suggestions;
    }

    /**
     * The field-less computed facts (n, species, housing density, …) for the
     * `computed` block of the evidence endpoint.
     *
     * @return list<EvidenceItem>
     */
    public function computedFacts(): array
    {
        return array_values(array_filter(
            $this->items,
            static fn (EvidenceItem $i): bool => EvidenceItem::SOURCE_COMPUTED === $i->source,
        ));
    }
}
