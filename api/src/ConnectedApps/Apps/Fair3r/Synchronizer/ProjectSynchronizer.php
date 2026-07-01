<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Synchronizer;

use App\ConnectedApps\Apps\Fair3r\Client\Client;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreCreateRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreUpsertRequestDto;
use App\ConnectedApps\Apps\Fair3r\Mapper\ExperimentMapper;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Repository\ExperimentRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Synchronizer to pull data from SoftMouse via the SoftMouseClientService
 * and persist it locally using repository services.
 */
readonly class ProjectSynchronizer
{
    public function __construct(
        private Client $fair3rClient,
        private ExperimentRepository $experimentRepository,
        private ExperimentMapper $mapper,
        private LoggerInterface $logger,
    ) {
    }

    public function syncProjectToDataset(ConnectedApp $connectedApp, Uuid $entityId): void
    {
        // Fetch experiment directly by id (method name suggests project sync but logic uses experiment)
        $experiment = $this->experimentRepository->find($entityId);
        if (null === $experiment) {
            throw new \InvalidArgumentException(\sprintf('Experiment with ID %s not found.', $entityId->toRfc4122()));
        }
        if (null === $experiment->getProject()?->getFair3rExternalId()) {
            $this->logger->info('Experiment not linked to a Fair3R project, skipping synchronization', [
                'experiment_id' => $experiment->getId()?->toString(),
            ]);

            return;
        }

        $this->syncOneExperimentToDatastore($experiment);
    }

    public function syncAllTo(ConnectedApp $connectedApp): void
    {
        $experiments = $this->experimentRepository->findBy(['user' => $connectedApp->getUser()]);
        foreach ($experiments as $experiment) {
            try {
                $this->syncOneExperimentToDatastore($experiment);
                $this->logger->info('Experiment synchronized to Fair3R', [
                    'experiment_id' => $experiment->getId()?->toString(),
                    'dataset_id' => $experiment->getDatasetId(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Experiment synchronization to Fair3R failed', [
                    'experiment_id' => $experiment->getId()?->toString(),
                    'dataset_id' => $experiment->getDatasetId(),
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }
    }

    private function syncOneExperimentToDatastore(Experiment $experiment): void
    {
        try {
            $requestDto = $this->mapper->mapToExternal($experiment);
            if (null === $requestDto->records) {
                throw new \RuntimeException('Experiment records are null, cannot sync to Fair3R.');
            }
        } catch (\Throwable $t) {
            $this->logger->error('Mapping Experiment to DatastoreCreateRequestDto failure', ['error' => $t->getMessage()]);

            return;
        }

        $this->logger->debug('push Experiment to Fair3R Datastore', [
            'requestDto' => $requestDto,
        ]);
        if (!$experiment->getFair3rExternalId()) {
            if (!$requestDto instanceof DatastoreCreateRequestDto) {
                throw new \LogicException('Expected DatastoreCreateRequestDto.');
            }
            $response = $this->fair3rClient->datastores()->create($requestDto);
            $datastoreId = $response->resource_id;
            $experiment->setFair3rExternalId($datastoreId);
        } else {
            if (!$requestDto instanceof DatastoreUpsertRequestDto) {
                throw new \LogicException('Expected DatastoreUpsertRequestDto.');
            }
            $this->fair3rClient->datastores()->upsert($requestDto);
        }
    }
}
