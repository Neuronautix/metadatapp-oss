<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum CageFormat: string
{
    use EnumApiResourceTrait;

    case T1 = 'T1';
    case T2 = 'T2';
    case T3 = 'T3';
    case T4 = 'T4';
}
