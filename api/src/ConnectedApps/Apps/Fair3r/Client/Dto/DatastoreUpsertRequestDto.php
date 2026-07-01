<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class DatastoreUpsertRequestDto
{
    public function __construct(
        #[Assert\Valid]
        public ?ResourceRequestDto $resource = null,

        // it's map to our experiment fair3rExternalId
        public ?string $resource_id = null,

        #[Assert\All([
            new Assert\Type(type: 'array'),
            new Assert\All([
                new Assert\Type(type: 'mixed'),
            ]),
        ])]
        public ?array $records = null,

        #[Assert\Choice(choices: ['insert', 'update', 'upsert', 'delete'], message: 'Method must be one of insert, update, upsert or delete.')]
        public string $method = 'insert',
    ) {
    }
}
