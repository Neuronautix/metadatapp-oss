<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Dto;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedPath;
use Symfony\Component\Validator\Constraints as Assert;

class ElabftwExperimentRequestDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Groups(['elabftw.experiment'])]
        #[SerializedPath('[name]')]
        public string $title,

        #[Groups(['elabftw.experiment'])]
        #[SerializedPath('[description]')]
        public ?string $body = null,

        #[Groups(['elabftw.experiment'])]
        public ?int $category = null,

        #[Groups(['elabftw.experiment'])]
        public ?int $status = null,

        #[Groups(['elabftw.experiment'])]
        public ?string $metadata = null,

        #[Groups(['elabftw.experiment'])]
        public int $template = -1,

        /**
         * @var string[]|null
         */
        #[Groups(['elabftw.experiment'])]
        public ?array $tags = [],
    ) {
    }
}
