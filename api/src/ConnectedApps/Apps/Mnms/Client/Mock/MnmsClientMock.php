<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Mnms\Client\Mock;

use App\ConnectedApps\Apps\Mnms\Client\MnmsClientInterface;
use App\Entity\ConnectedApp;

final class MnmsClientMock implements MnmsClientInterface
{
    public function searchFields(ConnectedApp $connectedApp, string $query, int $limit): array
    {
        return [];
    }
}
