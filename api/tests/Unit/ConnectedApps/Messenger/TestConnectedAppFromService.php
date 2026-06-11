<?php

declare(strict_types=1);

namespace App\Tests\Unit\ConnectedApps\Messenger;

use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;

interface TestConnectedAppFromService extends ConnectedAppServiceInterface, ConnectedAppFromInterface
{
}
