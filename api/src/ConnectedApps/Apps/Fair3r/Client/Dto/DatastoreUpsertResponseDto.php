<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

class DatastoreUpsertResponseDto
{
    public function __construct(
        /**
         * @var DatastoreFieldDto[]
         */
        #[SerializedName('__extras')]
        #[Assert\Valid]
        #[Assert\All([
            new Assert\Type(type: DatastoreFieldDto::class),
        ])]
        public array $fields = [],

        #[Assert\NotBlank(message: 'Resource ID must not be blank.')]
        #[Assert\Uuid(message: 'resourceId must be a valid UUID.')]
        #[SerializedName('resource_id')]
        public ?string $resource_id = null,

        /**
         * @var array<int, array<string, mixed>>|null
         */
        public ?array $records = null,
    ) {
    }
}
