<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\ImportedReference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportedReference>
 */
class ImportedReferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportedReference::class);
    }

    public function findByAccountAndReferenceId(Account $account, string $referenceId): ?ImportedReference
    {
        return $this->findOneBy(['account' => $account, 'referenceId' => $referenceId]);
    }

    /**
     * An account's most recent library items, capped — used by the agentic
     * `list_my_references` tool.
     *
     * @return list<ImportedReference>
     */
    public function findByAccount(Account $account, int $limit = 50): array
    {
        return $this->findBy(
            ['account' => $account],
            ['importedAt' => 'DESC'],
            max(1, min($limit, 200)),
        );
    }

    /**
     * All of an account's library items, newest first. Used by the deterministic
     * (pgvector-free) similarity path, which loads candidates and ranks them in PHP.
     * The optional `pgvector` nearest-neighbour query is a future scale path; with a
     * per-account library this in-memory scan is comfortably sufficient.
     *
     * @return list<ImportedReference>
     */
    public function findAllForAccount(Account $account): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.account = :account')
            ->setParameter('account', $account)
            ->orderBy('r.importedAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
