<?php

declare(strict_types=1);

namespace App\Enum;

enum MappingStatus: string
{
    use EnumApiResourceTrait;

    case SUGGESTED = 'suggested';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case DEPRECATED = 'deprecated';
}
