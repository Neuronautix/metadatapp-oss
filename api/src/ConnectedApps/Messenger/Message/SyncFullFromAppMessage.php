<?php

declare(strict_types=1);

namespace App\ConnectedApps\Messenger\Message;

use Symfony\Component\Uid\Uuid;

/**
 * This message is used for a connectedApp to trigger a synchronization of all entities of all classes
 * from the connected app to the local application.
 */
readonly class SyncFullFromAppMessage implements SyncAppMessageInterface
{
    public function __construct(
        public Uuid $connectedAppId,
    ) {
    }
}
