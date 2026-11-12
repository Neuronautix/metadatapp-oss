<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Osf;

use App\ConnectedApps\Apps\Osf\Client\OsfClient;
use App\ConnectedApps\Apps\Osf\Mapper\ExperimentMapper;
use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Enum\AppCode;
use App\Repository\ConnectedResourceLinkRepository;
use App\Repository\ExperimentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final class OsfService implements ConnectedAppServiceInterface, ConnectedAppFromInterface
{
    private const array SUPPORTED_ENTITIES = [
        Experiment::class,
    ];

    public function __construct(
        private readonly OsfClient $client,
        private readonly ExperimentMapper $mapper,
        private readonly EntityManagerInterface $em,
        private readonly ExperimentRepository $experimentRepository,
        private readonly ConnectedResourceLinkRepository $resourceLinkRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function supportsCode(AppCode $appCode): bool
    {
        return AppCode::Osf === $appCode;
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
            throw new \InvalidArgumentException(\sprintf('Entity class %s is not supported for OSF synchronization.', $entityClassname));
        }

        $synced = [];
        foreach ($this->client->listProjects($connectedApp) as $node) {
            $externalId = $this->extractExternalId($node);
            if (null === $externalId) {
                $this->logger->warning('Skipping OSF node without id during synchronization.', [
                    'connectedAppId' => $connectedApp->getId()?->toRfc4122(),
                ]);
                continue;
            }

            $experiment = $this->experimentRepository->findOneBy([
                'account' => $connectedApp->getAccount(),
                'externalId' => 'osf:' . $externalId,
            ]) ?? new Experiment();

            $this->mapper->mapNode($connectedApp, $node, $experiment);
            $this->em->persist($experiment);

            $resourceLink = $this->resourceLinkRepository->findOneByProviderAndExternalId($connectedApp->getAccount(), 'osf', 'osf:' . $externalId)
                ?? new \App\Entity\ConnectedResourceLink();
            $this->mapper->mapResourceLink($connectedApp, $node, $experiment, $resourceLink);
            $this->em->persist($resourceLink);

            $synced[] = $experiment;
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

    /**
     * @param array<string, mixed> $node
     */
    private function extractExternalId(array $node): ?string
    {
        $id = $node['id'] ?? null;

        return \is_scalar($id) && '' !== trim((string) $id) ? trim((string) $id) : null;
    }
}
