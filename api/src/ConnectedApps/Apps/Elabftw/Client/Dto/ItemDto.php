<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

readonly class ItemDto
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $body = null,
        public array $metadata = [],
    ) {
    }
}
