<?php

declare(strict_types=1);

namespace App\Guideline;

use App\Guideline\Evidence\EvidenceItem;

/**
 * A non-persisted AI draft candidate for a single guideline field (§7.3).
 *
 * Returned to the caller for review; the user must save it explicitly (the
 * fill channel never auto-persists an AI value).
 *
 * Carries the evidence items handed to the model (`usedEvidence`) so the UI can
 * cite provenance, and the deterministic `suggestions` so a one-click "use this"
 * is available even with no AI provider configured.
 */
final readonly class GuidelineFieldDraft
{
    /**
     * @param list<EvidenceItem> $usedEvidence the evidence passed to the model (for citation)
     * @param list<EvidenceItem> $suggestions  deterministic candidate values (no AI needed)
     */
    public function __construct(
        public string $fieldId,
        public string $value,
        public string $rationale,
        public bool $available,
        public string $provider,
        public string $model,
        public string $message = '',
        public array $usedEvidence = [],
        public array $suggestions = [],
    ) {
    }

    /**
     * @param list<EvidenceItem> $suggestions
     */
    public static function unavailable(string $fieldId, string $message, array $suggestions = []): self
    {
        return new self(
            fieldId: $fieldId,
            value: '',
            rationale: '',
            available: false,
            provider: 'none',
            model: 'none',
            message: $message,
            usedEvidence: [],
            suggestions: $suggestions,
        );
    }

    /**
     * @return array{
     *     field_id: string,
     *     value: string,
     *     rationale: string,
     *     available: bool,
     *     provider: string,
     *     model: string,
     *     message: string,
     *     persisted: false,
     *     used_evidence: list<array{source: string, label: string, provenance: string}>,
     *     suggestions: list<array{value: string, source: string, provenance: string, confidence: ?float}>
     * }
     */
    public function toArray(): array
    {
        return [
            'field_id' => $this->fieldId,
            'value' => $this->value,
            'rationale' => $this->rationale,
            'available' => $this->available,
            'provider' => $this->provider,
            'model' => $this->model,
            'message' => $this->message,
            'persisted' => false,
            'used_evidence' => array_map(static fn (EvidenceItem $i): array => $i->toCitationArray(), $this->usedEvidence),
            'suggestions' => array_map(static fn (EvidenceItem $i): array => $i->toSuggestionArray(), $this->suggestions),
        ];
    }
}
