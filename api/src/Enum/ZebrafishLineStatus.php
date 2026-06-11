<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(normalizationContext: ['groups' => ['enum:read']])]
#[GetCollection(provider: [ZebrafishLineStatus::class, 'getCases'])]
enum ZebrafishLineStatus: string
{
    use EnumApiResourceTrait;

    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case ARCHIVED = 'archived';
}
