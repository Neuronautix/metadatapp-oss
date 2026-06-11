<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum HcmDataType: string
{
    use EnumApiResourceTrait;

    case Activity = 'Activity';
    case FoodIntake = 'Food Intake';
    case Sinusoid = 'Sinusoid';
}
