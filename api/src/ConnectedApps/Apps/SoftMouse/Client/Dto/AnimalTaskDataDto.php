<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AnimalTaskDataDto
{
    public function __construct(
        #[Assert\NotNull]
        public int $animalId,
        #[Assert\NotBlank]
        public string $animalSID,
        public ?string $value = null,
        public string $assignedUser = '',
        public array $data = [],
    ) {
    }
}
