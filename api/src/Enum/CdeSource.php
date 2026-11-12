<?php

declare(strict_types=1);

namespace App\Enum;

enum CdeSource: string
{
    use EnumApiResourceTrait;

    case NIH_CDE = 'nih_cde';
    case METADATAPP = 'metadatapp';
    case HCMO = 'hcmo';
    case MBO = 'mbo';
    case CUSTOM = 'custom';
}
