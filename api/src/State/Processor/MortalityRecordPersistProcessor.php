<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\MortalityRecord;
use App\Entity\User;
use App\Repository\MortalityRecordRepository;
use App\Zefix\Alerts\AlertRuleEvaluator;
use App\Zefix\Support\ZefixPermissionChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** @implements ProcessorInterface<MortalityRecord, MortalityRecord> */
final readonly class MortalityRecordPersistProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<MortalityRecord, MortalityRecord> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private MortalityRecordRepository $mortalityRecordRepository,
        private AlertRuleEvaluator $alertRuleEvaluator,
        private ZefixPermissionChecker $permissionChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MortalityRecord
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User is not authenticated.');
        }

        $this->permissionChecker->assertCanEdit($user);

        $managedUser = $this->entityManager->find(User::class, $user->getId());
        if (!$managedUser instanceof User) {
            throw new AccessDeniedHttpException('User could not be loaded from the current entity manager.');
        }

        if ($data->getBatch()->getAccount()->getId()?->toRfc4122() !== $user->getAccount()->getId()?->toRfc4122()) {
            throw new AccessDeniedHttpException('The selected batch does not belong to the authenticated account.');
        }

        $data->setAccount($user->getAccount());
        $data->setUser($managedUser);

        $initialPopulation = $data->getBatch()->getMaleCount()
            + $data->getBatch()->getFemaleCount()
            + $data->getBatch()->getUnknownSexCount();
        $existingMortalityCount = $this->mortalityRecordRepository->sumCountForBatch($data->getBatch());
        $remainingPopulation = max(0, $initialPopulation - $existingMortalityCount);

        if ($data->getCount() > $remainingPopulation) {
            throw new UnprocessableEntityHttpException('Mortality count cannot exceed the current batch population.');
        }

        $data->getBatch()->setTotalPopulation($remainingPopulation - $data->getCount());

        /** @var MortalityRecord $record */
        $record = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        $this->alertRuleEvaluator->evaluateMortalityRecord($record);

        return $record;
    }
}
