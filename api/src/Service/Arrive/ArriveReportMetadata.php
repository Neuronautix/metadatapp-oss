<?php

declare(strict_types=1);

namespace App\Service\Arrive;

/**
 * @phpstan-type ArriveGroups array{essential: list<ArriveChecklistItem>, recommended: list<ArriveChecklistItem>}
 */
final readonly class ArriveReportMetadata
{
    /**
     * @param list<ArriveChecklistItem> $essentialItems
     * @param list<ArriveChecklistItem> $recommendedItems
     * @param list<string>              $warnings
     */
    public function __construct(
        public string $title,
        public string $investigationName,
        public int $studyCount,
        public int $animalCount,
        public array $essentialItems,
        public array $recommendedItems,
        public array $warnings,
    ) {
    }
}
