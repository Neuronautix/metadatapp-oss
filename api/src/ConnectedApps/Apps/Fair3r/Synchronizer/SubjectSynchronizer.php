<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Synchronizer;

use App\Entity\ConnectedApp;
use App\Repository\SubjectRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class SubjectSynchronizer
{
    public function __construct(
        private SubjectRepository $subjectRepository,
        private ExperimentSynchronizer $experimentSynchronizer,
        private LoggerInterface $logger,
    ) {
    }

    // If a subject is updated,in fact for fair3r we want to synchronize the whole experiment
    public function syncOneTo(ConnectedApp $connectedApp, Uuid $entityId): void
    {
        $subject = $this->subjectRepository->find($entityId);
        if (null === $subject) {
            throw new \InvalidArgumentException(\sprintf('Experiment with ID %s not found.', $entityId->toRfc4122()));
        }
        // array unque experiments

        $experiments = $subject->getProcedures()->map(static fn ($p) => $p->getExperiment());
        $experiments = array_unique($experiments->toArray(), \SORT_REGULAR);
        foreach ($experiments as $experiment) {
            $this->experimentSynchronizer->syncExperimentToDatastore($experiment);
        }
    }

    public function syncAllTo(ConnectedApp $connectedApp): void
    {
        $subjects = $this->subjectRepository->findBy(['account' => $connectedApp->getAccount()]);
        $experiments = [];
        foreach ($subjects as $subject) {
            $subjectExperiments = $subject->getProcedures()->map(static fn ($p) => $p->getExperiment())->toArray();
            $experiments = array_merge($experiments, $subjectExperiments);
        }
        foreach (array_unique($experiments, \SORT_REGULAR) as $experiment) {
            try {
                $this->experimentSynchronizer->syncExperimentToDatastore($experiment);
            } catch (\Throwable $e) {
                $this->logger->error('[APPS][FAIR3R] Subject synchronizer: Experiment synchronization to Fair3R failed', [
                    'experiment_id' => $experiment->getId()?->toString(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
