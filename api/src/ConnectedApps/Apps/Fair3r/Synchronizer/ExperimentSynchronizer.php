<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Synchronizer;

use App\ConnectedApps\Apps\Fair3r\Client\Client;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreCreateRequestDto;
use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatastoreUpsertRequestDto;
use App\ConnectedApps\Apps\Fair3r\Mapper\ExperimentMapper;
use App\Entity\Experiment;
use App\Entity\Project;
use Psr\Log\LoggerInterface;

/**
 * Synchronizer to pull data from SoftMouse via the SoftMouseClientService
 * and persist it locally using repository services.
 */
readonly class ExperimentSynchronizer
{
    public function __construct(
        private Client $fair3rClient,
        private ExperimentMapper $mapper,
        private LoggerInterface $logger,
    ) {
    }

    public function syncExperimentToDatastoreForProject(Project $project): void
    {
        foreach ($project->getExperiments() as $experiment) {
            $this->syncOneExperimentToDatastore($experiment);
        }
    }

    public function syncExperimentToDatastore(Experiment $experiment): void
    {
        $this->syncOneExperimentToDatastore($experiment);
    }

    public function syncOneExperimentToDatastore(Experiment $experiment): void
    {
        try {
            $requestDto = $this->mapper->mapToExternal($experiment);
            if (null === $requestDto->records) {
                // it should be validate before starting the sync of the experiment
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
            $datasetId = $response->resource_id;
            $experiment->setFair3rExternalId($datasetId);
        } else {
            if (!$requestDto instanceof DatastoreUpsertRequestDto) {
                throw new \LogicException('Expected DatastoreUpsertRequestDto.');
            }
            $this->fair3rClient->datastores()->upsert($requestDto);
        }
    }
}
