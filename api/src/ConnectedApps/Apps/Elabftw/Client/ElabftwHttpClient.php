<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\ElabftwResponseDto;
use App\ConnectedApps\Apps\Elabftw\Exception\ElabftwException;
use App\Entity\ConnectedApp;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
final readonly class ElabftwHttpClient implements ElabftwHttpClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     * @throws ElabftwException
     */
    public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): ElabftwResponseDto
    {
        $this->logger->debug('Sending request to Elabftw.', [
            'method' => $method,
            'uri' => $uri,
        ]);

        $response = $this->httpClient->request($method, $uri, $options);
        $statusCode = $response->getStatusCode();

        // eLabFTW returns 201 Created for POST requests with a Location header containing the new resource ID.
        if (201 === $statusCode) {
            return $this->handleCreatedResponse($response);
        }

        $responseContent = $response->getContent();
        $this->logger->debug('Elabftw Response.', ['statusCode' => $statusCode, 'length' => \strlen($responseContent)]);

        return $this->responseToDto($responseContent);
    }

    public function denormalizeResponse(array $content): ElabftwResponseDto
    {
        return new ElabftwResponseDto(data: $content);
    }

    public function listExperimentsTemplates(ConnectedApp $connectedApp): array
    {
        $url = $this->baseApiUrl($connectedApp) . 'experiments_templates?limit=100&offset=0';
        $response = $this->sendRequest('GET', $url, $this->buildOptions($connectedApp));
        $data = $response->data;

        return array_values(array_filter(
            \is_array($data['result'] ?? null) ? $data['result'] : (\is_array($data) ? $data : []),
            'is_array',
        ));
    }

    public function listItemsTypes(ConnectedApp $connectedApp): array
    {
        $url = $this->baseApiUrl($connectedApp) . 'items_types?limit=100&offset=0';
        $response = $this->sendRequest('GET', $url, $this->buildOptions($connectedApp));
        $data = $response->data;

        return array_values(array_filter(
            \is_array($data['result'] ?? null) ? $data['result'] : (\is_array($data) ? $data : []),
            'is_array',
        ));
    }

    private function baseApiUrl(ConnectedApp $connectedApp): string
    {
        $externalUrl = $connectedApp->getExternalUrl();
        if (!\is_string($externalUrl) || '' === trim($externalUrl)) {
            throw new \RuntimeException('eLabFTW connected app has no external URL configured.');
        }

        return rtrim($externalUrl, '/') . '/api/v2/';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(ConnectedApp $connectedApp): array
    {
        $options = [];
        $token = $connectedApp->getToken();
        if (\is_string($token) && '' !== trim($token)) {
            $options['headers']['Authorization'] = $token;
        }

        return $options;
    }

    /**
     * Handles a 201 Created response by extracting the new resource ID.
     * First attempts to parse the Location header, then falls back to the response body.
     *
     * @throws ElabftwException when no valid ID can be determined from the response
     */
    private function handleCreatedResponse(ResponseInterface $response): ElabftwResponseDto
    {
        $headers = $response->getHeaders(false);
        $location = $headers['location'][0] ?? null;

        // Attempt 1: extract ID from Location header (standard eLabFTW v2 behavior)
        if (null !== $location && preg_match('/(\d+)$/', $location, $matches)) {
            $id = (int) $matches[1];
            $this->logger->debug('Elabftw Created resource via Location header.', ['id' => $id, 'location' => $location]);

            return new ElabftwResponseDto(id: $id, data: ['id' => $id]);
        }

        // Attempt 2: fall back to response body if it contains an 'id' field
        try {
            $body = $response->getContent(false);
            if ('' !== $body) {
                $content = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);
                if (\is_array($content) && isset($content['id']) && is_numeric($content['id'])) {
                    $id = (int) $content['id'];
                    $this->logger->debug('Elabftw Created resource via response body.', ['id' => $id]);

                    return new ElabftwResponseDto(id: $id, data: $content);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->debug('Elabftw fallback body parsing failed for 201 Created response.', ['error' => $e->getMessage()]);
        }

        $this->logger->error('Elabftw 201 Created response has no parseable ID.', ['location' => $location]);

        throw new ElabftwException(\sprintf('ElabFTW returned 201 Created but no resource ID could be determined (Location: %s).', $location ?? 'none'));
    }

    private function responseToDto(string $responseContent): ElabftwResponseDto
    {
        if ('' === $responseContent) {
            return new ElabftwResponseDto();
        }

        $content = json_decode($responseContent, true, 512, \JSON_THROW_ON_ERROR);

        return new ElabftwResponseDto(data: $content);
    }
}
