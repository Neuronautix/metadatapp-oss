<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Standardization;

/**
 * The single ontology term a cluster of equivalent reference results is mapped to
 * (e.g. VT:0000188 "blood glucose level"), with the model's confidence.
 */
final readonly class CanonicalTerm
{
    public function __construct(
        public string $iri,
        public string $label,
        /** Ontology short name, e.g. "vt", "mp", "efo", "curategpt". */
        public string $ontology,
        public float $confidence,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $iri = trim((string) ($data['iri'] ?? ''));
        $label = trim((string) ($data['label'] ?? ''));
        if ('' === $iri && '' === $label) {
            return null;
        }

        return new self(
            iri: $iri,
            label: $label,
            ontology: trim((string) ($data['ontology'] ?? '')),
            confidence: self::clampConfidence($data['confidence'] ?? 0.0),
        );
    }

    private static function clampConfidence(mixed $value): float
    {
        $confidence = is_numeric($value) ? (float) $value : 0.0;

        return max(0.0, min(1.0, $confidence));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'iri' => $this->iri,
            'label' => $this->label,
            'ontology' => $this->ontology,
            'confidence' => $this->confidence,
        ];
    }
}
