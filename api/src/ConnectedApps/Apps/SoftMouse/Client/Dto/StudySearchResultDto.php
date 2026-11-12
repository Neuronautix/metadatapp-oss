<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

readonly class StudySearchResultDto
{
    public function __construct(
        public int $total,
        /** @var StudyDto[] $items */
        public array $items,
    ) {
    }
}
