<?php

declare(strict_types=1);

namespace App\ConnectedApps\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ConnectedAppEntityInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

// // and create repository that will be used for instance when we persit the project with the info from the preclinicalTrials
// // https://github.com/api-platform/demo/blob/86671152a0860257c218298b2b0e7508bbff254b/api/src/State/Processor/BookPersistProcessor.php#L35
final readonly class ConnectedAppPersistProcessor implements ProcessorInterface
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

        // TODO: Hydrate and merge with the data fetched from the connected app, exactly as done in the ConnectedAppResourceProvider.
        // 1) Persist  locally
        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
