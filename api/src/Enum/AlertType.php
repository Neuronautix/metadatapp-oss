<?php

declare(strict_types=1);

namespace App\Enum;

enum AlertType: string
{
    case TEMPERATURE_OUTSIDE_RANGE = 'temperature outside range';
    case PH_OUTSIDE_RANGE = 'pH outside range';
    case HIGH_MORTALITY_IN_BATCH = 'high mortality in batch';
}
