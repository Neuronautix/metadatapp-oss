<?php

declare(strict_types=1);

namespace App\Enum;

enum AlertEntityType: string
{
    case BATCH = 'batch';
    case ROOM = 'room';
    case SYSTEM = 'system';
}
