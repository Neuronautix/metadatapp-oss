<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Cedar\Client;

use App\ConnectedApps\Apps\Cedar\Enum\CedarArtifactType;
use App\Entity\ConnectedApp;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Production CEDAR resource-server client. Reproduces the request shape of the
 * official `cedar-artifact-rest-mcp` (`DefaultCedarHttp`): the
 * `Authorization: apiKey <key>` header, JSON content negotiation, IRIs
 * URL-encoded into the path, and a top-level `@id` nulled on create so the
 * server assigns one.
 *
 * @see https://github.com/metadatacenter/cedar-artifact-rest-mcp
 */
final class CedarClient implements CedarClientInterface
{
    private const string DEFAULT_BASE_URL = 'https://resource.metadatacenter.org';
    private const string AUTHORIZATION_PREFIX = 'apiKey ';
    // Artifacts are slowly-changing reference data; a short TTL keeps repeated
    // fetches off the upstream while staying fresh enough for editing flows.
    private const int ARTIFACT_CACHE_TTL = 300;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function getBaseUrl(ConnectedApp $connectedApp): string
    {
        $externalUrl = $connectedApp->getExternalUrl();
        if (null === $externalUrl || '' === trim($externalUrl)) {
            return self::DEFAULT_BASE_URL;
        }

        return rtrim(trim($externalUrl), '/');
    }

    public function getUsers(ConnectedApp $connectedApp): array
    {
        return $this->decode($this->request($connectedApp, 'GET', '/users'));
    }

    public function getArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, string $iri): array
    {
        $path = $this->idPath($type, $iri);

        return $this->cachedRead($connectedApp, $path, fn () => $this->decode($this->request($connectedApp, 'GET', $path)));
    }

    public function createArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, array $artifact): array
    {
        // The server mints the authoritative @id; null it on submission exactly
        // like the CEDAR MCP does.
        $artifact['@id'] = null;

        return $this->decode($this->request($connectedApp, 'POST', '/' . $type->pathSegment(), $artifact));
    }

    public function updateArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, string $iri, array $artifact): array
    {
        $path = $this->idPath($type, $iri);
        $updated = $this->decode($this->request($connectedApp, 'PUT', $path, $artifact));
        $this->invalidate($connectedApp, $path);

        return $updated;
    }

    public function deleteArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, string $iri): void
    {
        $path = $this->idPath($type, $iri);
        // Success (200/204) carries no JSON body; only assert the status code.
        $this->request($connectedApp, 'DELETE', $path);
        $this->invalidate($connectedApp, $path);
    }

    public function validateArtifact(ConnectedApp $connectedApp, array $artifact, ?CedarArtifactType $type = null): array
    {
        $resolvedType = $type ?? CedarArtifactType::detect($artifact);
        if (null === $resolvedType) {
            throw new \InvalidArgumentException('Could not determine the CEDAR artifact type from its @type; pass the type explicitly.');
        }

        $path = '/command/validate?resource_type=' . rawurlencode($resolvedType->validateResourceType());

        return $this->decode($this->request($connectedApp, 'POST', $path, $artifact));
    }

    public function searchArtifacts(ConnectedApp $connectedApp, string $query, int $limit = 20): array
    {
        $path = '/search?' . http_build_query([
            'q' => $query,
            'resource_types[]' => 'template',
            'page' => 1,
            'page_size' => $limit,
        ]);

        $payload = $this->decode($this->request($connectedApp, 'GET', $path));

        if (!isset($payload['resources']) || !\is_array($payload['resources'])) {
            return ['totalCount' => 0, 'resources' => []];
        }

        return [
            'totalCount' => \is_int($payload['totalCount'] ?? null) ? $payload['totalCount'] : 0,
            'resources' => array_values(array_filter($payload['resources'], 'is_array')),
        ];
    }

    /**
     * @param array<int|string, mixed>|null $json
     */
    private function request(ConnectedApp $connectedApp, string $method, string $path, ?array $json = null): ResponseInterface
    {
        $token = $connectedApp->getToken();
        if (null === $token || '' === trim($token)) {
            throw new \RuntimeException('No CEDAR API key configured.');
        }

        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $this->authorizationHeader($token),
            ],
        ];

        if (null !== $json) {
            $options['headers']['Content-Type'] = 'application/json';
            // Preserve null values (notably @id on create) — CEDAR distinguishes
            // an explicit null @id from an absent one.
            $options['json'] = $json;
        }

        $response = $this->httpClient->request($method, $this->getBaseUrl($connectedApp) . $path, $options);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(\sprintf('CEDAR returned HTTP %d: %s', $statusCode, $this->errorSnippet($response)));
        }

        return $response;
    }

    /**
     * Cache a pure GET read under a key scoped to the connection (account) and
     * the requested path, so a private artifact fetched by one account never
     * satisfies another's request. No-ops when no cache pool is configured.
     *
     * @param callable(): array<string, mixed> $loader
     *
     * @return array<string, mixed>
     */
    private function cachedRead(ConnectedApp $connectedApp, string $path, callable $loader): array
    {
        if (null === $this->cache) {
            return $loader();
        }

        $item = $this->cache->getItem($this->cacheKey($connectedApp, $path));
        $cached = $item->get();
        if ($item->isHit() && \is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }

        $value = $loader();
        $item->set($value)->expiresAfter(self::ARTIFACT_CACHE_TTL);
        $this->cache->save($item);

        return $value;
    }

    private function invalidate(ConnectedApp $connectedApp, string $path): void
    {
        $this->cache?->deleteItem($this->cacheKey($connectedApp, $path));
    }

    private function cacheKey(ConnectedApp $connectedApp, string $path): string
    {
        return 'cedar.artifact.' . hash('sha256', implode('|', [
            (string) $connectedApp->getId(),
            $this->getBaseUrl($connectedApp),
            $path,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        return $response->toArray(false);
    }

    private function idPath(CedarArtifactType $type, string $iri): string
    {
        $iri = trim($iri);
        if ('' === $iri) {
            throw new \InvalidArgumentException('A CEDAR artifact @id (IRI) is required.');
        }

        return '/' . $type->pathSegment() . '/' . rawurlencode($iri);
    }

    private function authorizationHeader(string $token): string
    {
        $token = trim($token);

        return str_starts_with($token, self::AUTHORIZATION_PREFIX) ? $token : self::AUTHORIZATION_PREFIX . $token;
    }

    private function errorSnippet(ResponseInterface $response): string
    {
        try {
            $body = $response->getContent(false);
        } catch (\Throwable) {
            return '(no response body)';
        }

        $body = trim($body);
        if ('' === $body) {
            return '(empty response body)';
        }

        return mb_substr($body, 0, 500);
    }
}
