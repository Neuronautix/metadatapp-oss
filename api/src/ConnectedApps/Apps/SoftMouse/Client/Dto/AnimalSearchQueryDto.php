<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;

class AnimalSearchQueryDto
{
    public function __construct(
        public ?string $strain = null,
        public ?string $cageTag = null,
        public ?int $sex = null,
        #[SerializedName('pageSize')]
        public int $limit = 100,
        #[SerializedName('pageIndex')]
        public int $page = 1,
        public array $additional = [],
    ) {
    }
}
