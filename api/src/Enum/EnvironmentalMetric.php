<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(normalizationContext: ['groups' => ['enum:read']])]
#[GetCollection(provider: [EnvironmentalMetric::class, 'getCases'])]
enum EnvironmentalMetric: string
{
    use EnumApiResourceTrait;

    case TEMPERATURE = 'temperature';
    case PH = 'pH';
}
