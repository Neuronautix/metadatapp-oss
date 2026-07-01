<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

class ProtocolSearchQueryDto
{
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public ?string $status = null,
        public int $limit = 100,
        public int $page = 1,
        public array $additional = [],
    ) {
    }
}
