<?php

declare(strict_types=1);

namespace App\ConnectedApps\Messenger\Message;

use Symfony\Component\Uid\Uuid;

/**
 * This message is used for a User to trigger a synchronization of all entities of a specific class
 * from the connected app to the local application.
 */
final class SyncOneFromAppMessage implements SyncAppMessageInterface
{
    public function __construct(
        public readonly Uuid $userId,
        public readonly string $entityClassName,
        public readonly Uuid $entityId,
    ) {
    }
}
