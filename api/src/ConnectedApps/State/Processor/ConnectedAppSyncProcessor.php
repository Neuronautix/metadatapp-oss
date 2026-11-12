<?php

declare(strict_types=1);

namespace App\ConnectedApps\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ConnectedApps\Messenger\Message\SyncOneToAppMessage;
use App\Entity\ConnectedAppEntityInterface;
use Doctrine\Common\Util\ClassUtils;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ConnectedAppSyncProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (!$data instanceof ConnectedAppEntityInterface) {
            return $data;
        }
        if (null === ($user = $this->security->getUser())) {
            return $data;
        }

        // 1) Persist  locally
        $data = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->messageBus->dispatch(new SyncOneToAppMessage(
            $user->getId(),
            ClassUtils::getClass($data),
            $data->getId(),
        ));

        return $data;
    }
}
