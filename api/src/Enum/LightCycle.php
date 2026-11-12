<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum LightCycle: string
{
    use EnumApiResourceTrait;

    case ON_08_OFF_20 = '08:00 ON, 20:00 OFF';
    case REVERSED = 'reversed';
    case OFF_08_ON_20 = '08:00 OFF, 20:00 ON';
}
