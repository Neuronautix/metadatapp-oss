<?php

declare(strict_types=1);

namespace App\ConnectedApps\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ConnectedApps\Messenger\Message\SyncFullFromAppMessage;
use App\Entity\ConnectedApp;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConnectedAppFullSyncProcessor implements ProcessorInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ConnectedApp) {
            return $data;
        }

        $this->messageBus->dispatch(new SyncFullFromAppMessage($data->getId()));

        return $data;
    }
}
