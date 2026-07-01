<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Crosswalk\Cde\CdeCandidate;
use App\Crosswalk\Cde\CdeImportService;
use App\Entity\ImportedReference;
use App\Entity\User;
use App\Repository\ImportedReferenceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Promotes a standardized {@see ImportedReference} into a reusable
 * {@see \App\Entity\CommonDataElement}, carrying its canonical ontology term as the
 * CDE's ontology mapping — connecting the Reference Hub library to the Crosswalk
 * Studio. Wired as the `POST /imported_references/{id}/promote-to-cde` operation.
 */
final class PromoteReferenceToCdeController extends AbstractController
{
    public function __construct(
        private readonly ImportedReferenceRepository $repository,
        private readonly CdeImportService $cdeImportService,
        private readonly Security $security,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new NotFoundHttpException();
        }

        $reference = Uuid::isValid($id) ? $this->repository->find(Uuid::fromString($id)) : null;
        if (null === $reference || (string) $reference->getAccount()->getId() !== (string) $user->getAccount()->getId()) {
            throw new NotFoundHttpException('Imported reference not found.');
        }

        $cde = $this->cdeImportService->persistCde($this->toCandidate($reference), $user);

        return new JsonResponse([
            'id' => (string) $cde->getId(),
            'label' => $cde->getLabel(),
            'source' => $cde->getSource()->value,
            'localKey' => $cde->getLocalKey(),
        ], 201);
    }

    private function toCandidate(ImportedReference $reference): CdeCandidate
    {
        $ontologyMappings = [];
        $iri = $reference->getCanonicalTermIri();
        if (null !== $iri) {
            $ontologyMappings[] = array_filter([
                'iri' => $iri,
                'label' => $reference->getCanonicalTermLabel(),
                'source' => $reference->getCanonicalTermOntology(),
            ], static fn (mixed $v): bool => null !== $v);
        }

        return new CdeCandidate(
            source: 'nih_cde' === $reference->getSource() ? 'nih_cde' : 'custom',
            label: $reference->getTitle(),
            externalId: $reference->getReferenceId(),
            externalUrl: $reference->getExternalUrl(),
            definition: $reference->getDescription(),
            ontologyMappings: $ontologyMappings,
            provenance: array_filter([
                'importedReferenceId' => (string) $reference->getId(),
                'referenceId' => $reference->getReferenceId(),
                'sourceName' => $reference->getSourceName(),
                'clusterId' => $reference->getClusterId(),
                'standardizationConfidence' => $reference->getStandardizationConfidence(),
            ], static fn (mixed $v): bool => null !== $v),
            rawPayload: $reference->getRaw() ?? [],
        );
    }
}
