<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(normalizationContext: ['groups' => ['enum:read']]),
    GetCollection(provider: [HealthStatus::class, 'getCases']),] enum HealthStatus: string
    {
        use EnumApiResourceTrait;

        case NORMAL = 'Normal';
        case WARNING_LEVEL_1 = 'Warning Level1';
        case WARNING_LEVEL_2 = 'Warning Level2';
        case WARNING_LEVEL_3 = 'Warning Level3';
    }
