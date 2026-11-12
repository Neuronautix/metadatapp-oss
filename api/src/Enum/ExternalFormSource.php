<?php

declare(strict_types=1);

namespace App\Enum;

enum ExternalFormSource: string
{
    use EnumApiResourceTrait;

    case NIH_CDE = 'nih_cde';
    case METADATAPP = 'metadatapp';
    case CUSTOM = 'custom';
}
