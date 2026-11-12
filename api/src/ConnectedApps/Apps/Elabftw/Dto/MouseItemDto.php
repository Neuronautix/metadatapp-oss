<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Dto;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExtraFieldDto;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for creating or updating a mouse item in ElabFTW.
 */
final class MouseItemDto
{
    /**
     * @param ExtraFieldDto[] $extraFields
     */
    public function __construct(
        #[Assert\Blank]
        private readonly ?string $elabId = null,
        #[Assert\Blank]
        private readonly ?string $name = null,
        /** @var ExtraFieldDto[]|null $extraFields */
        #[Assert\Valid] // Ensures nested DTOs are validated
        private readonly ?array $extraFields = null,
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return ExtraFieldDto[]|null
     */
    public function getExtraFields(): ?array
    {
        return $this->extraFields;
    }

    public function getElabId(): ?string
    {
        return $this->elabId;
    }
}
