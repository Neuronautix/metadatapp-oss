<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ExperimentCreateRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Groups(['elabftw:experiment:create', 'elabftw:experiment:update'])]
        public string $title,

        #[Assert\NotBlank]
        #[Groups(['elabftw:experiment:create', 'elabftw:experiment:update'])]
        public string $body,

        /**
         * @var array<int, string>
         */
        #[Assert\NotNull]
        #[Assert\All([new Assert\NotBlank()])]
        #[Groups(['elabftw:experiment:create', 'elabftw:experiment:update'])]
        public array $tags,

        #[Assert\NotNull]
        #[Groups(['elabftw:experiment:create', 'elabftw:experiment:update'])]
        public int $status,

        /**
         * Raw eLabFTW metadata payload serialized as JSON.
         */
        #[Groups(['elabftw:experiment:create', 'elabftw:experiment:update'])]
        public ?string $metadata = null,
    ) {
    }
}
