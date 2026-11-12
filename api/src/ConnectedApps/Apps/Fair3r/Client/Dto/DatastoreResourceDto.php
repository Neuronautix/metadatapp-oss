<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

class DatastoreResourceDto
{
    public function __construct(
        // It's map to our project fair3rExternalId
        #[SerializedName('package_id')]
        #[Assert\NotBlank(message: 'package_id must not be blank.')]
        public string $packageId,
        //        public string $package_id,

        #[Assert\NotBlank(message: 'url must not be blank.')]
        public string $url,

        public ?string $mimetype = null,
    ) {
    }
}
