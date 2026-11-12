<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Synchronizer;

use App\ConnectedApps\Apps\Elabftw\Client\Client;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentUpdateRequestDto;
use App\ConnectedApps\Apps\Elabftw\Mapper\ExperimentMapper;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Repository\ExperimentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class ExperimentSynchronizer
{
    public function __construct(
        private Client $client,
        private ExperimentRepository $experimentRepository,
        private ExperimentMapper $mapper,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function syncOneTo(ConnectedApp $connectedApp, Uuid $entityId): Experiment
    {
        $experiment = $this->experimentRepository->find($entityId);
        if (null === $experiment) {
            throw new \InvalidArgumentException(\sprintf('Experiment with ID %s not found.', $entityId->toRfc4122()));
        }

        return $this->syncOneExperimentToElabftw($connectedApp, $experiment);
    }

    public function syncAllTo(ConnectedApp $connectedApp): void
    {
        $experiments = $this->experimentRepository->findBy(['user' => $connectedApp->getUser()]);
        if ([] === $experiments) {
            return;
        }

        foreach ($experiments as $experiment) {
            try {
                $result = $this->syncOneExperimentToElabftw($connectedApp, $experiment);
                $this->logger->info('Experiment synchronized to ElabFTW', [
                    'experiment_id' => $experiment->getId()?->toString(),
                    'external_id' => $experiment->getElaftwExternalId(),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('Experiment synchronization to ElabFTW failed', [
                    'experiment_id' => $experiment->getId()?->toString(),
                    'external_id' => $experiment->getElaftwExternalId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function syncOneFrom(ConnectedApp $connectedApp, Uuid $experimentId): void
    {
        $experiment = $this->experimentRepository->find($experimentId);
        if (!$experiment) {
            throw new \InvalidArgumentException('Experiment not found.');
        }

        $externalId = $experiment->getElaftwExternalId();
        if (!$externalId) {
            $this->logger->warning('Cannot sync from ElabFTW: Experiment has no eLabFTW External ID.', ['id' => $experimentId]);

            return;
        }

        try {
            $dto = $this->client->experiments($connectedApp)->get((int) $externalId);
            $this->mapper->mapToInternal($dto, $experiment);

            $this->logger->info('Experiment synchronized FROM ElabFTW', [
                'experiment_id' => $experiment->getId()?->toString(),
                'external_id' => $externalId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Experiment synchronization FROM ElabFTW failed', [
                'experiment_id' => $experiment->getId()?->toString(),
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function syncAllFrom(ConnectedApp $connectedApp): array
    {
        $entities = [];

        try {
            $dtos = $this->client->experiments($connectedApp)->list();
            foreach ($dtos as $dto) {
                // Find local experiment by eLabFTW external ID
                $experiment = $this->experimentRepository->findOneBy(['elaftwExternalId' => (string) $dto->id]);

                if (null === $experiment) {
                    $experiment = new Experiment();
                    $experiment->setUser($connectedApp->getUser());
                    $experiment->setAccount($connectedApp->getUser()->getAccount());
                    $experiment->setStartAt(new \DateTime());
                    $this->em->persist($experiment);
                }

                $this->mapper->mapToInternal($dto, $experiment);
                $entities[] = $experiment;
            }

            $this->logger->info(\sprintf('Finished syncing %d experiments FROM ElabFTW', \count($dtos)));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync all experiments from ElabFTW', [
                'error' => $e->getMessage(),
            ]);
        }

        return $entities;
    }

    private function syncOneExperimentToElabftw(ConnectedApp $connectedApp, Experiment $experiment): Experiment
    {
        $dto = $this->mapper->mapToExternal($experiment);
        if (null === $experiment->getElaftwExternalId()) {
            $createdDto = $this->client->experiments($connectedApp)->create($dto);
            $experiment->setElaftwExternalId((string) $createdDto->id);
            $experiment->setElabftwMetadata($createdDto->metadata);
        } else {
            if (!$dto instanceof ExperimentUpdateRequestDto) {
                throw new \LogicException('Expected ExperimentUpdateRequestDto.');
            }
            $updatedDto = $this->client->experiments($connectedApp)->update((int) $experiment->getElaftwExternalId(), $dto);
            $experiment->setElabftwMetadata($updatedDto->metadata);
        }

        return $experiment;
    }
}
