<?php

declare(strict_types=1);

namespace App\ConnectedApps\Messenger\Message;

use Symfony\Component\Uid\Uuid;

/**
 * This message is used to trigger a full outbound synchronization from Metadatapp to one connected app.
 */
final readonly class SyncFullToAppMessage implements SyncAppMessageInterface
{
    public function __construct(
        public Uuid $connectedAppId,
    ) {
    }
}
