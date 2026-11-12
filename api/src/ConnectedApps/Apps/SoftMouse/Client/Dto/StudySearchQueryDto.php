<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

class StudySearchQueryDto
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?\DateTime $startAfter = null,
        public readonly ?\DateTime $endBefore = null,
        public readonly int $page = 1,
        public readonly int $limit = 50,
    ) {
    }
}
