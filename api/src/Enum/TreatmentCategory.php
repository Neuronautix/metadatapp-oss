<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum TreatmentCategory: string
{
    use EnumApiResourceTrait;

    case ACUTE = 'acute';
    case CHRONIC = 'chronic';
}
