<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum TreatmentRoute: string
{
    use EnumApiResourceTrait;

    case IP = 'IP';
    case IV = 'IV';
    case IM = 'IM';
    case Gavage = 'gavage';
    case TailVein = 'tailVein';
}
