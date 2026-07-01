<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(normalizationContext: ['groups' => ['enum:read']])]
#[GetCollection(provider: [EnvironmentalObservationSource::class, 'getCases'])]
enum EnvironmentalObservationSource: string
{
    use EnumApiResourceTrait;

    case MANUAL = 'manual';
    case CONNECTED = 'connected';
}
