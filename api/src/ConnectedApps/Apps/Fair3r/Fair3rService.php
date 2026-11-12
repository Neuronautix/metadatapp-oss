<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r;

use App\ConnectedApps\Apps\Fair3r\Synchronizer\ExperimentSynchronizer;
use App\ConnectedApps\Apps\Fair3r\Synchronizer\ProjectSynchronizer;
use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Shared\ConnectedAppToInterface;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\Project;
use App\Enum\AppCode;
use App\Repository\ExperimentRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class Fair3rService implements ConnectedAppToInterface, ConnectedAppServiceInterface
{
    private const array SUPPORTED_SYNC_FROM_ENTITIES = [
    ];

    private const array SUPPORTED_SYNC_TO_ENTITIES = [
        Experiment::class,
        //        Subject::class, // Subject sync to Fair3R is disabled, because we always sync the whole experiment when a subject is updated and when we update dozens of subjects at once, it creates too many requests for nothing.
    ];

    public function __construct(
        private readonly ExperimentSynchronizer $experimentSynchronizer,
        private readonly ProjectSynchronizer $projectSynchronizer,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly ProjectRepository $projectRepository,
        private readonly ExperimentRepository $experimentRepository,
    ) {
    }

    /**
     * @return string[] List of entity class names that can be synchronized to Fair3R
     */
    public static function getSupportedEntitiesSyncTo(): array
    {
        return self::SUPPORTED_SYNC_TO_ENTITIES;
    }

    /**
     * @return string[] List of entity class names that can be synchronized from Fair3R
     */
    public static function getSupportedEntitiesSyncFrom(): array
    {
        return self::SUPPORTED_SYNC_FROM_ENTITIES;
    }

    public function supportsEntitySyncTo(string $entityClassname): bool
    {
        return \in_array($entityClassname, self::SUPPORTED_SYNC_TO_ENTITIES, true);
    }

    public function supportsEntitySyncFrom(string $entityClassname): bool
    {
        return false; // Reading from Fair3r is not supported yet.
    }

    public function syncOneFrom(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void
    {
        // Reading from Fair3r is not supported yet.
    }

    public function synchronizeAllFrom(ConnectedApp $connectedApp, string $entityClassname): void
    {
        // Reading from Fair3r is not supported yet.
    }

    /**
     * @throws \Exception
     */
    public function syncOneToExternal(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void
    {
        match ($entityClassname) {
            Project::class => $this->projectSynchronizer->syncProjectToDataset($connectedApp, $entityId),
            Experiment::class => $this->getToDatastore($entityId),
            default => throw new \InvalidArgumentException(\sprintf('Entity class %s is not supported for synchronization to Fair3R.', $entityClassname)),
        };
        $this->em->flush();
        $this->logger->info('[Apps][Fair3r] Sync completed successfully', [
            'entityClassname' => $entityClassname,
            'connectedAppId' => $connectedApp->getId()?->toString(),
        ]);
    }

    public function syncAllToExternal(ConnectedApp $connectedApp, string $entityClassname): void
    {
        $user = $connectedApp->getUser();

        $projects = $this->projectRepository->createQueryBuilder('p')
            ->where('p.user = :user')
            ->andWhere('p.fair3rExternalId IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult()
        ;

        $count = \count($projects);
        if (0 === $count) {
            $this->logger->warning('You need to set the fair3r datasetId in the project external id to make this sync possible, sorry, but for now that\'s the way it goes.');

            return;
        }
        $this->logger->info(\sprintf('[Apps][Fair3r] %d projects found to sync', $count));
        foreach ($projects as $project) {
            try {
                $this->logger->info('[Apps][Fair3r] Starting FAIR3R sync for project', [
                    'projectId' => $project->getId()->toString(),
                    'name' => $project->getName(),
                    'fair3rExternalId' => $project->getFair3rExternalId(),
                    'userId' => $user->getId()?->toString(),
                ]);

                $this->experimentSynchronizer->syncExperimentToDatastoreForProject($project);

                $this->em->flush();

                $this->logger->info('[Apps][Fair3r] FAIR3R sync completed', [
                    'projectId' => $project->getId(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('[Apps][Fair3r] FAIR3R sync failed', [
                    'projectId' => $project->getId(),
                    'connectedAppId' => $connectedApp->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function sync(ConnectedApp $connectedApp): void
    {
        $this->syncAllToExternal($connectedApp, Experiment::class);
    }

    public function syncExperiment(ConnectedApp $connectedApp, Project $project): void
    {
        $this->experimentSynchronizer->syncExperimentToDatastoreForProject($project);
        $this->em->flush();

        $this->logger->info('[Apps][Fair3r] Project Sync completed successfully', [
            'projectId' => $project->getId()?->toString(),
            'connectedAppId' => $connectedApp->getId()?->toString(),
        ]);
    }

    public function supportsCode(AppCode $appCode): bool
    {
        return AppCode::Fair3r === $appCode;
    }

    protected function getToDatastore(Uuid $entityId): void
    {
        $experiment = $this->experimentRepository->find($entityId);
        if (null === $experiment) {
            throw new \InvalidArgumentException(\sprintf('Experiment with ID %s not found.', $entityId->toRfc4122()));
        }
        $this->experimentSynchronizer->syncOneExperimentToDatastore($experiment);
    }
}
