<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

readonly class UpdateItemDto
{
    public function __construct(
        public ?string $title = null,
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
