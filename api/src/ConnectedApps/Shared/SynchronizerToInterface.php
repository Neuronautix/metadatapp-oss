<?php

declare(strict_types=1);

namespace App\ConnectedApps\Shared;

use App\Entity\ConnectedApp;
use App\Entity\ConnectedAppEntityInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Synchronizer for pushing local resources to a connected app.
 */
interface SynchronizerToInterface
{
    /**
     * @return array<ConnectedAppEntityInterface>|null
     */
    public function syncAllTo(ConnectedApp $connectedApp): ?array;

    public function syncOneTo(ConnectedApp $connectedApp, Uuid $entityId): ?ConnectedAppEntityInterface;
}
