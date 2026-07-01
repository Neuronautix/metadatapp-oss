<?php

declare(strict_types=1);

namespace App\ConnectedApps;

use App\Enum\AppCode;

interface ConnectedAppServiceInterface
{
    public function supportsCode(AppCode $appCode): bool;

    public function supportsEntitySyncTo(string $entityClassname): bool;

    public function supportsEntitySyncFrom(string $entityClassname): bool;
}
