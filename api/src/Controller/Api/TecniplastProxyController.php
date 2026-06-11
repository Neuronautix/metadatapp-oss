<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Apps\Tecniplast\Client\TecniplastHttpClientInterface;
use App\ConnectedApps\Apps\Tecniplast\Import\DvcImportServiceInterface;
use App\ConnectedApps\Apps\Tecniplast\Operations\TecniplastOperationsDataProvider;
use App\Entity\ConnectedApp;
use App\Entity\Experiment;
use App\Entity\User;
use App\Enum\AppCode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TecniplastProxyController extends AbstractController
{
    private const string DVC_UTC_DATETIME_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    public function __construct(
        private TecniplastHttpClientInterface $tecniplastClient,
        private TecniplastOperationsDataProvider $operationsDataProvider,
        private DvcImportServiceInterface $dvcImportService,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private LoggerInterface $logger,
        #[Autowire('%kernel.debug%')]
        private bool $debugEnabled,
    ) {
    }

    private function debugLog(string $message, array $context = []): void
    {
        if (!$this->debugEnabled) {
            return;
        }

        $this->logger->debug($message, $context);
    }

    private function getValidToken(ConnectedApp $connectedApp): string
    {
        $this->assertConnectedAppAccount($connectedApp);

        if (AppCode::Tecniplast !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not Tecniplast.');
        }

        $token = $connectedApp->getToken();
        if (null === $token) {
            throw $this->createAccessDeniedException('No API Key configured.');
        }

        return $token;
    }

    private function getDvcBaseUrl(ConnectedApp $connectedApp): ?string
    {
        $externalUrl = $connectedApp->getExternalUrl();

        return null === $externalUrl || '' === trim($externalUrl) ? null : $externalUrl;
    }

    private function assertConnectedAppAccount(ConnectedApp $connectedApp): void
    {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $connectedApp->getAccount()->getId()?->toRfc4122() !== $user->getAccount()->getId()?->toRfc4122()) {
            throw $this->createAccessDeniedException('Connected app does not belong to the current account.');
        }
    }

    private function assertSubmitPayloadIsValid(array $payload): void
    {
        foreach (['start', 'stop'] as $field) {
            $value = $payload[$field] ?? null;

            if (!\is_string($value) || 1 !== preg_match(self::DVC_UTC_DATETIME_PATTERN, $value)) {
                throw new \InvalidArgumentException(\sprintf('Unexpected datetime format for "%s". Expected YYYY-MM-DDTHH:MM:SSZ (e.g. 2023-07-21T17:32:28Z)', $field));
            }
        }
    }

    public function testApiKey(ConnectedApp $connectedApp): JsonResponse
    {
        $token = $this->getValidToken($connectedApp);

        try {
            $this->debugLog('Tecniplast DVC test-api-key request', [
                'connected_app_id' => (string) $connectedApp->getId(),
            ]);

            $result = $this->tecniplastClient->testApiKey($token, $this->getDvcBaseUrl($connectedApp));

            $this->debugLog('Tecniplast DVC test-api-key response', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'result' => $result,
            ]);

            return new JsonResponse($result);
        } catch (\Throwable $e) {
            $this->logger->error('Tecniplast DVC test-api-key failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to validate Tecniplast API key. Check key and DVC endpoint configuration.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    public function getMetrics(ConnectedApp $connectedApp): JsonResponse
    {
        $token = $this->getValidToken($connectedApp);

        try {
            $this->debugLog('Tecniplast DVC metrics request', [
                'connected_app_id' => (string) $connectedApp->getId(),
            ]);

            $result = $this->tecniplastClient->getMetrics($token, $this->getDvcBaseUrl($connectedApp));

            $this->debugLog('Tecniplast DVC metrics response', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'count' => \count($result),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Tecniplast DVC metrics request failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to fetch Tecniplast metrics.',
                'detail' => $e->getMessage(),
            ], 502);
        }

        return new JsonResponse($result);
    }

    public function searchCages(Request $request, ConnectedApp $connectedApp): JsonResponse
    {
        $token = $this->getValidToken($connectedApp);
        $payload = json_decode($request->getContent(), true) ?? [];

        // Normalize cage IDs: replace _ with - before the final digit
        if (isset($payload['patterns']) && \is_array($payload['patterns'])) {
            $payload['patterns'] = array_map(static function ($pattern) {
                return preg_replace('/_(\d+)$/', '-$1', $pattern);
            }, $payload['patterns']);
        }

        try {
            $this->debugLog('Tecniplast DVC cages/search request', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'payload' => $payload,
            ]);

            $result = $this->tecniplastClient->searchCages($token, $payload, $this->getDvcBaseUrl($connectedApp));

            $this->debugLog('Tecniplast DVC cages/search response', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'count' => \count($result),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Tecniplast DVC cages/search failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'payload' => $payload,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to search cages on Tecniplast DVC.',
                'detail' => $e->getMessage(),
            ], 502);
        }

        // Find min and max dates
        $minDate = null;
        $maxDate = null;

        foreach ($result as $cage) {
            $registration = $cage['registrationEventDate'] ?? null;
            $lastEvent = $cage['lastEventDate'] ?? null;

            if ($registration && (!$minDate || $registration < $minDate)) {
                $minDate = $registration;
            }
            if ($lastEvent && (!$maxDate || $lastEvent > $maxDate)) {
                $maxDate = $lastEvent;
            }
        }

        return new JsonResponse([
            'cages' => $result,
            'min_date' => $minDate,
            'max_date' => $maxDate,
        ]);
    }

    public function searchAnimals(Request $request, ConnectedApp $connectedApp): JsonResponse
    {
        $token = $this->getValidToken($connectedApp);
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $this->debugLog('Tecniplast DVC animals/search request', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'payload' => $payload,
            ]);

            $result = $this->tecniplastClient->searchAnimals($token, $payload, $this->getDvcBaseUrl($connectedApp));

            $this->debugLog('Tecniplast DVC animals/search response', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'count' => \count($result),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Tecniplast DVC animals/search failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'payload' => $payload,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to search animals on Tecniplast DVC.',
                'detail' => $e->getMessage(),
            ], 502);
        }

        return new JsonResponse($result);
    }

    public function submitTask(Request $request, ConnectedApp $connectedApp): JsonResponse
    {
        $token = $this->getValidToken($connectedApp);
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $this->assertSubmitPayloadIsValid($payload);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'message' => 'Invalid DVC submission payload.',
                'detail' => $e->getMessage(),
            ], 400);
        }

        try {
            $this->debugLog('Tecniplast DVC submit request', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'payload' => $payload,
            ]);

            $result = $this->tecniplastClient->submitTask($token, $payload, $this->getDvcBaseUrl($connectedApp));

            $this->debugLog('Tecniplast DVC submit response', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'response' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Tecniplast DVC submit failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'payload' => $payload,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to submit extraction task to Tecniplast DVC.',
                'detail' => $e->getMessage(),
            ], 502);
        }

        return new JsonResponse($result);
    }

    public function getTaskState(int $taskId, ConnectedApp $connectedApp): JsonResponse
    {
        $token = $this->getValidToken($connectedApp);

        try {
            $this->debugLog('Tecniplast DVC task state request', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'task_id' => $taskId,
            ]);

            $result = $this->tecniplastClient->getTaskState($token, $taskId, $this->getDvcBaseUrl($connectedApp));
        } catch (\Throwable $e) {
            $this->logger->error('Tecniplast DVC task state failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to retrieve task state from Tecniplast DVC.',
                'detail' => $e->getMessage(),
            ], 502);
        }

        return new JsonResponse($result);
    }

    public function downloadTaskResult(int $taskId, ConnectedApp $connectedApp): StreamedResponse
    {
        $token = $this->getValidToken($connectedApp);
        $responseInterface = $this->tecniplastClient->downloadTaskResult($token, $taskId, $this->getDvcBaseUrl($connectedApp));

        return new StreamedResponse(function () use ($responseInterface): void {
            $stream = $this->tecniplastClient->toStream($responseInterface);
            if (\is_resource($stream)) {
                stream_copy_to_stream($stream, fopen('php://output', 'w') ?: throw new \RuntimeException('Could not open php://output'));
            }
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="task_' . $taskId . '.zip"',
        ]);
    }

    public function importTaskResult(int $taskId, ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        $token = $this->getValidToken($connectedApp);

        $body = json_decode($request->getContent(), true);
        $experimentIri = \is_array($body) ? ($body['experimentIri'] ?? null) : null;

        if (!\is_string($experimentIri) || '' === $experimentIri) {
            return new JsonResponse([
                'message' => 'Missing required field "experimentIri".',
            ], 400);
        }

        // Resolve UUID from IRI like /experiments/{uuid}
        $uuidStr = basename($experimentIri);
        /** @var Experiment|null $experiment */
        $experiment = $this->entityManager->getRepository(Experiment::class)->find($uuidStr);

        if (null === $experiment) {
            return new JsonResponse([
                'message' => 'Experiment not found.',
            ], 404);
        }

        if ((string) $experiment->getAccount()->getId() !== (string) $connectedApp->getAccount()->getId()) {
            return new JsonResponse([
                'message' => 'Experiment does not belong to the same account as the connected app.',
            ], 403);
        }

        // Download ZIP from Tecniplast and stream it to a temp file
        try {
            $responseInterface = $this->tecniplastClient->downloadTaskResult($token, $taskId, $this->getDvcBaseUrl($connectedApp));
        } catch (\Throwable $e) {
            $this->logger->error('Tecniplast DVC import: download failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to download extraction archive from Tecniplast DVC.',
                'detail' => $e->getMessage(),
            ], 502);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'dvc_import_') . '.zip';
        try {
            $stream = $this->tecniplastClient->toStream($responseInterface);
            if (\is_resource($stream)) {
                $dest = fopen($tmpFile, 'w');
                if (false === $dest) {
                    throw new \RuntimeException('Unable to open temp file for writing.');
                }
                stream_copy_to_stream($stream, $dest);
                fclose($dest);
            }

            $result = $this->dvcImportService->importFromZip($tmpFile, $experiment, $connectedApp, $taskId);
        } catch (\Throwable $e) {
            $this->logger->error('Tecniplast DVC import: import failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'DVC import failed.',
                'detail' => $e->getMessage(),
            ], 500);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        return new JsonResponse([
            'taskId' => $taskId,
            'experimentIri' => $experimentIri,
            'processedRows' => $result->processedRows,
            'importedRows' => $result->importedRows,
            'skippedRows' => $result->skippedRows,
        ], 200);
    }

    #[Route('/tp/facilities', name: 'api_tecniplast_operations_facilities', methods: ['GET'])]
    public function getFacilities(Request $request): JsonResponse
    {
        return new JsonResponse($this->operationsDataProvider->getFacilities($request->query->getString('status') ?: null));
    }

    #[Route('/tp/facilities/{id}', name: 'api_tecniplast_operations_facility', methods: ['GET'])]
    public function getFacilityById(string $id): JsonResponse
    {
        $facility = $this->operationsDataProvider->getFacility($id);

        if (null === $facility) {
            return new JsonResponse(['message' => 'Facility not found.'], 404);
        }

        return new JsonResponse($facility);
    }

    #[Route('/tp/rooms', name: 'api_tecniplast_operations_rooms', methods: ['GET'])]
    public function getRooms(Request $request): JsonResponse
    {
        return new JsonResponse($this->operationsDataProvider->getRooms($request->query->getString('facilityId') ?: null));
    }

    #[Route('/tp/rooms/{id}', name: 'api_tecniplast_operations_room', methods: ['GET'])]
    public function getRoomById(string $id): JsonResponse
    {
        $room = $this->operationsDataProvider->getRoom($id);

        if (null === $room) {
            return new JsonResponse(['message' => 'Room not found.'], 404);
        }

        return new JsonResponse($room);
    }

    #[Route('/tp/racks', name: 'api_tecniplast_operations_racks', methods: ['GET'])]
    public function getRacks(Request $request): JsonResponse
    {
        return new JsonResponse($this->operationsDataProvider->getRacks($request->query->getString('roomId') ?: null));
    }

    #[Route('/tp/racks/{id}', name: 'api_tecniplast_operations_rack', methods: ['GET'])]
    public function getRackById(string $id): JsonResponse
    {
        $rack = $this->operationsDataProvider->getRack($id);

        if (null === $rack) {
            return new JsonResponse(['message' => 'Rack not found.'], 404);
        }

        return new JsonResponse($rack);
    }

    #[Route('/tp/cages', name: 'api_tecniplast_operations_cages', methods: ['GET'])]
    public function getCages(Request $request): JsonResponse
    {
        return new JsonResponse($this->operationsDataProvider->getCages($request->query->getString('rackId') ?: null));
    }

    #[Route('/tp/cages/{id}', name: 'api_tecniplast_operations_cage', methods: ['GET'])]
    public function getCageById(string $id): JsonResponse
    {
        $cage = $this->operationsDataProvider->getCage($id);

        if (null === $cage) {
            return new JsonResponse(['message' => 'Cage not found.'], 404);
        }

        return new JsonResponse($cage);
    }

    #[Route('/tp/alarms', name: 'api_tecniplast_operations_alarms', methods: ['GET'])]
    public function getAlarms(Request $request): JsonResponse
    {
        return new JsonResponse($this->operationsDataProvider->getAlarms($request->query->all()));
    }

    #[Route('/tp/alarms/{id}', name: 'api_tecniplast_operations_alarm', methods: ['GET'])]
    public function getAlarmById(string $id): JsonResponse
    {
        $alarm = $this->operationsDataProvider->getAlarm($id);

        if (null === $alarm) {
            return new JsonResponse(['message' => 'Alarm not found.'], 404);
        }

        return new JsonResponse($alarm);
    }

    #[Route('/tp/sensors', name: 'api_tecniplast_operations_sensors', methods: ['GET'])]
    public function getSensors(Request $request): JsonResponse
    {
        return new JsonResponse($this->operationsDataProvider->getSensors($request->query->all()));
    }

    #[Route('/tp/sensors/{id}', name: 'api_tecniplast_operations_sensor', methods: ['GET'])]
    public function getSensorById(string $id): JsonResponse
    {
        $sensor = $this->operationsDataProvider->getSensor($id);

        if (null === $sensor) {
            return new JsonResponse(['message' => 'Sensor not found.'], 404);
        }

        return new JsonResponse($sensor);
    }

    #[Route('/tp/conditions/snapshot', name: 'api_tecniplast_operations_condition_snapshot', methods: ['GET'])]
    public function getConditionSnapshot(Request $request): JsonResponse
    {
        $scopeType = $request->query->getString('scopeType');
        $scopeId = $request->query->getString('scopeId');
        if ('' === $scopeType || '' === $scopeId) {
            return new JsonResponse(['message' => 'scopeType and scopeId required.'], 400);
        }

        $snapshot = $this->operationsDataProvider->getConditionSnapshot($scopeType, $scopeId, $request->query->getString('at') ?: null);
        if (null === $snapshot) {
            return new JsonResponse(['message' => 'Condition scope not found.'], 404);
        }

        return new JsonResponse($snapshot);
    }

    #[Route('/tp/conditions/readings', name: 'api_tecniplast_operations_condition_readings', methods: ['GET'])]
    public function getConditionReadings(Request $request): JsonResponse
    {
        $scopeType = $request->query->getString('scopeType');
        $scopeId = $request->query->getString('scopeId');
        $metric = $request->query->getString('metric');
        $from = $request->query->getString('from');
        $to = $request->query->getString('to');
        if ('' === $scopeType || '' === $scopeId || '' === $metric || '' === $from || '' === $to) {
            return new JsonResponse(['message' => 'Missing parameters.'], 400);
        }

        $readings = $this->operationsDataProvider->getConditionReadings($scopeType, $scopeId, $metric, $from, $to, $request->query->getString('interval') ?: null);
        if (null === $readings) {
            return new JsonResponse(['message' => 'Condition readings unavailable.'], 400);
        }

        return new JsonResponse($readings);
    }

    #[Route('/tp/conditions/thresholds', name: 'api_tecniplast_operations_condition_thresholds', methods: ['GET'])]
    public function getConditionThresholds(Request $request): JsonResponse
    {
        $scopeType = $request->query->getString('scopeType');
        $scopeId = $request->query->getString('scopeId');
        if ('' === $scopeType || '' === $scopeId) {
            return new JsonResponse(['message' => 'scopeType and scopeId required.'], 400);
        }

        $thresholds = $this->operationsDataProvider->getConditionThresholds($scopeType, $scopeId);
        if ([] === $thresholds) {
            return new JsonResponse(['message' => 'Condition scope not found.'], 404);
        }

        return new JsonResponse($thresholds);
    }

    #[Route('/tp/conditions/thresholds', name: 'api_tecniplast_operations_condition_thresholds_update', methods: ['PUT'])]
    public function updateConditionThresholds(Request $request): JsonResponse
    {
        $scopeType = $request->query->getString('scopeType');
        $scopeId = $request->query->getString('scopeId');
        if ('' === $scopeType || '' === $scopeId) {
            return new JsonResponse(['message' => 'scopeType and scopeId required.'], 400);
        }
        if ([] === $this->operationsDataProvider->getConditionThresholds($scopeType, $scopeId)) {
            return new JsonResponse(['message' => 'Condition scope not found.'], 404);
        }

        $body = json_decode($request->getContent(), true);
        $thresholds = \is_array($body) && \is_array($body['thresholds'] ?? null) ? $body['thresholds'] : null;
        if (null === $thresholds) {
            return new JsonResponse(['message' => 'thresholds required.'], 400);
        }

        return new JsonResponse($this->operationsDataProvider->updateConditionThresholds($scopeType, $scopeId, $thresholds));
    }
}
