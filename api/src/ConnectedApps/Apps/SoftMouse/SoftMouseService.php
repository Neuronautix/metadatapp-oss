<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse;

use App\ConnectedApps\Apps\SoftMouse\Error\SoftMouseSyncError;
use App\ConnectedApps\Apps\SoftMouse\Synchronizer\CageSynchronizer;
use App\ConnectedApps\Apps\SoftMouse\Synchronizer\ExperimentSynchronizer;
use App\ConnectedApps\Apps\SoftMouse\Synchronizer\SubjectSynchronizer;
use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Messenger\Message\SyncOneToAppMessage;
use App\Entity\Cage;
use App\Entity\ConnectedApp;
use App\Entity\ConnectedAppEntityInterface;
use App\Entity\Experiment;
use App\Entity\Subject;
use App\Enum\AppCode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * SoftMouse is a colony manager app.
 * Service to handle synchronization of SoftMouse data into MAPP entities.
 *
 * Entity Mappings
 * SoftMouse entitiy (id) -> Mapp Entity (softmouseExternalId)
 * Project (studyData->projectName) -> Project (softmouseExternalId)
 * Study/StudyData (code) -> Experiment (softmouseExternalId)
 * Task -> Procedure
 * Animals -> Subjects
 * Cage -> Cage
 */
readonly class SoftMouseService implements ConnectedAppServiceInterface
{
    private const array SUPPORTED_SYNC_FROM_ENTITIES = [
        Cage::class,
        Subject::class,
        Experiment::class,
    ];

    private const array SUPPORTED_SYNC_TO_ENTITIES = [];

    public function __construct(
        private SubjectSynchronizer $subjectSynchronizer,
        private CageSynchronizer $cageSynchronizer,
        private ExperimentSynchronizer $experimentSynchronizer,
        private MessageBusInterface $messageBus,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function supportsCode(AppCode $appCode): bool
    {
        return AppCode::SoftMouse === $appCode;
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
        return false;
        //        return \in_array($entityClassname, self::SUPPORTED_SYNC_TO_ENTITIES, true);
    }

    public function supportsEntitySyncFrom(string $entityClassname): bool
    {
        return \in_array($entityClassname, self::SUPPORTED_SYNC_FROM_ENTITIES, true);
    }

    /**
     * @throws \Exception
     */
    public function syncOneFromExternal(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void
    {
        /** @var Cage|Subject|Experiment|null $entity */
        $entity = match ($entityClassname) {
            Experiment::class => $this->experimentSynchronizer->syncOneFrom($connectedApp, $entityId),
            Cage::class => $this->cageSynchronizer->syncOneFrom($connectedApp, $entityId),
            Subject::class => $this->subjectSynchronizer->syncOneFrom($connectedApp, $entityId),
            default => throw new \Exception(\sprintf('Entity classname "%s" not implemented', $entityClassname)),
        };

        $connectedApp->setLastSyncAt(new \DateTime());
        $this->em->flush();

        $userId = $connectedApp->getuser()->getId();
        if (null === $userId) {
            throw new \InvalidArgumentException('ConnectedApp user ID is null.');
        }

        if (null === $entity) {
            throw new \InvalidArgumentException(\sprintf('Entity of class %s with ID %s not found.', $entityClassname, $entityId->toRfc4122()));
        }
        $entityIdValue = $entity->getId();
        if (null === $entityIdValue) {
            throw new \RuntimeException('Entity ID is null.');
        }
        $this->messageBus->dispatch(
            new SyncOneToAppMessage(
                $userId,
                $entityClassname,
                $entityIdValue,
                $connectedApp->getId(),
            )
        );
    }

    /**
     * @throws \Exception
     */
    public function syncAllFromExternal(ConnectedApp $connectedApp, string $entityClassname): array
    {
        /** @var array<ConnectedAppEntityInterface> $entities */
        $entities =
            match ($entityClassname) {
                Experiment::class => $this->experimentSynchronizer->syncAllFrom($connectedApp),
                Cage::class => $this->cageSynchronizer->syncAllFrom($connectedApp),
                default => throw new \Exception(\sprintf('Entity classname "%s" not implemented', $entityClassname)),
            };

        $this->em->flush();

        return $entities;
    }

    public function sync(ConnectedApp $connectedApp): void
    {
        $this->fullSynchronizationFromExternal($connectedApp);
    }

    public function fullSynchronizationFromExternal(ConnectedApp $connectedApp): void
    {
        try {
            $this->experimentSynchronizer->syncAllFrom($connectedApp);
            $this->cageSynchronizer->syncCages($connectedApp);
            $this->subjectSynchronizer->syncSubjects($connectedApp);
            $connectedApp->setLastSyncAt(new \DateTime());
            $this->em->flush();
            $this->logger->info('[Apps][SoftMouse] Sync completed successfully', [
                'connectedAppId' => $connectedApp->getId()?->toString(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[Apps][SoftMouse] Sync failed', [
                'connectedAppId' => $connectedApp->getId(),
                'error' => $e->getMessage(),
            ]);
            throw new SoftMouseSyncError('Full synchronization failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function syncAllFrom(ConnectedApp $connectedApp): array
    {
        return $this->syncAllFromExternal($connectedApp, Subject::class);
    }
}
