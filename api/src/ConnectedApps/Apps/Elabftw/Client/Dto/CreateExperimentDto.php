<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

readonly class CreateExperimentDto
{
    public function __construct(
        public string $title,
        public ?string $body = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'title' => $this->title,
                'body' => $this->body,
            ],
            static fn ($value) => null !== $value,
        );
    }
}
