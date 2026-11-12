<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

final class ExperimentCreateDto
{
    public function __construct(
        public string $title,
        public ?string $body = null,
        public ?int $category = null,
    ) {
    }
}
