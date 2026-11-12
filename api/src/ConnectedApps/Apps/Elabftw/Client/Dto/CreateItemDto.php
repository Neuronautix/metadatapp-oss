<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

readonly class CreateItemDto
{
    public function __construct(
        public int $categoryId,
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
                'category_id' => $this->categoryId,
                'title' => $this->title,
                'body' => $this->body,
            ],
            static fn ($value) => null !== $value,
        );
    }
}
