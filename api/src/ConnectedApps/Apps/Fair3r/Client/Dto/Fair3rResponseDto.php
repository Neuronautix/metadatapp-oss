<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class Fair3rResponseDto
{
    public function __construct(
        #[Assert\Url(message: "The help URL '{{ value }}' is not a valid URL.")]
        public ?string $help = null,

        public bool $success = false,

        public array $result = [],

        public array $error = [],
    ) {
    }
}
