<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum EnrichmentType: string
{
    use EnumApiResourceTrait;

    case RunningWheel = 'runningWheel';
    case Tunnel = 'tunnel';
    case House = 'house';
    case CotonSwab = 'cotonSwab';
    case Wood = 'wood';
    case Toys = 'toys';
}
