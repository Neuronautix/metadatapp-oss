<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Crosswalk\Export\CrosswalkJsonLdExporter;
use App\Crosswalk\Mapping\MappingSuggestion;
use App\Crosswalk\Mapping\MappingSuggestionEngine;
use App\Crosswalk\Validation\CrosswalkValidator;
use App\Entity\TemplateFormCrosswalk;
use App\Entity\User;
use App\Repository\CommonDataElementRepository;
use App\Repository\TemplateFormCrosswalkRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Crosswalk-level actions: suggest field mappings, validate semantic completeness,
 * and export enriched RO-Crate / JSON-LD. All actions are tenant-checked.
 */
#[IsGranted('ROLE_USER')]
final class CrosswalkActionController extends AbstractController
{
    public function __construct(
        private readonly TemplateFormCrosswalkRepository $crosswalkRepository,
        private readonly CommonDataElementRepository $cdeRepository,
        private readonly MappingSuggestionEngine $suggestionEngine,
        private readonly CrosswalkValidator $validator,
        private readonly CrosswalkJsonLdExporter $exporter,
    ) {
    }

    #[Route(path: '/api/template-crosswalks/{id}/suggest-mappings', name: 'api_crosswalk_suggest_mappings', methods: ['POST'])]
    public function suggestMappings(string $id): JsonResponse
    {
        $crosswalk = $this->loadCrosswalk($id);

        $candidates = $this->cdeRepository->findBy(['account' => $crosswalk->getAccount()]);

        $suggestions = [];
        foreach ($crosswalk->getExternalTemplate()->getExtractedFields() as $field) {
            $fieldSuggestions = $this->suggestionEngine->suggestForField($field, $candidates);
            $suggestions[] = [
                'field' => [
                    'id' => $field->getId()?->toRfc4122(),
                    'label' => $field->getLabel(),
                    'jsonldPath' => $field->getJsonldPath(),
                ],
                'suggestions' => array_map(static fn (MappingSuggestion $s): array => $s->toArray(), $fieldSuggestions),
            ];
        }

        return new JsonResponse(['suggestions' => $suggestions]);
    }

    #[Route(path: '/api/template-crosswalks/{id}/validate', name: 'api_crosswalk_validate', methods: ['POST'])]
    public function validate(string $id): JsonResponse
    {
        $crosswalk = $this->loadCrosswalk($id);

        return new JsonResponse($this->validator->validate($crosswalk)->toArray());
    }

    #[Route(path: '/api/template-crosswalks/{id}/export-ro-crate', name: 'api_crosswalk_export_ro_crate', methods: ['POST'])]
    public function exportRoCrate(string $id): JsonResponse
    {
        $crosswalk = $this->loadCrosswalk($id);

        return new JsonResponse($this->exporter->exportEnrichedRoCrate($crosswalk));
    }

    #[Route(path: '/api/template-crosswalks/{id}/export-jsonld', name: 'api_crosswalk_export_jsonld', methods: ['POST'])]
    public function exportJsonLd(string $id): JsonResponse
    {
        $crosswalk = $this->loadCrosswalk($id);

        return new JsonResponse($this->exporter->exportJsonLd($crosswalk));
    }

    private function loadCrosswalk(string $id): TemplateFormCrosswalk
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException('Invalid crosswalk ID format.');
        }

        $crosswalk = $this->crosswalkRepository->find($uuid);
        if (null === $crosswalk) {
            throw $this->createNotFoundException(\sprintf('Crosswalk %s not found.', $id));
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentication required.');
        }

        $crosswalkAccountId = $crosswalk->getAccount()->getId();
        $userAccountId = $user->getAccount()->getId();
        if (null === $crosswalkAccountId || null === $userAccountId || !$crosswalkAccountId->equals($userAccountId)) {
            throw $this->createAccessDeniedException('Crosswalk not accessible.');
        }

        return $crosswalk;
    }
}
