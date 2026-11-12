<?php

declare(strict_types=1);

namespace App\Enum;

enum AlertSeverity: string
{
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case CRITICAL = 'CRITICAL';
}
