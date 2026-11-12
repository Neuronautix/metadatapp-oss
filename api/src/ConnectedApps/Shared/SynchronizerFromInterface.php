<?php

declare(strict_types=1);

namespace App\ConnectedApps\Shared;

use App\Entity\ConnectedApp;
use App\Entity\ConnectedAppEntityInterface;
use Symfony\Component\Uid\Uuid;

interface SynchronizerFromInterface
{
    /**
     * @return array<ConnectedAppEntityInterface>|null
     */
    public function syncAllFrom(ConnectedApp $connectedApp): ?array;

    public function syncOneFrom(ConnectedApp $connectedApp, Uuid $entityId): ?ConnectedAppEntityInterface;
}
