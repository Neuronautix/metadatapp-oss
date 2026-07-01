<?php

declare(strict_types=1);

namespace App\Guideline\Evidence;

/**
 * The deterministic, code-computed facts derived from a resource's real Doctrine
 * rows (subjects, cages, home-cage monitoring). Shared by both the evidence
 * retriever (which turns them into {@see EvidenceItem}s) and the metadata
 * resolver (which maps a subset 1:1 onto guideline field ids as base values),
 * so the computation lives in exactly one place.
 *
 * Every property is derived ONLY from real rows — never invented.
 *
 * @phpstan-type FactList list<array{field_id: ?string, label: string, value: string, provenance: string}>
 */
final readonly class ComputedFacts
{
    /**
     * @param int                $totalSubjects      total subject count (n)
     * @param array<string, int> $sexCounts          per-sex subject counts
     * @param list<string>       $species            distinct species values
     * @param list<string>       $strains            distinct strain names
     * @param list<string>       $genotypes          distinct non-empty genotypes
     * @param ?string            $ageRange           "min–max days" or null
     * @param bool               $hasWeight          any subject carries a weight
     * @param string             $weightUnit         unit assumed for weight
     * @param ?float             $housingDensity     subjects per occupied cage
     * @param list<string>       $cageTypes          distinct cage types
     * @param bool               $hasEnrichment      any occupied cage has enrichment
     * @param ?int               $lightsOnUtcHour    DVC light-on hour
     * @param ?int               $lightDurationHours DVC light-phase duration
     * @param bool               $hasHcmMeasures     any HCM/activity measures exist
     * @param ?string            $studyTimeSpan      "start → end" or null
     */
    public function __construct(
        public int $totalSubjects = 0,
        public array $sexCounts = [],
        public array $species = [],
        public array $strains = [],
        public array $genotypes = [],
        public ?string $ageRange = null,
        public bool $hasWeight = false,
        public string $weightUnit = 'g',
        public ?float $housingDensity = null,
        public array $cageTypes = [],
        public bool $hasEnrichment = false,
        public ?int $lightsOnUtcHour = null,
        public ?int $lightDurationHours = null,
        public bool $hasHcmMeasures = false,
        public ?string $studyTimeSpan = null,
    ) {
    }
}
