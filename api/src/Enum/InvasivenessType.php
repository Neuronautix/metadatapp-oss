<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum InvasivenessType: string
{
    use EnumApiResourceTrait;

    case Injection = 'injection';
    case Incision = 'incision';
    case Biopsy = 'biopsy';
    case Surgery = 'surgery';
}
