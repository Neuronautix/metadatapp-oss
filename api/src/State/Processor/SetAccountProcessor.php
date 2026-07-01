<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\AccountAwareInterface;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator('api_platform.doctrine.orm.state.persist_processor')]
/** @implements ProcessorInterface<PersistProcessor, mixed> */
class SetAccountProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ProcessorInterface $innerProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if (
            is_subclass_of($data, AccountAwareInterface::class)
            && \is_object($data) // Ensure $data is an object
            && (null !== ($user = $this->security->getUser()))
            && $user instanceof User
            && null === $data->getId()
        ) {
            // if is new and not set in form, set the current user account
            $data->setAccount($user->getAccount());
        }

        return $this->innerProcessor->process($data, $operation, $uriVariables, $context);
    }
}
