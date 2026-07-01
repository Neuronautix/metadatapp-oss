<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Account;
use App\Entity\Pool;
use App\Entity\User;
use App\Entity\ZebrafishBatch;
use App\Enum\PoolStatus;
use App\Enum\ZebrafishBatchLifecycleStatus;
use App\Zefix\Support\ZefixBatchStatistics;
use App\Zefix\Support\ZefixPermissionChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** @implements ProcessorInterface<ZebrafishBatch, ZebrafishBatch> */
final readonly class ZebrafishBatchPersistProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private ZefixBatchStatistics $statistics,
        private ZefixPermissionChecker $permissionChecker,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ZebrafishBatch
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User is not authenticated.');
        }

        $this->permissionChecker->assertCanEdit($user);

        if (!$this->sameAccount($data->getLine()->getAccount(), $user->getAccount())) {
            throw new AccessDeniedHttpException('The selected line does not belong to the authenticated account.');
        }

        if (!$this->sameAccount($data->getPool()->getAccount(), $user->getAccount())) {
            throw new AccessDeniedHttpException('The selected pool does not belong to the authenticated account.');
        }

        $data->setTotalPopulation($this->statistics->getCurrentPopulation($data));
        $this->assertPoolCanHostBatch($data);
        $this->synchronizePoolStatus($data->getPool(), $data);

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function assertPoolCanHostBatch(ZebrafishBatch $batch): void
    {
        if (!$this->isOccupyingLifecycle($batch->getLifecycleStatus())) {
            return;
        }

        $pool = $batch->getPool();
        if (!\in_array($pool->getStatus(), [PoolStatus::AVAILABLE, PoolStatus::OCCUPIED], true)) {
            throw new UnprocessableEntityHttpException('A batch can only be assigned to an empty or available pool.');
        }

        if ($this->countOccupyingBatches($pool, $batch) > 0) {
            throw new UnprocessableEntityHttpException('A batch can only be assigned to an empty or available pool.');
        }
    }

    private function synchronizePoolStatus(Pool $pool, ZebrafishBatch $batch): void
    {
        if ($this->isOccupyingLifecycle($batch->getLifecycleStatus())) {
            $pool->setStatus(PoolStatus::OCCUPIED);

            return;
        }

        if ($this->countOccupyingBatches($pool, $batch) > 0) {
            $pool->setStatus(PoolStatus::OCCUPIED);

            return;
        }

        if (PoolStatus::OCCUPIED === $pool->getStatus()) {
            $pool->setStatus(PoolStatus::AVAILABLE);
        }
    }

    private function countOccupyingBatches(Pool $pool, ?ZebrafishBatch $ignoreBatch = null): int
    {
        $queryBuilder = $this->entityManager
            ->getRepository(ZebrafishBatch::class)
            ->createQueryBuilder('batch')
            ->select('COUNT(batch.id)')
            ->andWhere('batch.pool = :pool')
            ->andWhere('batch.lifecycleStatus IN (:statuses)')
            ->setParameter('pool', $pool)
            ->setParameter('statuses', [ZebrafishBatchLifecycleStatus::ACTIVE, ZebrafishBatchLifecycleStatus::QUARANTINED])
        ;

        if (null !== $ignoreBatch?->getId()) {
            $queryBuilder
                ->andWhere('batch.id != :batchId')
                ->setParameter('batchId', $ignoreBatch->getId())
            ;
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function isOccupyingLifecycle(ZebrafishBatchLifecycleStatus $status): bool
    {
        return \in_array($status, [ZebrafishBatchLifecycleStatus::ACTIVE, ZebrafishBatchLifecycleStatus::QUARANTINED], true);
    }

    private function sameAccount(Account $left, Account $right): bool
    {
        return $left->getId()?->toRfc4122() === $right->getId()?->toRfc4122();
    }
}
