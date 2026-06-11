<?php

declare(strict_types=1);

namespace App\Enum;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
enum ImportSessionStatus: string
{
    use EnumApiResourceTrait;

    case Uploaded = 'uploaded';
    case Profiled = 'profiled';
    case ReviewReady = 'review_ready';
    case PatchesBuilt = 'patches_built';
    case Applied = 'applied';
    case Failed = 'failed';
}
