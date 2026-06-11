<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class DatastoreCreateResponseDto
{
    public function __construct(
        #[Assert\Valid]
        public DatastoreResourceDto $resource,

        //        #[SerializedName('resource_id')]
        #[Assert\NotBlank(message: 'resource_id must not be blank.')]
        //        public string $resourceId,
        public string $resource_id,

        #[Assert\NotBlank(message: 'method must not be blank.')]
        #[Assert\Choice(choices: ['insert', 'update', 'upsert', 'delete'], message: 'Method must be one of insert, update, upsert or delete.')]
        public string $method,

        /**
         * @var DatastoreFieldDto[]
         */
        #[Assert\Valid]
        #[Assert\All([
            new Assert\Type(type: DatastoreFieldDto::class, message: 'Each entry must be a DatastoreFieldDto.'),
        ])]
        public ?array $fields = [],
    ) {
    }
}
