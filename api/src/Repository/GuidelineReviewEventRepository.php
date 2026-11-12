<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\GuidelineReviewEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<GuidelineReviewEvent>
 */
final class GuidelineReviewEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuidelineReviewEvent::class);
    }

    /**
     * Full, newest-first audit trail for a (resourceType, resourceId, templateId),
     * scoped to the given account for tenant isolation.
     *
     * @return list<GuidelineReviewEvent>
     */
    public function findHistory(string $resourceType, Uuid $resourceId, string $templateId, Account $account): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.resourceType = :resourceType')
            ->andWhere('e.resourceId = :resourceId')
            ->andWhere('e.templateId = :templateId')
            ->andWhere('IDENTITY(e.account) = :accountId')
            ->setParameter('resourceType', $resourceType)
            ->setParameter('resourceId', $resourceId, 'uuid')
            ->setParameter('templateId', $templateId)
            ->setParameter('accountId', $account->getId(), 'uuid')
            ->orderBy('e.occurredAt', 'DESC')
            // `occurredAt` truncates to whole seconds (TIMESTAMP(0)), so events
            // created within the same second tie — the id (UUIDv7, time-ordered)
            // breaks the tie deterministically in the same direction.
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * The latest report-level decision (approved | changes_requested), or null
     * when the report has never been reviewed. Account-scoped for tenant isolation.
     */
    public function findLatestReportDecision(string $resourceType, Uuid $resourceId, string $templateId, Account $account): ?GuidelineReviewEvent
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.resourceType = :resourceType')
            ->andWhere('e.resourceId = :resourceId')
            ->andWhere('e.templateId = :templateId')
            ->andWhere('IDENTITY(e.account) = :accountId')
            ->andWhere('e.action IN (:actions)')
            ->setParameter('resourceType', $resourceType)
            ->setParameter('resourceId', $resourceId, 'uuid')
            ->setParameter('templateId', $templateId)
            ->setParameter('accountId', $account->getId(), 'uuid')
            ->setParameter('actions', GuidelineReviewEvent::REPORT_ACTIONS)
            ->orderBy('e.occurredAt', 'DESC')
            // `occurredAt` truncates to whole seconds (TIMESTAMP(0)), so events
            // created within the same second tie — the id (UUIDv7, time-ordered)
            // breaks the tie deterministically in the same direction.
            ->addOrderBy('e.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * Whether any field-level content change (a value edit or an explicit
     * un-review) happened after the given event — used to tell whether a report
     * approval is still current. Comparing by id rather than occurredAt avoids
     * same-second ties (occurredAt truncates to whole seconds; id is a UUIDv7,
     * which is time-ordered and compares correctly as raw bytes in Postgres).
     */
    public function hasFieldChangeSince(string $resourceType, Uuid $resourceId, string $templateId, Account $account, Uuid $sinceEventId): bool
    {
        $count = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.resourceType = :resourceType')
            ->andWhere('e.resourceId = :resourceId')
            ->andWhere('e.templateId = :templateId')
            ->andWhere('IDENTITY(e.account) = :accountId')
            ->andWhere('e.action IN (:actions)')
            ->andWhere('e.id > :sinceEventId')
            ->setParameter('resourceType', $resourceType)
            ->setParameter('resourceId', $resourceId, 'uuid')
            ->setParameter('templateId', $templateId)
            ->setParameter('accountId', $account->getId(), 'uuid')
            ->setParameter('actions', [GuidelineReviewEvent::ACTION_FIELD_VALUE_CHANGED, GuidelineReviewEvent::ACTION_FIELD_UNREVIEWED])
            ->setParameter('sinceEventId', $sinceEventId, 'uuid')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return $count > 0;
    }
}
