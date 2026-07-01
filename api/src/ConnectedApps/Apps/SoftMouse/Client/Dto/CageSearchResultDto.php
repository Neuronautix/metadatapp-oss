<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

/**
 * Search result for SoftMouse Cages.
 */
readonly class CageSearchResultDto
{
    /**
     * @param CageDto[] $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $limit,
    ) {
    }
}
