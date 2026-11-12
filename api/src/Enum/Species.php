<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum Species: string
{
    use EnumApiResourceTrait;

    case Mouse = 'mouse';
    case Rat = 'rat';
    case Zebrafish = 'zebrafish';
}
