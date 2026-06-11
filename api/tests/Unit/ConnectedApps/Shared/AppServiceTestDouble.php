<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Shared;

use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;

interface AppServiceTestDouble extends ConnectedAppServiceInterface, ConnectedAppFromInterface
{
}
