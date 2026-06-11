<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Client;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TecniplastHttpClient implements TecniplastHttpClientInterface
{
    private const string DEFAULT_BASE_URL = 'https://analytics.dvc.tecniplast.it';

    private function summarizeErrorBody(mixed $decoded, string $rawBody, int $statusCode): string
    {
        if (401 === $statusCode) {
            return 'The configured API key was rejected by Tecniplast.';
        }

        if (\is_string($decoded) && '' !== trim($decoded)) {
            return $decoded;
        }

        if (\is_array($decoded)) {
            $message = $decoded['detail'] ?? $decoded['message'] ?? $decoded['error'] ?? null;
            if (\is_string($message) && '' !== trim($message)) {
                return $message;
            }

            $json = json_encode($decoded);
            if (false !== $json) {
                return $json;
            }
        }

        $trimmedBody = trim($rawBody);

        if ('' === $trimmedBody) {
            return 'Empty response body.';
        }

        return mb_substr($trimmedBody, 0, 300);
    }

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    private function resolveBaseUrl(?string $baseUrl): string
    {
        $normalizedBaseUrl = null === $baseUrl || '' === trim($baseUrl)
            ? self::DEFAULT_BASE_URL
            : trim($baseUrl);

        return rtrim($normalizedBaseUrl, '/');
    }

    private function doRequestWithBackoff(string $method, string $uri, array $options = [], int $retries = 0, ?string $baseUrl = null): ResponseInterface
    {
        // 1.5s base delay to respect rate limit (DVC has 2 requests/3 seconds limit or similar)
        usleep(1500000);

        $response = $this->httpClient->request($method, $this->resolveBaseUrl($baseUrl) . $uri, $options);

        try {
            $statusCode = $response->getStatusCode();
        } catch (\Throwable $e) {
            // Rethrow underlying transport exceptions
            throw $e;
        }

        if (429 === $statusCode && $retries < 3) {
            $headers = $response->getHeaders(false);
            $retryAfter = $headers['retry-after'][0] ?? null;

            if ($retryAfter) {
                $sleepSeconds = is_numeric($retryAfter) ? (int) $retryAfter : 10;
            } else {
                // Exponential backoff
                $sleepSeconds = 2 ** $retries * 2; // 2, 4, 8 seconds
            }

            sleep($sleepSeconds);

            return $this->doRequestWithBackoff($method, $uri, $options, $retries + 1, $baseUrl);
        }

        return $response;
    }

    public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true, ?string $baseUrl = null): mixed
    {
        return $this->doRequestWithBackoff($method, $uri, $options, baseUrl: $baseUrl)->toArray(false);
    }

    public function denormalizeResponse(array $content): mixed
    {
        return $content;
    }

    public function testApiKey(string $token, ?string $baseUrl = null): string|array
    {
        $response = $this->doRequestWithBackoff('POST', '/tasks/api/1/integration/test-api-key/', [
            'headers' => [
                'x-api-key' => $token,
            ],
        ], baseUrl: $baseUrl);

        $statusCode = $response->getStatusCode();
        $rawBody = $response->getContent(false);
        $decoded = json_decode($rawBody, true);

        if ($statusCode >= 400) {
            throw new \RuntimeException(\sprintf('Tecniplast test-api-key request failed with HTTP %d. %s', $statusCode, $this->summarizeErrorBody($decoded, $rawBody, $statusCode)));
        }

        if (!\is_string($decoded) && !\is_array($decoded)) {
            throw new \UnexpectedValueException('Tecniplast test-api-key response must be a JSON string or object.');
        }

        return $decoded;
    }

    public function getMetrics(string $token, ?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', '/tasks/api/1/integration/metrics/', [
            'headers' => [
                'x-api-key' => $token,
                'Content-Type' => 'application/json',
            ],
        ], baseUrl: $baseUrl);
    }

    public function submitTask(string $token, array $payload, ?string $baseUrl = null): array
    {
        return $this->sendRequest('POST', '/tasks/api/1/integration/submit/', [
            'headers' => [
                'x-api-key' => $token,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ], baseUrl: $baseUrl);
    }

    public function getTaskState(string $token, int $taskId, ?string $baseUrl = null): array
    {
        return $this->sendRequest('GET', \sprintf('/tasks/api/1/integration/%d/state', $taskId), [
            'headers' => [
                'x-api-key' => $token,
            ],
        ], baseUrl: $baseUrl);
    }

    public function searchCages(string $token, array $payload, ?string $baseUrl = null): array
    {
        return $this->sendRequest('POST', '/tasks/api/1/integration/cages/search', [
            'headers' => [
                'x-api-key' => $token,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ], baseUrl: $baseUrl);
    }

    public function searchAnimals(string $token, array $payload, ?string $baseUrl = null): array
    {
        return $this->sendRequest('POST', '/tasks/api/1/integration/animals/search', [
            'headers' => [
                'x-api-key' => $token,
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ], baseUrl: $baseUrl);
    }

    public function downloadTaskResult(string $token, int $taskId, ?string $baseUrl = null): ResponseInterface
    {
        return $this->doRequestWithBackoff('GET', \sprintf('/tasks/api/1/integration/%d/download', $taskId), [
            'headers' => [
                'x-api-key' => $token,
            ],
        ], baseUrl: $baseUrl);
    }

    public function toStream(ResponseInterface $response): mixed
    {
        return \Symfony\Component\HttpClient\Response\StreamWrapper::createResource($response, $this->httpClient);
    }
}
