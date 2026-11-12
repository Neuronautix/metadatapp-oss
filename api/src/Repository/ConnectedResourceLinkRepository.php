<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\ConnectedResourceLink;
use App\Entity\Experiment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConnectedResourceLink>
 */
final class ConnectedResourceLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConnectedResourceLink::class);
    }

    /**
     * @return list<ConnectedResourceLink>
     */
    public function findForExperiment(Experiment $experiment): array
    {
        return $this->createQueryBuilder('crl')
            ->andWhere('crl.experiment = :experiment')
            ->andWhere('crl.account = :account')
            ->setParameter('experiment', $experiment)
            ->setParameter('account', $experiment->getAccount())
            ->orderBy('crl.provider', 'ASC')
            ->addOrderBy('crl.resourceType', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByProviderAndExternalId(Account $account, string $provider, string $externalId): ?ConnectedResourceLink
    {
        return $this->findOneBy([
            'account' => $account,
            'provider' => $provider,
            'externalId' => $externalId,
        ]);
    }
}
