<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(normalizationContext: ['groups' => ['enum:read']])]
#[GetCollection(provider: [MortalityReason::class, 'getCases'])]
enum MortalityReason: string
{
    use EnumApiResourceTrait;

    case NATURAL = 'natural';
    case DISEASE = 'disease';
    case EXPERIMENTAL_ENDPOINT = 'experimental endpoint';
    case UNKNOWN = 'unknown';
    case SYSTEM_ISSUE = 'system issue';
}
