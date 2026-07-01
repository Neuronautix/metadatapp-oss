<?php

declare(strict_types=1);

namespace App\Enum;

enum ExternalTemplateFormat: string
{
    use EnumApiResourceTrait;

    case ELN_RO_CRATE = 'eln_ro_crate';
    case CEDAR_TEMPLATE = 'cedar_template';
    case CUSTOM = 'custom';
}
