<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum TreatmentType: string
{
    use EnumApiResourceTrait;

    case Pharmacological = 'pharmacological';
    case Environmental = 'environmental';
    case InFood = 'inFood';
    case Other = 'other';
}
