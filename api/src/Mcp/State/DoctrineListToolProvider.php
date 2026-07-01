<?php

declare(strict_types=1);

namespace App\Mcp\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Cage;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\BeddingType;
use App\Enum\CageFormat;
use App\Enum\CageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProviderInterface<object>
 */
final readonly class DoctrineListToolProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    /**
     * @return list<object>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $args = \is_array($context['mcp_data'] ?? null) ? $context['mcp_data'] : [];

        return match ($operation->getName()) {
            'list_cages' => $this->listCages($user, $args),
            'list_studies', 'list_experiments' => $this->listExperiments($user, $args),
            'list_investigations', 'list_projects' => $this->listProjects($user, $args),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return list<Cage>
     */
    private function listCages(User $user, array $args): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('cage')
            ->from(Cage::class, 'cage')
            ->andWhere('cage.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('cage.id', 'ASC')
            ->setMaxResults(30)
        ;

        if (\is_string($args['type'] ?? null) && null !== $type = CageType::tryFrom($args['type'])) {
            $qb->andWhere('cage.type = :type')->setParameter('type', $type);
        }

        if (\is_string($args['format'] ?? null) && null !== $format = CageFormat::tryFrom($args['format'])) {
            $qb->andWhere('cage.format = :format')->setParameter('format', $format);
        }

        if (\is_string($args['beddingType'] ?? null) && null !== $beddingType = BeddingType::tryFrom($args['beddingType'])) {
            $qb->andWhere('cage.beddingType = :beddingType')->setParameter('beddingType', $beddingType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return list<Experiment>
     */
    private function listExperiments(User $user, array $args): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('experiment')
            ->from(Experiment::class, 'experiment')
            ->andWhere('experiment.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('experiment.name', 'ASC')
            ->setMaxResults(30)
        ;

        if (\is_string($args['nameContains'] ?? null) && '' !== trim($args['nameContains'])) {
            $qb
                ->andWhere('LOWER(experiment.name) LIKE :name')
                ->setParameter('name', '%' . mb_strtolower(trim($args['nameContains'])) . '%')
            ;
        }

        if (\is_string($args['goalContainsCaseSensitive'] ?? null) && '' !== trim($args['goalContainsCaseSensitive'])) {
            $qb
                ->andWhere('experiment.goal LIKE :goal')
                ->setParameter('goal', '%' . trim($args['goalContainsCaseSensitive']) . '%')
            ;
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return list<Project>
     */
    private function listProjects(User $user, array $args): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('project')
            ->from(Project::class, 'project')
            ->andWhere('project.account = :account')
            ->setParameter('account', $user->getAccount())
            ->orderBy('project.name', 'ASC')
            ->setMaxResults(30)
        ;

        if (\is_string($args['name'] ?? null) && '' !== trim($args['name'])) {
            $qb
                ->andWhere('LOWER(project.name) LIKE :name')
                ->setParameter('name', '%' . mb_strtolower(trim($args['name'])) . '%')
            ;
        }

        return $qb->getQuery()->getResult();
    }
}
