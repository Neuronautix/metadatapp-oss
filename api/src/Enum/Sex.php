<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum Sex: string
{
    use EnumApiResourceTrait;

    case Male = 'male';
    case Female = 'female';
    case Unknown = 'unknown';
}
