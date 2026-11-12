<?php

declare(strict_types=1);

namespace App\AI\Eval;

/**
 * The scores produced by {@see StandardizationEvaluator}: pairwise clustering
 * precision/recall, canonical-term accuracy, cluster counts, and a per-concept
 * breakdown for drilling into which equivalence classes the model got right.
 */
final readonly class StandardizationEvalResult
{
    /**
     * @param list<ConceptScore> $perConcept one entry per expected cluster
     */
    public function __construct(
        public float $precision,
        public float $recall,
        public float $termAccuracy,
        public int $expectedClusterCount,
        public int $producedClusterCount,
        public array $perConcept = [],
    ) {
    }

    /**
     * Harmonic mean of pairwise precision and recall (0.0 when both are 0).
     */
    public function f1(): float
    {
        $denominator = $this->precision + $this->recall;

        return $denominator > 0.0 ? (2 * $this->precision * $this->recall) / $denominator : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'precision' => $this->precision,
            'recall' => $this->recall,
            'f1' => $this->f1(),
            'termAccuracy' => $this->termAccuracy,
            'expectedClusterCount' => $this->expectedClusterCount,
            'producedClusterCount' => $this->producedClusterCount,
            'perConcept' => array_map(static fn (ConceptScore $c): array => $c->toArray(), $this->perConcept),
        ];
    }
}
