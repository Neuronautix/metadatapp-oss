<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

final class DatastoreDumpResponseDto
{
    /**
     * @param array<int, array<string, mixed>> $fields
     * @param array<int, mixed>                $records
     */
    public function __construct(
        public array $fields = [],
        public array $records = [],
    ) {
    }
}
