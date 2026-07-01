<?php

declare(strict_types=1);

namespace App\Enum;

enum CdeStatus: string
{
    use EnumApiResourceTrait;

    case DRAFT = 'draft';
    case RECOMMENDED = 'recommended';
    case ENDORSED = 'endorsed';
    case DEPRECATED = 'deprecated';
}
