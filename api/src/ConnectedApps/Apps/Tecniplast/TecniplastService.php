<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast;

use App\ConnectedApps\Apps\Tecniplast\Client\TecniplastDto;
use App\ConnectedApps\Apps\Tecniplast\Client\TecniplastHttpClientInterface;
use App\ConnectedApps\Apps\Tecniplast\Mapper\MetricMapper;
use App\ConnectedApps\ConnectedAppServiceInterface;
use App\ConnectedApps\Shared\ConnectedAppFromInterface;
use App\Entity\ConnectedApp;
use App\Entity\Variable;
use App\Enum\AppCode;
use App\Repository\VariableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class TecniplastService implements ConnectedAppServiceInterface, ConnectedAppFromInterface
{
    private const array SUPPORTED_ENTITIES = [
        Variable::class,
    ];

    public function __construct(
        private TecniplastHttpClientInterface $client,
        private MetricMapper $metricMapper,
        private EntityManagerInterface $em,
        private VariableRepository $variableRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function supportsCode(AppCode $appCode): bool
    {
        return AppCode::Tecniplast === $appCode;
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
        $token = $connectedApp->getToken();
        if (!$token) {
            $this->logger->error('Tecniplast access token is missing for connected app {id}', ['id' => $connectedApp->getId()]);
            throw new \RuntimeException('Tecniplast access token is missing for connected app.');
        }

        $this->logger->info('Starting Tecniplast synchronization for {entity} from app {id}', [
            'entity' => $entityClassname,
            'id' => $connectedApp->getId(),
        ]);

        $entities = [];
        try {
            if (Variable::class === $entityClassname) {
                // We sync metrics as Variables
                $dtos = $this->client->getMetrics($token);
                foreach ($dtos as $dtoData) {
                    $dto = new TecniplastDto($dtoData);

                    // Match by tecniplastExternalId, fallback to name, or create new
                    $variable = $this->variableRepository->findOneBy(['tecniplastExternalId' => (string) $dtoData['id']])
                        ?? $this->variableRepository->findOneBy(['name' => $dtoData['description'] ?? $dtoData['id'], 'account' => $connectedApp->getAccount()])
                        ?? new Variable();

                    $this->metricMapper->mapFromExternal($dto, $variable);
                    $variable->setAccount($connectedApp->getAccount());

                    $this->em->persist($variable);
                    $entities[] = $variable;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Tecniplast synchronization failed for app {id}: {message}', [
                'id' => $connectedApp->getId(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e;
        }

        $this->em->flush();

        // Update the last sync time
        $connectedApp->setLastSyncAt(new \DateTime());
        $this->em->flush();

        $this->logger->info('Tecniplast synchronization completed for {entity}. Synced {count} items.', [
            'entity' => $entityClassname,
            'count' => \count($entities),
        ]);

        return [] === $entities ? null : $entities;
    }

    public function syncOneFromExternal(ConnectedApp $connectedApp, string $entityClassname, Uuid $entityId): void
    {
        // For now, we only support syncing all metrics.
        $this->syncAllFromExternal($connectedApp, $entityClassname);
    }

    public function fullSynchronizationFromExternal(ConnectedApp $connectedApp): void
    {
        foreach (self::SUPPORTED_ENTITIES as $entityClassname) {
            $this->syncAllFromExternal($connectedApp, $entityClassname);
        }
    }
}
