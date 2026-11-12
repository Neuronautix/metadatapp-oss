<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum Gas: string
{
    use EnumApiResourceTrait;

    case Oxygen = 'Oxygen';
    case CarbonDioxide = 'Carbon Dioxide';
    case Nitrogen = 'Nitrogen';
    case Methane = 'Methane';
}
