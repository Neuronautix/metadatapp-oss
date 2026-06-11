<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\EnvironmentalObservation;
use App\Entity\Room;
use App\Entity\System;
use App\Entity\User;
use App\Zefix\Alerts\AlertRuleEvaluator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** @implements ProcessorInterface<EnvironmentalObservation, EnvironmentalObservation> */
final readonly class EnvironmentalObservationPersistProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<EnvironmentalObservation, EnvironmentalObservation> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private AlertRuleEvaluator $alertRuleEvaluator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): EnvironmentalObservation
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User is not authenticated.');
        }

        $managedUser = $this->entityManager->find(User::class, $user->getId());
        if (!$managedUser instanceof User) {
            throw new AccessDeniedHttpException('User could not be loaded from the current entity manager.');
        }

        $system = $data->getSystem();
        $room = $data->getRoom();
        if ((null === $system && null === $room) || (null !== $system && null !== $room)) {
            throw new UnprocessableEntityHttpException('An environmental observation must target exactly one scope: system or room.');
        }

        $scopeAccount = $this->resolveScopeAccount($system, $room);
        if ($scopeAccount->getId()?->toRfc4122() !== $user->getAccount()->getId()?->toRfc4122()) {
            throw new AccessDeniedHttpException('The selected scope does not belong to the authenticated account.');
        }

        $data->setAccount($scopeAccount);
        $data->setUser($managedUser);

        /** @var EnvironmentalObservation $record */
        $record = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->alertRuleEvaluator->evaluateEnvironmentalObservation($record);

        return $record;
    }

    private function resolveScopeAccount(?System $system, ?Room $room): \App\Entity\Account
    {
        if ($system instanceof System) {
            return $system->getAccount();
        }

        if ($room instanceof Room) {
            return $room->getAccount();
        }

        throw new UnprocessableEntityHttpException('Unable to resolve the environmental observation scope.');
    }
}
