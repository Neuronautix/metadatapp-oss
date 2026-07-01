<?php

declare(strict_types=1);

namespace App\Guideline\Evidence;

/**
 * One piece of deterministic, prompt-safe evidence backing a guideline field.
 *
 * Produced ONLY from real, locally-persisted data (Doctrine rows, elabFTW blob,
 * connected-resource links, prior fills, FAIR signals) — never invented by a
 * model. Every text value is length-bounded and stripped of control/role-marker
 * characters by the retriever before it is constructed, so it is safe to embed
 * in an LLM prompt.
 *
 * @phpstan-type EvidenceArray array{source: string, label: string, value: string, provenance: string, field_id?: ?string, confidence?: ?float, url?: ?string}
 */
final readonly class EvidenceItem
{
    public const string SOURCE_COMPUTED = 'computed';
    public const string SOURCE_ELABFTW = 'elabftw';
    public const string SOURCE_CONNECTED_LINK = 'connected_link';
    public const string SOURCE_HISTORY = 'history';
    public const string SOURCE_FAIR = 'fair';
    public const string SOURCE_ENTITY = 'entity';
    public const string SOURCE_REFERENCE = 'reference';

    /**
     * @param ?string $fieldId    the guideline field id this item is mapped to, or null for general facts
     * @param string  $source     one of computed|elabftw|connected_link|history|fair|entity|reference
     * @param string  $label      short human label for the fact
     * @param string  $value      the evidence value (already sanitized + bounded)
     * @param string  $provenance where it came from, e.g. "computed from 24 subjects" / "Investigation: Foo"
     * @param ?float  $confidence optional 0..1 confidence for field mapping
     * @param ?string $url        optional official/source URL backing this item (e.g. a published standard)
     */
    public function __construct(
        public ?string $fieldId,
        public string $source,
        public string $label,
        public string $value,
        public string $provenance,
        public ?float $confidence = null,
        public ?string $url = null,
    ) {
    }

    /**
     * Full shape (with field_id + confidence + url) for evidence lists.
     *
     * @return array{source: string, label: string, value: string, provenance: string, field_id: ?string, confidence: ?float, url: ?string}
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'label' => $this->label,
            'value' => $this->value,
            'provenance' => $this->provenance,
            'field_id' => $this->fieldId,
            'confidence' => $this->confidence,
            'url' => $this->url,
        ];
    }

    /**
     * Compact shape handed to the model (no field_id/confidence noise).
     *
     * @return array{source: string, label: string, value: string, provenance: string}
     */
    public function toPromptArray(): array
    {
        return [
            'source' => $this->source,
            'label' => $this->label,
            'value' => $this->value,
            'provenance' => $this->provenance,
        ];
    }

    /**
     * Citation shape exposed in the draft DTO (`used_evidence`).
     *
     * @return array{source: string, label: string, provenance: string}
     */
    public function toCitationArray(): array
    {
        return [
            'source' => $this->source,
            'label' => $this->label,
            'provenance' => $this->provenance,
        ];
    }

    /**
     * Deterministic "suggestion" candidate shape for one-click use (no AI).
     *
     * @return array{value: string, source: string, provenance: string, confidence: ?float}
     */
    public function toSuggestionArray(): array
    {
        return [
            'value' => $this->value,
            'source' => $this->source,
            'provenance' => $this->provenance,
            'confidence' => $this->confidence,
        ];
    }
}
