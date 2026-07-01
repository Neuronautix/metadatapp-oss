<?php

declare(strict_types=1);

namespace App\Guideline\Report;

use App\Guideline\ConformanceEntry;
use App\Guideline\Schema\GuidelineTemplate;

/**
 * The conformance report (§6.1 + §6.3): the live per-field status list plus the
 * FAIR R-dimension score that scales with the percentage of satisfied fields.
 *
 * Missing fields degrade (lower the score) but never block (§9).
 */
final readonly class ConformanceReport
{
    /**
     * @param list<ConformanceEntry> $entries
     */
    public function __construct(
        public GuidelineTemplate $template,
        public array $entries,
    ) {
    }

    /**
     * Number of `satisfied` entries (direct or via crosswalk).
     */
    public function satisfiedCount(): int
    {
        return \count(array_filter(
            $this->entries,
            static fn (ConformanceEntry $e): bool => ConformanceEntry::SATISFIED === $e->status,
        ));
    }

    public function totalCount(): int
    {
        return \count($this->entries);
    }

    public function satisfiedPercentage(): float
    {
        $total = $this->totalCount();
        if (0 === $total) {
            return 100.0;
        }

        return round($this->satisfiedCount() / $total * 100, 1);
    }

    /**
     * FAIR R-dimension contribution (§6.3): banded by satisfied %.
     * 0 / 1 / 3 / 5 points at increasing satisfaction bands.
     */
    public function fairScore(): int
    {
        $maxPoints = $this->template->fairBonus['max_points'] ?? 5;
        $pct = $this->satisfiedPercentage();

        $points = match (true) {
            $pct >= 90.0 => $maxPoints,
            $pct >= 60.0 => (int) round($maxPoints * 0.6),
            $pct >= 30.0 => (int) round($maxPoints * 0.2),
            default => 0,
        };

        return min($points, $maxPoints);
    }

    public function fairDimension(): string
    {
        return $this->template->fairBonus['dimension'] ?? 'R';
    }

    /**
     * @return array{
     *     template_id: string,
     *     standard: string,
     *     version: string,
     *     score: array{dimension: string, points: int, max_points: int, satisfied_pct: float},
     *     totals: array{satisfied: int, total: int},
     *     entries: list<array<string, mixed>>
     * }
     */
    public function toArray(): array
    {
        return [
            'template_id' => $this->template->id,
            'standard' => $this->template->name,
            'version' => $this->template->version,
            'score' => [
                'dimension' => $this->fairDimension(),
                'points' => $this->fairScore(),
                'max_points' => $this->template->fairBonus['max_points'] ?? 5,
                'satisfied_pct' => $this->satisfiedPercentage(),
            ],
            'totals' => [
                'satisfied' => $this->satisfiedCount(),
                'total' => $this->totalCount(),
            ],
            'entries' => array_map(static fn (ConformanceEntry $e): array => $e->toArray(), $this->entries),
        ];
    }
}
