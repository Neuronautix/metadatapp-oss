<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum ExperimentType: string
{
    use EnumApiResourceTrait;

    case TypeA = 'TypeA';
    case TypeB = 'TypeB';
    case TypeC = 'TypeC';
}
