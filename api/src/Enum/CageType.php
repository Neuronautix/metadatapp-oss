<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum CageType: string
{
    use EnumApiResourceTrait;

    case Standard = 'standard';
    case Hcm = 'hcm';
    case Breeding = 'breeding';
    case Experimental = 'experimental';
}
