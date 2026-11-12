<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class DatastoreCreateRequestDto
{
    public function __construct(
        #[Assert\Valid]
        public ResourceRequestDto $resource,

        /**
         * @var ?DatastoreFieldDto[]
         */
        #[Assert\All([
            new Assert\Type(type: DatastoreFieldCreateDto::class),
            new Assert\NotBlank(),
        ])]
        public ?array $fields = null,

        #[Assert\All([
            new Assert\Type(type: 'array'),
            new Assert\All([
                new Assert\Type(type: 'mixed'),
            ]),
        ])]
        public ?array $records = null,
    ) {
    }
}
