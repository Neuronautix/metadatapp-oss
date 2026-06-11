<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum BeddingType: string
{
    use EnumApiResourceTrait;

    case Oak = 'oak';
    case Osk = 'osk';
}
