<?php

declare(strict_types=1);

namespace App\ConnectedApps\Reference\Standardization;

/**
 * The outcome of standardizing a set of selected reference results: the clusters
 * of cross-source equivalents, whether a real AI provider produced them (vs. the
 * deterministic fallback), plus warnings, provenance and token usage for metering.
 */
final readonly class StandardizationResult
{
    /**
     * @param list<ReferenceCluster>     $clusters
     * @param list<string>               $warnings
     * @param list<array<string, mixed>> $provenance
     * @param array<string, mixed>       $usage
     */
    public function __construct(
        public array $clusters,
        public bool $aiAvailable,
        public array $warnings = [],
        public array $provenance = [],
        public array $usage = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'aiAvailable' => $this->aiAvailable,
            'clusters' => array_map(static fn (ReferenceCluster $c): array => $c->toArray(), $this->clusters),
            'warnings' => $this->warnings,
            'provenance' => $this->provenance,
            'usage' => $this->usage,
        ];
    }
}
