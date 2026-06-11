<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

final class ElabftwResponseDto
{
    public function __construct(
        public ?int $id = null,
        public ?string $message = null,
        public mixed $data = null,
    ) {
    }
}
