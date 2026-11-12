<?php

declare(strict_types=1);

namespace App\ConnectedApps\Shared;

use App\Entity\ConnectedApp;
use Symfony\Component\Uid\Uuid;

interface ConnectedAppToInterface
{
    /**
     * @return string[]
     */
    public static function getSupportedEntitiesSyncTo(): array;

    /**
     * Checks if the service supports for a specific entity the synchronization from MAPP to the connected app. We send data to the app.
     */
    public function supportsEntitySyncTo(string $entityClassname): bool;

    /**
     * Synchronizes all entities to the connected app from MAPP.
     * This method is used to push data to the connected app.
     */
    public function syncAllToExternal(ConnectedApp $connectedApp, string $entityClassname): void;

    /**
     * Synchronizes a specific entity from the connected app to MAPP.
     * This method is used to pull data for a specific entity from the connected app.
     */
    public function syncOneToExternal(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void;
}
