<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Synchronizer;

use App\ConnectedApps\Apps\Elabftw\Client\Client;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\CreateItemDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\UpdateItemDto;
use App\ConnectedApps\Apps\Elabftw\DataTransformer\ElabftwItemToMdtaMouseTransformer;
use App\ConnectedApps\Apps\Elabftw\DataTransformer\MouseItemMetadataTransformer;
use App\ConnectedApps\Apps\Elabftw\Dto\MouseMetadataDto;
use App\Entity\ConnectedApp;
use App\Entity\Subject;
use App\Repository\SubjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

readonly class SubjectSynchronizer
{
    // TODO: This should be configurable.
    private const int MOUSE_CATEGORY_ID = 1;

    public function __construct(
        private Client $client,
        private SubjectRepository $subjectRepository,
        private MouseItemMetadataTransformer $metadataTransformer,
        private ElabftwItemToMdtaMouseTransformer $elabftwToMdtaTransformer,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function syncOneTo(ConnectedApp $connectedApp, Uuid $entityId): Subject
    {
        $subject = $this->subjectRepository->find($entityId);
        if (null === $subject) {
            throw new \InvalidArgumentException(\sprintf('Subject with ID %s not found.', $entityId->toRfc4122()));
        }

        return $this->syncOneSubjectToElabftw($connectedApp, $subject);
    }

    public function syncAllTo(ConnectedApp $connectedApp): void
    {
        // Currently, subjects are not filtered by user in the repository like experiments.
        // We'll sync all subjects the app has access to (usually via account).
        $subjects = $this->subjectRepository->findBy(['account' => $connectedApp->getUser()->getAccount()]);

        foreach ($subjects as $subject) {
            try {
                $this->syncOneSubjectToElabftw($connectedApp, $subject);
            } catch (\Throwable $e) {
                $this->logger->error('Subject synchronization to ElabFTW failed', [
                    'subject_id' => $subject->getId()?->toString(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function syncOneFrom(ConnectedApp $connectedApp, Uuid $subjectId): void
    {
        $subject = $this->subjectRepository->find($subjectId);
        if (!$subject) {
            return;
        }

        $externalId = $subject->getElaftwExternalId();
        if (!$externalId) {
            return;
        }

        try {
            $dto = $this->client->items($connectedApp)->get((int) $externalId);
            $metadataDto = new MouseMetadataDto();
            $metadataDto->extra_fields = $dto->metadata; // Assuming structure matches

            $this->elabftwToMdtaTransformer->hydrateSubjectWithExtrafields($subject, $metadataDto);

            $this->logger->info('Subject synchronized FROM ElabFTW', [
                'subject_id' => $subject->getId()?->toString(),
                'external_id' => $externalId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Subject synchronization FROM ElabFTW failed', [
                'subject_id' => $subject->getId()?->toString(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function syncAllFrom(ConnectedApp $connectedApp): array
    {
        $entities = [];

        try {
            $dtos = $this->client->items($connectedApp)->list();
            foreach ($dtos as $dto) {
                $subject = $this->subjectRepository->findOneBy(['elaftwExternalId' => (string) $dto->id]);

                if (null === $subject) {
                    $subject = new Subject();
                    $subject->setAccount($connectedApp->getUser()->getAccount());
                    // Species, Strain, Sex, etc. would be hydrated by transformer or need defaults
                    $this->em->persist($subject);
                }

                $metadataDto = new MouseMetadataDto();
                $metadataDto->extra_fields = $dto->metadata;
                $this->elabftwToMdtaTransformer->hydrateSubjectWithExtrafields($subject, $metadataDto);
                $entities[] = $subject;
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to sync all subjects from ElabFTW', ['error' => $e->getMessage()]);
        }

        return $entities;
    }

    private function syncOneSubjectToElabftw(ConnectedApp $connectedApp, Subject $subject): Subject
    {
        $extraFields = $this->metadataTransformer::transformSubjectToExtraFields($subject);
        $metadataJson = json_encode($extraFields);

        if (null === $subject->getElaftwExternalId()) {
            $dto = new CreateItemDto(
                categoryId: self::MOUSE_CATEGORY_ID,
                title: $subject->getName(),
                body: $metadataJson ?: null,
            );
            $created = $this->client->items($connectedApp)->create($dto);
            $subject->setElaftwExternalId((string) $created->id);
        } else {
            $dto = new UpdateItemDto(
                title: $subject->getName(),
                body: $metadataJson ?: null,
            );
            $this->client->items($connectedApp)->update((int) $subject->getElaftwExternalId(), $dto);
        }

        return $subject;
    }
}
