<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

class SoftMouseResponseDto
{
    public function __construct(
        public ?string $messageCode = null,
        public ?string $messageDesc = null,
        public ?array $respObj = null,
        public ?int $totalPage = null,
        public ?int $currentPage = null,
        public ?int $totalNum = null,
    ) {
    }
}
