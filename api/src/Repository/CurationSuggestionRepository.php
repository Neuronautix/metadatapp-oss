<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CurationSuggestion;
use App\Entity\Subject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CurationSuggestion>
 */
class CurationSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CurationSuggestion::class);
    }

    /**
     * @return array<CurationSuggestion>
     */
    public function findForSubject(Subject $subject): array
    {
        return $this->createQueryBuilder('suggestion')
            ->andWhere('suggestion.subject = :subject')
            ->setParameter('subject', $subject)
            ->orderBy('suggestion.createdAt', 'DESC')
            ->addOrderBy('suggestion.id', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }
}
