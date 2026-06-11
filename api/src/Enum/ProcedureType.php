<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum ProcedureType: string
{
    use EnumApiResourceTrait;

    case BEHAVIOR = 'behavior';
    case SURGERY = 'surgery';
    case TREATMENT = 'treatment';
    case BREEDING = 'breeding';
    case IMAGING = 'imaging';
    case RECORDING = 'recording'; // Electorphysiology, Optogenetics, ...
    // Add more types here if needed, e.g., 'breeding', 'imaging', etc.
}
