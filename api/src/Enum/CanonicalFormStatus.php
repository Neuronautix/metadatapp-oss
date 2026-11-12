<?php

declare(strict_types=1);

namespace App\Enum;

enum CanonicalFormStatus: string
{
    use EnumApiResourceTrait;

    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case DEPRECATED = 'deprecated';
}
