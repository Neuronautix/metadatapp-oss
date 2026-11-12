<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

readonly class DatastoreFieldCreateDto
{
    public function __construct(
        public string $id,
    ) {
    }
}
