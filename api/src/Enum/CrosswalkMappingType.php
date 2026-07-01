<?php

declare(strict_types=1);

namespace App\Enum;

enum CrosswalkMappingType: string
{
    use EnumApiResourceTrait;

    case EXACT = 'exact';
    case CLOSE = 'close';
    case PARTIAL = 'partial';
    case COMPOSITE = 'composite';
    case BROADER = 'broader';
    case NARROWER = 'narrower';
    case RELATED = 'related';
    case NO_MATCH = 'no_match';
}
