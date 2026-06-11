<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Entity\UserAwareInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator('api_platform.doctrine.orm.state.persist_processor')]
/** @implements ProcessorInterface<PersistProcessor, mixed> */
class SetUserProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $innerProcessor,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isSubclassOf = is_subclass_of($data, UserAwareInterface::class, true);
        $user = $this->security->getUser();
        if (
            $isSubclassOf
            && $user instanceof User
            && \is_object($data) // Ensure $data is an object
            && null === $data->getId()
        ) {
            $managedUser = $this->entityManager->find(User::class, $user->getId());
            if (null !== $managedUser) {
                $data->setUser($managedUser);
            }
        }

        return $this->innerProcessor->process($data, $operation, $uriVariables, $context);
    }
}
