<?php

declare(strict_types=1);

namespace App\Mcp\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Subject;
use App\Entity\User;
use App\Enum\HealthStatus;
use App\Enum\Sex;
use App\Enum\Species;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<Subject>
 */
final readonly class SubjectListToolProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @return list<Subject>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $args = \is_array($context['mcp_data'] ?? null) ? $context['mcp_data'] : [];
        $species = 'list_zebrafish' === $operation->getName() ? Species::Zebrafish : Species::Mouse;

        $qb = $this->entityManager->createQueryBuilder()
            ->select('subject')
            ->from(Subject::class, 'subject')
            ->andWhere('subject.account = :account')
            ->andWhere('subject.species = :species')
            ->setParameter('account', $user->getAccount())
            ->setParameter('species', $species)
            ->orderBy('subject.name', 'ASC')
            ->setMaxResults(30)
        ;

        if (\is_string($args['name'] ?? null) && '' !== trim($args['name'])) {
            $qb
                ->andWhere('LOWER(subject.name) LIKE :name')
                ->setParameter('name', '%' . mb_strtolower(trim($args['name'])) . '%')
            ;
        }

        if (\is_string($args['genotype'] ?? null) && '' !== trim($args['genotype'])) {
            $qb
                ->andWhere('LOWER(subject.genotype) LIKE :genotype')
                ->setParameter('genotype', '%' . mb_strtolower(trim($args['genotype'])) . '%')
            ;
        }

        if (\is_string($args['sex'] ?? null) && null !== $sex = Sex::tryFrom($args['sex'])) {
            $qb
                ->andWhere('subject.sex = :sex')
                ->setParameter('sex', $sex)
            ;
        }

        if (\is_string($args['healthStatus'] ?? null) && null !== $healthStatus = HealthStatus::tryFrom($args['healthStatus'])) {
            $qb
                ->andWhere('subject.healthStatus = :healthStatus')
                ->setParameter('healthStatus', $healthStatus)
            ;
        }

        if (\array_key_exists('isPregnant', $args) && \is_bool($args['isPregnant'])) {
            $qb
                ->andWhere('subject.isPregnant = :isPregnant')
                ->setParameter('isPregnant', $args['isPregnant'])
            ;
        }

        return $qb->getQuery()->getResult();
    }
}
