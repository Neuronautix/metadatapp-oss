<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

readonly class ExperimentDto
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $body = null,
        public ?int $status = null,
        public array $tags = [],
        public ?int $category = null,
        public ?array $metadata = null,
    ) {
    }
}
