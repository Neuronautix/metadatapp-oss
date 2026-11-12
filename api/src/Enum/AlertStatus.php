<?php

declare(strict_types=1);

namespace App\Enum;

enum AlertStatus: string
{
    case ACTIVE = 'active';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';
}
