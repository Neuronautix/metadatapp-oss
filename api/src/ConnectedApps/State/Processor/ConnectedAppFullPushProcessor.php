<?php

declare(strict_types=1);

namespace App\ConnectedApps\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ConnectedApps\Messenger\Message\SyncFullToAppMessage;
use App\Entity\ConnectedApp;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConnectedAppFullPushProcessor implements ProcessorInterface
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

        $this->messageBus->dispatch(new SyncFullToAppMessage($data->getId()));

        return $data;
    }
}
