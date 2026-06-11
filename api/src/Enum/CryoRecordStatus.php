<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(normalizationContext: ['groups' => ['enum:read']])]
#[GetCollection(provider: [CryoRecordStatus::class, 'getCases'])]
enum CryoRecordStatus: string
{
    use EnumApiResourceTrait;

    case STORED = 'stored';
    case USED = 'used';
    case DISCARDED = 'discarded';
}
