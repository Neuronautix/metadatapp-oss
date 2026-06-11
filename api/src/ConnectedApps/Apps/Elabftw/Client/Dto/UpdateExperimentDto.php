<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

readonly class UpdateExperimentDto
{
    public function __construct(
        public ?string $title = null,
        public ?int $id = null,
        public ?string $body = null,
    ) {
    }

    // todo refactor
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'id' => $this->id,
                'title' => $this->title,
                'body' => $this->body,
            ],
            static fn ($value) => null !== $value,
        );
    }
}
