<?php

declare(strict_types=1);

namespace App\Enum;

enum ExternalTemplateSource: string
{
    use EnumApiResourceTrait;

    case ELN_COMMUNITY = 'eln_community';
    case ELN_FILE = 'eln_file';
    case ELABFTW = 'elabftw';
    case CEDAR = 'cedar';
    case CUSTOM = 'custom';
}
