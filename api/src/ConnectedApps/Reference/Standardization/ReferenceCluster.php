<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Standardization;

/**
 * A group of reference results judged to describe the same underlying concept
 * across sources, mapped to a single {@see CanonicalTerm}, with the evidence the
 * model (or the deterministic fallback) used and one proposed standardized record.
 */
final readonly class ReferenceCluster
{
    /**
     * @param list<string>          $memberRefIds     federated reference ids in this cluster
     * @param list<string>          $evidence         human-readable justifications
     * @param array<string, mixed>  $proposedRecord   the importable, standardized record
     * @param list<HarmonizedField> $harmonizedFields per-field reconciliation across the sources
     */
    public function __construct(
        public string $clusterId,
        public ?CanonicalTerm $canonicalTerm,
        public array $memberRefIds,
        public array $evidence,
        public array $proposedRecord,
        public float $confidence,
        public array $harmonizedFields = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'clusterId' => $this->clusterId,
            'canonicalTerm' => $this->canonicalTerm?->toArray(),
            'memberRefIds' => $this->memberRefIds,
            'evidence' => $this->evidence,
            'proposedRecord' => $this->proposedRecord,
            'confidence' => $this->confidence,
            'harmonizedFields' => array_map(static fn (HarmonizedField $f): array => $f->toArray(), $this->harmonizedFields),
        ];
    }
}
