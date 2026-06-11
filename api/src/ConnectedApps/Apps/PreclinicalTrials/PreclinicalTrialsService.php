<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\PreclinicalTrials;

use App\ConnectedApps\Apps\PreclinicalTrials\Client\PreclinicalTrialsClient;
use App\ConnectedApps\Apps\PreclinicalTrials\Mapper\ProtocolMapper;
use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;
use App\Entity\ConnectedApp;
use App\Entity\Project;
use App\Enum\AppCode;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class PreclinicalTrialsService implements ConnectedAppServiceInterface, ConnectedAppFromInterface
{
    private const array SUPPORTED_ENTITIES = [
        Project::class,
    ];

    public function __construct(
        private readonly PreclinicalTrialsClient $client,
        private readonly ProtocolMapper $mapper,
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supportsCode(AppCode $appCode): bool
    {
        return AppCode::PreclinicalTrials === $appCode;
    }

    public static function getSupportedEntitiesSyncFrom(): array
    {
        return self::SUPPORTED_ENTITIES;
    }

    public function supportsEntitySyncTo(string $entityClassname): bool
    {
        return false;
    }

    public function supportsEntitySyncFrom(string $entityClassname): bool
    {
        return \in_array($entityClassname, self::SUPPORTED_ENTITIES, true);
    }

    public function syncAllFromExternal(ConnectedApp $connectedApp, string $entityClassname): ?array
    {
        if (!$this->supportsEntitySyncFrom($entityClassname)) {
            throw new \InvalidArgumentException(\sprintf('Entity class %s is not supported for PreclinicalTrials.eu synchronization.', $entityClassname));
        }

        $synced = [];
        foreach ($this->client->listProtocols($connectedApp) as $protocol) {
            $protocolId = $this->mapper->extractProtocolId($protocol);
            if (null === $protocolId) {
                $this->logger->warning('Skipping PreclinicalTrials.eu protocol without id.', [
                    'connectedAppId' => $connectedApp->getId()?->toRfc4122(),
                ]);
                continue;
            }

            try {
                $details = $this->client->getProtocol($connectedApp, $protocolId);
            } catch (\Throwable) {
                $details = [];
            }
            $source = [] === $details ? $protocol : array_replace($protocol, $details);
            $project = $this->projectRepository->findOneBy([
                'account' => $connectedApp->getAccount(),
                'externalId' => 'preclinicaltrials:' . $protocolId,
            ]) ?? new Project();

            $this->mapper->mapToProject($connectedApp, $source, $project);
            $this->em->persist($project);
            $synced[] = $project;
        }

        $connectedApp->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return [] === $synced ? null : $synced;
    }

    public function syncOneFromExternal(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void
    {
        $this->syncAllFromExternal($connectedApp, $entityClassname);
    }

    public function fullSynchronizationFromExternal(ConnectedApp $connectedApp): void
    {
        foreach (self::SUPPORTED_ENTITIES as $entityClassname) {
            $this->syncAllFromExternal($connectedApp, $entityClassname);
        }
    }
}
