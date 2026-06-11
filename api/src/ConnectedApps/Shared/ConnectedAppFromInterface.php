<?php

declare(strict_types=1);

namespace App\ConnectedApps\Shared;

use App\Entity\ConnectedApp;
use App\Entity\ConnectedAppEntityInterface;
use Symfony\Component\Uid\Uuid;

interface ConnectedAppFromInterface
{
    /**
     * @return string[]
     */
    public static function getSupportedEntitiesSyncFrom(): array;

    /**
     * Checks if the service supports for a specific app code the synchronization from the connected app to MAPP. We fetch data from the app.
     */
    public function supportsEntitySyncFrom(string $entityClassname): bool;

    /**
     * Synchronizes all entities from the connected app to MAPP.
     * This method is used to pull data from the connected app.
     *
     * @return array<ConnectedAppEntityInterface>|null
     *
     * @throws \Exception
     */
    public function syncAllFromExternal(ConnectedApp $connectedApp, string $entityClassname): ?array;

    /**
     * Synchronizes a specific entity from MAPP to the connected app.
     * This method is used to push data for a specific entity to the connected app.
     */
    public function syncOneFromExternal(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void;

    /**
     * Full synchronization from the connected app to MAPP.
     * This method is used to pull all data from the connected app, for all supported entities.
     */
    public function fullSynchronizationFromExternal(ConnectedApp $connectedApp): void;
}
