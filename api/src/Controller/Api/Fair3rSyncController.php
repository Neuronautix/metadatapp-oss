<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Experiment;
use App\Entity\Project;
use App\Service\Fair3rFdfMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Public-facing FAIR3R exchange endpoints.
 *
 * These routes expose Metadatapp investigations and studies in two interoperable formats:
 *   1. DataCite 4.x JSON — aligned with the FAIR3R Open Science Dataset Registration Wizard
 *      (https://github.com/precliniverse/Dynamic_Metadata_form, v3.0.0), used to exchange
 *      data with validation.fair3r.fr.
 *   2. DataCite XML — DataCite Metadata Schema 4.x, used for DOI registration.
 *
 * External apps (e.g. FAIR3R) can GET these endpoints to pull metadata, or POST
 * a DataCite JSON payload to the validate endpoint to check semantic compliance.
 *
 * All routes require ROLE_USER. Tenant isolation is enforced — only resources belonging
 * to the authenticated user's account are accessible.
 *
 * Routes:
 *   GET  /fair3r/studies/{id}/dataset.json          — Experiment as DataCite 4.x JSON
 *   GET  /fair3r/studies/{id}/datacite.xml          — Experiment as DataCite XML
 *   GET  /fair3r/investigations/{id}/dataset.json   — Investigation as DataCite 4.x JSON
 *   GET  /fair3r/investigations/{id}/datacite.xml   — Investigation as DataCite XML
 *   POST /fair3r/datasets/validate                  — Validate an incoming DataCite JSON payload
 */
#[IsGranted('ROLE_USER')]
#[Route('/fair3r', name: 'api_fair3r_')]
final class Fair3rSyncController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Fair3rFdfMapper $fdfMapper,
        private readonly Security $security,
    ) {
    }

    // ------------------------------------------------------------------
    // Studies (Experiments)
    // ------------------------------------------------------------------

    #[Route('/studies/{id}/dataset.json', name: 'study_fdf', methods: ['GET'])]
    public function studyFdf(string $id, Request $request): JsonResponse
    {
        $experiment = $this->findExperiment($id);
        if (null === $experiment) {
            return $this->json(['error' => 'Study not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isSameAccount($experiment->getUser())) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $doc = $this->fdfMapper->experimentToFdf($experiment, $request->getSchemeAndHttpHost());

        return $this->fdfResponse($doc);
    }

    #[Route('/studies/{id}/datacite.xml', name: 'study_datacite', methods: ['GET'])]
    public function studyDataciteXml(string $id, Request $request): Response
    {
        $experiment = $this->findExperiment($id);
        if (null === $experiment) {
            return $this->json(['error' => 'Study not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isSameAccount($experiment->getUser())) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $xml = $this->fdfMapper->experimentToDataciteXml($experiment, $request->getSchemeAndHttpHost());

        return $this->xmlResponse($xml, \sprintf('study-%s-datacite.xml', $id));
    }

    // ------------------------------------------------------------------
    // Investigations (Projects)
    // ------------------------------------------------------------------

    #[Route('/investigations/{id}/dataset.json', name: 'investigation_fdf', methods: ['GET'])]
    public function investigationFdf(string $id, Request $request): JsonResponse
    {
        $project = $this->findProject($id);
        if (null === $project) {
            return $this->json(['error' => 'Investigation not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isSameAccount($project->getUser())) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $doc = $this->fdfMapper->projectToFdf($project, $request->getSchemeAndHttpHost());

        return $this->fdfResponse($doc);
    }

    #[Route('/investigations/{id}/datacite.xml', name: 'investigation_datacite', methods: ['GET'])]
    public function investigationDataciteXml(string $id, Request $request): Response
    {
        $project = $this->findProject($id);
        if (null === $project) {
            return $this->json(['error' => 'Investigation not found.'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isSameAccount($project->getUser())) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $xml = $this->fdfMapper->projectToDataciteXml($project, $request->getSchemeAndHttpHost());

        return $this->xmlResponse($xml, \sprintf('investigation-%s-datacite.xml', $id));
    }

    // ------------------------------------------------------------------
    // Validation endpoint — POST an FDF JSON for semantic verification
    // ------------------------------------------------------------------

    /**
     * Validates an incoming FDF JSON payload and returns the validation report.
     *
     * Body: FDF JSON object
     *
     * Response 200:  { "valid": true, "errors": [] }
     * Response 422:  { "valid": false, "errors": ["…", "…"] }
     */
    #[Route('/datasets/validate', name: 'validate_fdf', methods: ['POST'])]
    public function validateFdf(Request $request): JsonResponse
    {
        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->json([
                'valid' => false,
                'errors' => ['Request body is not valid JSON: ' . $e->getMessage()],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $errors = $this->fdfMapper->validateFdf($payload);

        if ([] !== $errors) {
            return $this->json([
                'valid' => false,
                'errors' => $errors,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'valid' => true,
            'errors' => [],
            'schema' => 'https://fair3r.fr/schemas/datacite-dataset/3.0',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function findExperiment(string $id): ?Experiment
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->entityManager->getRepository(Experiment::class)->find(Uuid::fromString($id));
    }

    private function findProject(string $id): ?Project
    {
        if (!Uuid::isValid($id)) {
            return null;
        }

        return $this->entityManager->getRepository(Project::class)->find(Uuid::fromString($id));
    }

    private function isSameAccount(?\App\Entity\User $resourceUser): bool
    {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return true;
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof \App\Entity\User || null === $resourceUser) {
            return false;
        }

        $currentAccountId = $currentUser->getAccount()->getId();
        $resourceAccountId = $resourceUser->getAccount()->getId();

        if (null === $currentAccountId || null === $resourceAccountId) {
            return false;
        }

        return $currentAccountId->equals($resourceAccountId);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function fdfResponse(array $data): JsonResponse
    {
        $response = new JsonResponse($data);
        $response->headers->set('Content-Type', 'application/ld+json');
        $response->headers->set('X-FDF-Schema', 'https://fair3r.fr/schemas/datacite-dataset/3.0');

        return $response;
    }

    private function xmlResponse(string $xml, string $filename): Response
    {
        $response = new Response($xml);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf('attachment; filename="%s"', $filename));

        return $response;
    }
}
