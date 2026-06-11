<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

final class ExperimentPatchDto
{
    public function __construct(
        public ?string $title = null,
        public ?string $body = null,
        public ?int $category = null,
    ) {
    }
}
