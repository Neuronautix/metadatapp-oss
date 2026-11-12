<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(normalizationContext: ['groups' => ['enum:read']])]
#[GetCollection(provider: [ZebrafishBatchLifecycleStatus::class, 'getCases'])]
enum ZebrafishBatchLifecycleStatus: string
{
    use EnumApiResourceTrait;

    case ACTIVE = 'active';
    case QUARANTINED = 'quarantined';
    case COMPLETED = 'completed';
    case ARCHIVED = 'archived';
}
