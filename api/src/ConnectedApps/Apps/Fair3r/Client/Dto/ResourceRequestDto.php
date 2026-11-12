<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ResourceRequestDto
{
    public function __construct(
        // the id of the Dataset (aka package) this resource belongs to,
        //        #[SerializedName('package_id')]
        #[Assert\NotBlank(message: 'package_id must not be blank.')]
        public ?string $package_id = null,

        public ?string $name = null,

        public ?string $description = null,
    ) {
    }
}
