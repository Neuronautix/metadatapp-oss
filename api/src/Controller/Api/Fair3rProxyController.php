<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Apps\Fair3r\Client\Fair3rHttpClientInterface;
use App\Entity\ConnectedApp;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\AppCode;
use App\Service\Fair3rDemoCsvCatalog;
use App\Service\Fair3rFdfMapper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
class Fair3rProxyController extends AbstractController
{
    public function __construct(
        private readonly Fair3rHttpClientInterface $httpClient,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
        private readonly Fair3rFdfMapper $fdfMapper,
        private readonly Fair3rDemoCsvCatalog $demoCsvCatalog,
    ) {
    }

    public function testConnection(ConnectedApp $connectedApp): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);

            // Use per-ConnectedApp credentials, overriding the global scoped client
            $options = $this->buildRequestOptions($connectedApp);
            $response = $this->sendCkanAction($connectedApp, 'POST', 'package_list', $options);
            $this->assertSuccessfulCkanResponse($response->success, 'package_list', $response->error);

            // package_list returns a flat list of dataset name strings, not DatasetDto objects
            $datasetsCount = \count($response->result);

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $connectedApp->getExternalUrl(),
                'datasetsCount' => $datasetsCount,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Fair3R test-connection failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to validate Fair3R connection. Check URL and API token.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * List all dataset names from the connected FAIR3R / CKAN instance.
     *
     * Response: { "datasets": ["slug1", "slug2", …] }
     */
    public function listDatasets(ConnectedApp $connectedApp): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);

            $options = $this->buildRequestOptions($connectedApp);
            $options['query'] = ['rows' => 1000, 'include_private' => 'true'];
            $response = $this->sendCkanAction($connectedApp, 'GET', 'package_search', $options);
            $this->assertSuccessfulCkanResponse($response->success, 'package_search', $response->error);

            // package_search returns { count: N, results: [{name: "...", ...}, ...] }
            $results = $response->result['results'] ?? [];
            $names = array_values(array_filter(array_map(
                static fn ($pkg) => \is_array($pkg) ? ($pkg['name'] ?? null) : null,
                $results,
            )));

            return new JsonResponse(['datasets' => $names]);
        } catch (\Throwable $e) {
            $this->logger->error('Fair3R list-datasets failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'message' => 'Unable to list Fair3R datasets.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    public function showDataset(ConnectedApp $connectedApp, string $datasetName): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);

            $datasetName = $this->normalizeCkanDatasetName($datasetName);
            if ('' === $datasetName) {
                return new JsonResponse(['message' => 'datasetName is required.'], Response::HTTP_BAD_REQUEST);
            }

            $options = $this->buildRequestOptions($connectedApp);
            $options['query'] = ['id' => $datasetName];
            $response = $this->sendCkanAction($connectedApp, 'GET', 'package_show', $options);
            $this->assertSuccessfulCkanResponse($response->success, 'package_show', $response->error);

            return new JsonResponse([
                'dataset' => $this->normalizeDatasetResult($response->result),
                'fdf' => $this->extractFdfFromDatasetResult($response->result),
                'ckan' => $response->result,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Fair3R show-dataset failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'dataset_name' => $datasetName,
                'error' => $e->getMessage(),
            ]);

            $detail = $e->getMessage();
            $status = str_contains(strtolower($detail), 'access denied')
                ? Response::HTTP_FORBIDDEN
                : Response::HTTP_BAD_GATEWAY;

            return new JsonResponse([
                'message' => $detail,
                'detail' => 'Unable to read Fair3R dataset.',
            ], $status);
        }
    }

    /**
     * Push an investigation (Project) to FAIR3R as a CKAN package.
     *
     * Body: { "investigationId": "<uuid>" }
     *
     * Response 200: { "ok": true, "ckanName": "…", "packageUrl": "…" }
     * Response 4xx/502: error
     */
    public function pushInvestigation(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);

            /** @var array<string, mixed> $body */
            $body = json_decode($request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $investigationId = $body['investigationId'] ?? null;

            if (!\is_string($investigationId) || !Uuid::isValid($investigationId)) {
                return new JsonResponse(['message' => 'investigationId is required and must be a valid UUID.'], Response::HTTP_BAD_REQUEST);
            }
            $targetDatasetName = $body['targetDatasetName'] ?? null;
            if (null !== $targetDatasetName && (!\is_string($targetDatasetName) || '' === trim($targetDatasetName))) {
                return new JsonResponse(['message' => 'targetDatasetName must be a non-empty string when provided.'], Response::HTTP_BAD_REQUEST);
            }
            $selectedDemoCsvKeys = $this->selectedDemoCsvKeys($body['demoCsvKeys'] ?? []);

            $project = $this->entityManager->getRepository(Project::class)->find(Uuid::fromString($investigationId));
            if (null === $project) {
                return new JsonResponse(['message' => 'Investigation not found.'], Response::HTTP_NOT_FOUND);
            }

            $currentUser = $this->security->getUser();
            $projectUser = $project->getUser();
            $currentAccountId = $currentUser instanceof User ? $currentUser->getAccount()->getId() : null;
            $projectAccountId = $projectUser->getAccount()->getId();
            if (
                !$this->security->isGranted('ROLE_SUPER_ADMIN')
                && (null === $currentAccountId || null === $projectAccountId || !$projectAccountId->equals($currentAccountId))
            ) {
                return new JsonResponse(['message' => 'Access denied.'], Response::HTTP_FORBIDDEN);
            }

            $ckanName = \is_string($targetDatasetName)
                ? $this->normalizeCkanDatasetName($targetDatasetName)
                : ($this->resolveTargetDatasetName($connectedApp) ?? $this->buildCkanName($project));
            $usesConfiguredTargetDataset = \is_string($targetDatasetName) || null !== $this->resolveTargetDatasetName($connectedApp);
            $user = $project->getUser();
            $authorName = $user->getName() ?? $user->getEmail();
            $fdfPayload = $this->fdfMapper->projectToFdf($project, $request->getSchemeAndHttpHost());
            $landingUrl = $this->extractFirstAlternateIdentifierUrl($fdfPayload) ?? $request->getSchemeAndHttpHost() . '/fair3r/investigations/' . $investigationId . '/dataset.json';

            $packagePayload = [
                'name' => $ckanName,
                'title' => $project->getName(),
                'notes' => $project->getDescription() ?? $project->getName(),
                'author' => $authorName,
                'author_email' => $user->getEmail(),
                'license_id' => 'cc-by',
                'extras' => [
                    ['key' => 'metadatapp_id', 'value' => $project->getId()?->toRfc4122()],
                    ['key' => 'metadatapp_type', 'value' => 'investigation'],
                    ['key' => 'fair3r_schema', 'value' => 'https://fair3r.fr/schemas/datacite-dataset/3.0'],
                ],
            ];
            $options = $this->buildRequestOptions($connectedApp);
            $ownerOrg = $this->resolveOwnerOrg($connectedApp, $options);
            if (null !== $ownerOrg) {
                $packagePayload['owner_org'] = $ownerOrg;
            }

            $externalUrl = $connectedApp->getExternalUrl();

            // Check if the package already exists; patch if so, create if not
            $packageExists = false;
            $packageResult = null;
            try {
                $showOptions = $options;
                $showOptions['query'] = ['id' => $ckanName];
                $showResponse = $this->sendCkanAction($connectedApp, 'GET', 'package_show', $showOptions);
                $this->assertSuccessfulCkanResponse($showResponse->success, 'package_show', $showResponse->error);
                $packageExists = true;
                $packageResult = $showResponse->result;
            } catch (\Throwable $showError) {
                $this->logger->debug('Fair3R package_show failed', [
                    'ckan_name' => $ckanName,
                    'error' => $showError->getMessage(),
                ]);
                // A "not found" failure means the package doesn't exist yet — proceed to create.
                // Any other failure (access denied, bad token, …) on an explicitly configured
                // target dataset must surface immediately; silently falling through to
                // package_create would produce a misleading "not authorized to create" error.
                $lowerMessage = strtolower($showError->getMessage());
                $isNotFound = str_contains($lowerMessage, 'not found')
                    || str_contains($lowerMessage, '404')
                    || str_contains($lowerMessage, 'object not found');
                if ($usesConfiguredTargetDataset && !$isNotFound) {
                    throw new \RuntimeException(\sprintf('Dataset "%s" could not be accessed: %s', $ckanName, $showError->getMessage()), 0, $showError);
                }
                // Package does not exist yet — will create
            }

            if ($packageExists) {
                $packagePayload['id'] = $ckanName;
                $patchOptions = $options;
                $patchOptions['json'] = $packagePayload;
                $patchResponse = $this->sendCkanAction($connectedApp, 'POST', 'package_patch', $patchOptions);
                $this->assertSuccessfulCkanResponse($patchResponse->success, 'package_patch', $patchResponse->error);
                $packageResult = $patchResponse->result;
            } else {
                if (!$usesConfiguredTargetDataset) {
                    return new JsonResponse([
                        'message' => 'Fair3R package creation is not allowed for this token. Configure an existing Target Dataset in the FAIR3R settings.',
                    ], Response::HTTP_CONFLICT);
                }

                $createOptions = $options;
                $createOptions['json'] = $packagePayload;
                $createResponse = $this->sendCkanAction($connectedApp, 'POST', 'package_create', $createOptions);
                $this->assertSuccessfulCkanResponse($createResponse->success, 'package_create', $createResponse->error);
                $packageResult = $createResponse->result;
            }

            $resourceId = $this->upsertFdfResource(
                connectedApp: $connectedApp,
                options: $options,
                packageName: $ckanName,
                packageResult: $packageResult,
                fdfPayload: $fdfPayload,
                landingUrl: $landingUrl,
            );
            $demoCsvResourceIds = $this->upsertDemoCsvResources(
                connectedApp: $connectedApp,
                options: $options,
                packageName: $ckanName,
                packageResult: $packageResult,
                demoCsvKeys: $selectedDemoCsvKeys,
                baseUrl: $request->getSchemeAndHttpHost(),
            );

            $packageUrl = \is_string($externalUrl)
                ? rtrim($externalUrl, '/') . '/dataset/' . $ckanName
                : null;

            $this->logger->info('Fair3R investigation pushed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'investigation_id' => $investigationId,
                'ckan_name' => $ckanName,
            ]);

            return new JsonResponse([
                'ok' => true,
                'ckanName' => $ckanName,
                'packageUrl' => $packageUrl,
                'resourceId' => $resourceId,
                'demoCsvResourceIds' => $demoCsvResourceIds,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Fair3R push-investigation failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'message' => 'Unable to push investigation to Fair3R: ' . $e->getMessage(),
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Build per-request options that override the global Fair3R scoped client
     * with the credentials stored on the ConnectedApp entity.
     *
     * @return array<string, mixed>
     */
    private function buildRequestOptions(ConnectedApp $connectedApp): array
    {
        $options = [];

        $externalUrl = $connectedApp->getExternalUrl();
        if (\is_string($externalUrl) && '' !== trim($externalUrl)) {
            $parts = parse_url(trim($externalUrl));
            if (\is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                $options['base_uri'] = \sprintf('%s://%s%s/', $parts['scheme'], $parts['host'], $port);
            } else {
                $options['base_uri'] = rtrim($externalUrl, '/') . '/';
            }
        }

        $token = $this->resolveFair3rAccessToken($connectedApp);
        if (\is_string($token) && '' !== trim($token)) {
            $options['headers']['Authorization'] = $token;
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function sendCkanAction(ConnectedApp $connectedApp, string $method, string $action, array $options = []): \App\ConnectedApps\Apps\Fair3r\Client\Dto\Fair3rResponseDto
    {
        return $this->httpClient->sendRequest($method, $this->buildCkanActionUri($connectedApp, $action), $options);
    }

    private function buildCkanActionUri(ConnectedApp $connectedApp, string $action): string
    {
        $externalUrl = trim((string) $connectedApp->getExternalUrl());
        $path = (string) (parse_url($externalUrl, \PHP_URL_PATH) ?: '');
        $path = rtrim($path, '/');

        if (preg_match('#^(?<prefix>.*?)/api/(?:3/)?action(?:/.*)?$#', $path, $matches)) {
            $prefix = rtrim($matches['prefix'], '/');

            return \sprintf('%s/api/3/action/%s', $prefix, $action);
        }

        return \sprintf('/api/3/action/%s', $action);
    }

    private function resolveFair3rAccessToken(ConnectedApp $connectedApp): ?string
    {
        $authenticationParameters = $connectedApp->getAuthenticationParameters() ?? [];
        foreach (['accessToken', 'token', 'apiKey'] as $key) {
            $value = $authenticationParameters[$key] ?? null;
            if (\is_scalar($value) && '' !== trim((string) $value)) {
                return trim((string) $value);
            }
        }

        return $connectedApp->getToken();
    }

    private function assertSuccessfulCkanResponse(bool $success, string $action, array $error = []): void
    {
        if (!$success) {
            $message = $this->ckanErrorMessage($error) ?? \sprintf('Fair3R CKAN action "%s" returned success=false.', $action);

            throw new \RuntimeException($message);
        }
    }

    /**
     * @param array<string, mixed> $error
     */
    private function ckanErrorMessage(array $error): ?string
    {
        foreach (['message', '__type'] as $key) {
            $value = $error[$key] ?? null;
            if (\is_scalar($value) && '' !== trim((string) $value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function buildCkanName(Project $project): string
    {
        $id = $project->getId()?->toRfc4122() ?? bin2hex(random_bytes(8));
        $name = 'metadatapp-' . strtolower($id);
        $name = preg_replace('/[^a-z0-9_-]+/', '-', $name) ?? $name;
        $name = trim($name, '-_');

        return substr($name, 0, 100);
    }

    private function normalizeCkanDatasetName(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?? $normalized;

        return trim($normalized, '-_');
    }

    private function resolveTargetDatasetName(ConnectedApp $connectedApp): ?string
    {
        $authenticationParameters = $connectedApp->getAuthenticationParameters() ?? [];
        foreach (['targetDatasetName', 'datasetName', 'packageName', 'ckanName'] as $key) {
            $value = $authenticationParameters[$key] ?? null;
            if (\is_scalar($value) && '' !== trim((string) $value)) {
                return $this->normalizeCkanDatasetName((string) $value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveOwnerOrg(ConnectedApp $connectedApp, array $options): ?string
    {
        $authenticationParameters = $connectedApp->getAuthenticationParameters() ?? [];
        foreach (['owner_org', 'ownerOrg', 'organizationId', 'organizationName'] as $key) {
            $value = $authenticationParameters[$key] ?? null;
            if (\is_scalar($value) && '' !== trim((string) $value)) {
                return trim((string) $value);
            }
        }

        try {
            $response = $this->sendCkanAction($connectedApp, 'GET', 'organization_list_for_user', $options);
            if (!$response->success) {
                return null;
            }

            $firstOrganization = $response->result[0] ?? null;
            if (\is_array($firstOrganization)) {
                foreach (['id', 'name'] as $key) {
                    if (isset($firstOrganization[$key]) && \is_scalar($firstOrganization[$key]) && '' !== trim((string) $firstOrganization[$key])) {
                        return trim((string) $firstOrganization[$key]);
                    }
                }
            }

            if (\is_scalar($firstOrganization) && '' !== trim((string) $firstOrganization)) {
                return trim((string) $firstOrganization);
            }
        } catch (\Throwable $e) {
            $this->logger->info('Fair3R owner organization could not be inferred.', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $packageResult
     * @param array<string, mixed>      $fdfPayload
     */
    private function upsertFdfResource(
        ConnectedApp $connectedApp,
        array $options,
        string $packageName,
        ?array $packageResult,
        array $fdfPayload,
        string $landingUrl,
    ): ?string {
        $resourcePayload = [
            'package_id' => $packageName,
            'name' => 'Metadatapp FAIR3R JSON',
            'description' => 'FAIR3R/DataCite JSON generated by Metadatapp.',
            'format' => 'JSON',
            'mimetype' => 'application/ld+json',
            'url' => $landingUrl,
            'metadatapp_fdf_json' => json_encode($fdfPayload, \JSON_THROW_ON_ERROR),
        ];

        $resourceId = $this->findExistingFdfResourceId($packageResult);
        $resourceOptions = $options;

        if (null !== $resourceId) {
            $resourcePayload['id'] = $resourceId;
            $resourceOptions['json'] = $resourcePayload;
            $response = $this->sendCkanAction($connectedApp, 'POST', 'resource_patch', $resourceOptions);
            $this->assertSuccessfulCkanResponse($response->success, 'resource_patch', $response->error);
        } else {
            $resourceOptions['json'] = $resourcePayload;
            $response = $this->sendCkanAction($connectedApp, 'POST', 'resource_create', $resourceOptions);
            $this->assertSuccessfulCkanResponse($response->success, 'resource_create', $response->error);
        }

        return isset($response->result['id']) && \is_scalar($response->result['id'])
            ? (string) $response->result['id']
            : $resourceId;
    }

    /**
     * @param array<string, mixed>|null $packageResult
     * @param list<string>              $demoCsvKeys
     *
     * @return array<string, string|null>
     */
    private function upsertDemoCsvResources(
        ConnectedApp $connectedApp,
        array $options,
        string $packageName,
        ?array $packageResult,
        array $demoCsvKeys,
        string $baseUrl,
    ): array {
        $resourceIds = [];
        foreach ($demoCsvKeys as $key) {
            $record = $this->demoCsvCatalog->get($key);
            if (null === $record) {
                continue;
            }

            $resourceName = 'Demo CSV - ' . $record['title'];
            $resourcePayload = [
                'package_id' => $packageName,
                'name' => $resourceName,
                'description' => $record['description'],
                'format' => 'CSV',
                'mimetype' => 'text/csv',
                'url' => \sprintf('%s/fair3r/demo-results/%s.csv', rtrim($baseUrl, '/'), $key),
                'metadatapp_demo_csv_key' => $key,
                'metadatapp_demo_csv' => $record['csv'],
            ];

            $resourceId = $this->findExistingDemoCsvResourceId($packageResult, $key, $resourceName);
            $resourceOptions = $options;
            if (null !== $resourceId) {
                $resourcePayload['id'] = $resourceId;
                $resourceOptions['json'] = $resourcePayload;
                $response = $this->sendCkanAction($connectedApp, 'POST', 'resource_patch', $resourceOptions);
                $this->assertSuccessfulCkanResponse($response->success, 'resource_patch', $response->error);
            } else {
                $resourceOptions['json'] = $resourcePayload;
                $response = $this->sendCkanAction($connectedApp, 'POST', 'resource_create', $resourceOptions);
                $this->assertSuccessfulCkanResponse($response->success, 'resource_create', $response->error);
            }

            $resourceIds[$key] = isset($response->result['id']) && \is_scalar($response->result['id'])
                ? (string) $response->result['id']
                : $resourceId;
        }

        return $resourceIds;
    }

    /**
     * @param array<string, mixed>|null $packageResult
     */
    private function findExistingFdfResourceId(?array $packageResult): ?string
    {
        foreach ((array) ($packageResult['resources'] ?? []) as $resource) {
            if (!\is_array($resource)) {
                continue;
            }

            $name = isset($resource['name']) && \is_scalar($resource['name']) ? (string) $resource['name'] : '';
            if ('Metadatapp FAIR3R JSON' !== $name) {
                continue;
            }

            return isset($resource['id']) && \is_scalar($resource['id']) ? (string) $resource['id'] : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $packageResult
     */
    private function findExistingDemoCsvResourceId(?array $packageResult, string $key, string $resourceName): ?string
    {
        foreach ((array) ($packageResult['resources'] ?? []) as $resource) {
            if (!\is_array($resource)) {
                continue;
            }

            $resourceKey = isset($resource['metadatapp_demo_csv_key']) && \is_scalar($resource['metadatapp_demo_csv_key']) ? (string) $resource['metadatapp_demo_csv_key'] : null;
            $name = isset($resource['name']) && \is_scalar($resource['name']) ? (string) $resource['name'] : null;
            if ($key !== $resourceKey && $resourceName !== $name) {
                continue;
            }

            return isset($resource['id']) && \is_scalar($resource['id']) ? (string) $resource['id'] : null;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function selectedDemoCsvKeys(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $allowedKeys = array_keys($this->demoCsvCatalog->all());
        $selectedKeys = [];
        foreach ($value as $item) {
            if (!\is_scalar($item)) {
                continue;
            }

            $key = trim((string) $item);
            if ('' !== $key && \in_array($key, $allowedKeys, true)) {
                $selectedKeys[$key] = $key;
            }
        }

        return array_values($selectedKeys);
    }

    /**
     * @param array<string, mixed> $datasetResult
     *
     * @return array<string, mixed>
     */
    private function normalizeDatasetResult(array $datasetResult): array
    {
        return [
            'id' => $this->stringValue($datasetResult['id'] ?? null),
            'name' => $this->stringValue($datasetResult['name'] ?? null),
            'title' => $this->stringValue($datasetResult['title'] ?? null),
            'notes' => $this->stringValue($datasetResult['notes'] ?? null),
            'licenseId' => $this->stringValue($datasetResult['license_id'] ?? null),
            'author' => $this->stringValue($datasetResult['author'] ?? null),
            'authorEmail' => $this->stringValue($datasetResult['author_email'] ?? null),
            'metadataCreated' => $this->stringValue($datasetResult['metadata_created'] ?? null),
            'metadataModified' => $this->stringValue($datasetResult['metadata_modified'] ?? null),
            'resources' => array_values(array_map(
                fn (array $resource): array => [
                    'id' => $this->stringValue($resource['id'] ?? null),
                    'name' => $this->stringValue($resource['name'] ?? null),
                    'description' => $this->stringValue($resource['description'] ?? null),
                    'format' => $this->stringValue($resource['format'] ?? null),
                    'mimetype' => $this->stringValue($resource['mimetype'] ?? null),
                    'url' => $this->stringValue($resource['url'] ?? null),
                ],
                array_filter((array) ($datasetResult['resources'] ?? []), \is_array(...)),
            )),
        ];
    }

    /**
     * @param array<string, mixed> $datasetResult
     *
     * @return array<string, mixed>|null
     */
    private function extractFdfFromDatasetResult(array $datasetResult): ?array
    {
        foreach ($this->fdfJsonCandidates($datasetResult) as $candidate) {
            try {
                $decoded = json_decode($candidate, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $datasetResult
     *
     * @return list<string>
     */
    private function fdfJsonCandidates(array $datasetResult): array
    {
        // Keys that may hold the full FDF JSON directly (checked on both the package and each resource).
        // `fdf_output_json` is the key used by the FAIR3R wizard when submitting via the platform UI.
        $jsonFieldKeys = ['metadatapp_fdf_json', 'fdf_output_json', 'fdf_json', 'fair3r_json', 'datacite_json', 'fdf'];

        $candidates = [];
        foreach ([$datasetResult, ...(array) ($datasetResult['resources'] ?? [])] as $container) {
            if (!\is_array($container)) {
                continue;
            }

            foreach ($jsonFieldKeys as $key) {
                if (isset($container[$key]) && \is_scalar($container[$key]) && '' !== trim((string) $container[$key])) {
                    $candidates[] = (string) $container[$key];
                }
            }

            // Search extras — for FAIR3R-native datasets the full FDF or individual fields may be
            // stored here under a variety of keys.
            $extrasJsonValues = [];
            foreach ((array) ($container['extras'] ?? []) as $extra) {
                if (!\is_array($extra) || !isset($extra['key'], $extra['value'])) {
                    continue;
                }

                $extraKey = (string) $extra['key'];
                $extraValue = \is_scalar($extra['value']) ? trim((string) $extra['value']) : '';

                if ('' === $extraValue) {
                    continue;
                }

                // Priority: known JSON blob keys
                if (\in_array($extraKey, $jsonFieldKeys, true)) {
                    $candidates[] = $extraValue;
                    continue;
                }

                // Collect any extra value that looks like a JSON object — we'll try those last
                if (str_starts_with($extraValue, '{')) {
                    $extrasJsonValues[] = $extraValue;
                }
            }

            // Add generic JSON-looking extra values after the priority candidates
            array_push($candidates, ...$extrasJsonValues);
        }

        return $candidates;
    }

    private function stringValue(mixed $value): ?string
    {
        return \is_scalar($value) && '' !== trim((string) $value) ? (string) $value : null;
    }

    /**
     * @param array<string, mixed> $fdfPayload
     */
    private function extractFirstAlternateIdentifierUrl(array $fdfPayload): ?string
    {
        $alternateIdentifier = $fdfPayload['alternateIdentifiers'][0]['alternateIdentifier'] ?? null;

        return \is_string($alternateIdentifier) && '' !== trim($alternateIdentifier)
            ? $alternateIdentifier
            : null;
    }

    private function assertValidConnectedApp(ConnectedApp $connectedApp): void
    {
        $this->assertConnectedAppAccount($connectedApp);

        if (AppCode::Fair3r !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not Fair3R.');
        }

        $externalUrl = $connectedApp->getExternalUrl();
        if (null === $externalUrl || '' === trim($externalUrl)) {
            throw $this->createAccessDeniedException('No Fair3R external URL configured.');
        }
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
}
