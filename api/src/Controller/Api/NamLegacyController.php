<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class NamLegacyController extends AbstractController
{
    #[Route('/nam/datasets', name: 'api_nam_datasets', methods: ['GET'])]
    public function getDatasets(): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                [
                    'id' => 'NAM-001',
                    'name' => 'Control Group - B6 Baseline',
                    'pid' => 'doi:10.5072/dvc.nam.001',
                    'description' => 'Baseline activity and environment metrics for C57BL/6J control subjects.',
                    'strain' => 'C57BL/6J',
                    'sex' => 'Male',
                    'ageWeeks' => 12,
                    'environment' => 'Standard Housing',
                    'sanitaryStatus' => 'SPF',
                    'beddingMaterial' => 'Aspen Chips',
                    'beddingBatch' => 'B-2023-X9',
                    'facility' => 'Main Facility',
                    'room' => 'Room 101',
                    'device' => 'DVC-Board-V3',
                    'pipelineVersion' => '1.4.2',
                    'createdAt' => '2023-01-15T10:00:00Z',
                ],
                [
                    'id' => 'NAM-002',
                    'name' => 'Shank3 KO - Circadian Shift',
                    'pid' => 'doi:10.5072/dvc.nam.002',
                    'description' => 'Observation of sleep-wake cycle disruptions in Shank3 knockout models.',
                    'strain' => 'Shank3',
                    'sex' => 'Male',
                    'ageWeeks' => 14,
                    'environment' => 'Standard Housing',
                    'sanitaryStatus' => 'SPF',
                    'beddingMaterial' => 'Aspen Chips',
                    'beddingBatch' => 'B-2023-X9',
                    'facility' => 'Main Facility',
                    'room' => 'Room 102',
                    'device' => 'DVC-Board-V3',
                    'pipelineVersion' => '1.4.2',
                    'createdAt' => '2023-02-20T14:30:00Z',
                ],
            ],
        ]);
    }

    #[Route('/nam/activity', name: 'api_nam_activity', methods: ['GET'])]
    public function getActivity(): JsonResponse
    {
        return new JsonResponse(['data' => []]);
    }

    #[Route('/nam/vcg/generate', name: 'api_nam_vcg_generate', methods: ['POST'])]
    public function generateVcg(): JsonResponse
    {
        return new JsonResponse([
            'id' => 'VCG-MOCK',
            'generatedAt' => (new \DateTime())->format(\DateTime::ATOM),
            'candidates' => [],
        ]);
    }

    #[Route('/nam/vcg/{id}', name: 'api_nam_vcg_get', methods: ['GET'])]
    public function getVcg(string $id): JsonResponse
    {
        return new JsonResponse([
            'id' => $id,
            'generatedAt' => (new \DateTime())->format(\DateTime::ATOM),
            'candidates' => [],
        ]);
    }
}
