<?php

declare(strict_types=1);

namespace App\ConnectedApps\Messenger\Message;

use Symfony\Component\Uid\Uuid;

/**
 * This message is used for a User to trigger a synchronization of all entities of a specific class
 * from the connected app to the local application.
 */
final readonly class SyncOneToAppMessage implements SyncAppMessageInterface
{
    /**
     * @param ?Uuid $senderConnectedAppId is used to specify the connected app that triggered the sync to avoid infinite loops
     */
    public function __construct(
        public Uuid $userId,
        public string $entityClassName,
        public Uuid $entityId,
        public ?Uuid $senderConnectedAppId = null,
    ) {
    }
}
