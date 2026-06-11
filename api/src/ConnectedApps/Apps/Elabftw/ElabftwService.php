<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw;

use App\ConnectedApps\Apps\Elabftw\Synchronizer\ExperimentSynchronizer;
use App\ConnectedApps\Apps\Elabftw\Synchronizer\SubjectSynchronizer;
use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;
use App\ConnectedApps\Shared\ConnectedAppToInterface;
use App\Entity\ConnectedApp;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\Experiment;
use App\Entity\Subject;
use App\Enum\AppCode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Service handling synchronization with Elabftw connected app.
 */
readonly class ElabftwService implements ConnectedAppFromInterface, ConnectedAppToInterface, ConnectedAppServiceInterface
{
    private const array SUPPORTED_SYNC_FROM_ENTITIES = [
        Experiment::class,
    ];

    private const array SUPPORTED_SYNC_TO_ENTITIES = [
        Experiment::class,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private ExperimentSynchronizer $experimentSynchronizer,
        private SubjectSynchronizer $subjectSynchronizer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return string[]
     */
    public static function getSupportedEntitiesSyncTo(): array
    {
        return self::SUPPORTED_SYNC_TO_ENTITIES;
    }

    /**
     * @return string[]
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
        return \in_array($entityClassname, self::SUPPORTED_SYNC_FROM_ENTITIES, true);
    }

    public function syncOneFrom(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void
    {
        match ($entityClassname) {
            Experiment::class => $this->experimentSynchronizer->syncOneFrom($connectedApp, $entityId),
            Subject::class => $this->subjectSynchronizer->syncOneFrom($connectedApp, $entityId),
            default => throw new \Exception(\sprintf('Entity classname "%s" not implemented for syncOneFrom', $entityClassname)),
        };
        $this->em->flush();
    }

    public function synchronizeAllFrom(ConnectedApp $connectedApp, string $entityClassname): void
    {
        match ($entityClassname) {
            Experiment::class => $this->experimentSynchronizer->syncAllFrom($connectedApp),
            Subject::class => $this->subjectSynchronizer->syncAllFrom($connectedApp),
            default => throw new \Exception(\sprintf('Entity classname "%s" not implemented for synchronizeAllFrom', $entityClassname)),
        };
        $this->em->flush();
    }

    public function syncOneFromExternal(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void
    {
        $this->syncOneFrom($connectedApp, $entityClassname, $entityId);
        $connectedApp->setLastSyncAt(new \DateTime());
        $this->em->flush();
    }

    public function syncAllFromExternal(ConnectedApp $connectedApp, string $entityClassname): ?array
    {
        $entities = match ($entityClassname) {
            Experiment::class => $this->experimentSynchronizer->syncAllFrom($connectedApp),
            Subject::class => $this->subjectSynchronizer->syncAllFrom($connectedApp),
            default => throw new \Exception(\sprintf('Entity classname "%s" not implemented for syncAllFromExternal', $entityClassname)),
        };

        $connectedApp->setLastSyncAt(new \DateTime());
        $this->em->flush();

        return [] === $entities ? null : array_filter($entities, static fn (mixed $entity): bool => $entity instanceof ConnectedAppEntityInterface);
    }

    public function fullSynchronizationFromExternal(ConnectedApp $connectedApp): void
    {
        foreach (self::SUPPORTED_SYNC_FROM_ENTITIES as $entityClassname) {
            $this->syncAllFromExternal($connectedApp, $entityClassname);
        }
    }

    /**
     * @throws \Exception
     */
    public function syncOneToExternal(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void
    {
        // Synchronizing a single entity is not implemented.
        $this->logger->info('syncOneToExternal not implemented', [
            'entityClassname' => $entityClassname,
            'entityId' => $entityId,
        ]);

        match ($entityClassname) {
            Experiment::class => $this->experimentSynchronizer->syncOneTo($connectedApp, $entityId),
            Subject::class => $this->subjectSynchronizer->syncOneTo($connectedApp, $entityId),
            default => throw new \Exception(\sprintf('Entity classname "%s" not implemented', $entityClassname)),
        };

        $this->em->flush();
    }

    /**
     * @throws \Exception
     */
    public function syncAllToExternal(ConnectedApp $connectedApp, string $entityClassname): void
    {
        $this->logger->info('Starting synchronization to Elabftw', [
            'entityClassname' => $entityClassname,
            'connectedAppId' => $connectedApp->getId(),
        ]);
        match ($entityClassname) {
            Experiment::class => $this->experimentSynchronizer->syncAllTo($connectedApp),
            Subject::class => $this->subjectSynchronizer->syncAllTo($connectedApp),
            default => throw new \Exception(\sprintf('Entity classname "%s" not implemented', $entityClassname)),
        };

        $connectedApp->setLastSyncAt(new \DateTime());
        $this->em->flush();
    }

    public function supportsCode(AppCode $appCode): bool
    {
        return AppCode::Elabftw === $appCode;
    }
}
