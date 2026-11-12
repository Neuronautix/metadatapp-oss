<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Client\Mock;

use App\ConnectedApps\Apps\Tecniplast\Client\TecniplastHttpClientInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HttpClientMock extends MockHttpClient implements TecniplastHttpClientInterface
{
    public function __construct()
    {
        $callback = $this->handleRequests(...);
        parent::__construct($callback, 'https://analytics.dvc.tecniplast.it/');
    }

    private function handleRequests(string $method, string $uri): MockResponse
    {
        $json = match (true) {
            str_contains($uri, '/tasks/api/1/integration/test-api-key/') => $this->testApiKeyResponse(),
            str_contains($uri, '/tasks/api/1/integration/metrics/') => $this->metricsList(),
            str_contains($uri, '/tasks/api/1/integration/submit/') => $this->submitTaskResponse(),
            str_contains($uri, '/tasks/api/1/integration/cages/search') => $this->searchResponse(),
            str_contains($uri, '/tasks/api/1/integration/animals/search') => $this->searchResponse(),
            1 === preg_match('/\/tasks\/api\/1\/integration\/\d+\/state/', $uri) => $this->taskStateResponse(),
            1 === preg_match('/\/tasks\/api\/1\/integration\/\d+\/download/', $uri) => $this->downloadTaskResultResponse(),
            default => throw new \RuntimeException('Unhandled request for path ' . $uri),
        };

        $isDownload = 1 === preg_match('/\/tasks\/api\/1\/integration\/\d+\/download/', $uri);

        return new MockResponse(
            $json,
            ['http_code' => 200, 'response_headers' => ['Content-Type' => $isDownload ? 'application/octet-stream' : 'application/json']]
        );
    }

    public function denormalizeResponse(array $content): mixed
    {
        return $content;
    }

    public function getMetrics(string $token, ?string $baseUrl = null): array
    {
        return json_decode($this->metricsList(), true);
    }

    public function testApiKey(string $token, ?string $baseUrl = null): string|array
    {
        return json_decode($this->testApiKeyResponse(), true) ?? 'f1234';
    }

    public function submitTask(string $token, array $payload, ?string $baseUrl = null): array
    {
        return json_decode($this->submitTaskResponse(), true);
    }

    public function getTaskState(string $token, int $taskId, ?string $baseUrl = null): array
    {
        return json_decode($this->taskStateResponse(), true);
    }

    public function searchCages(string $token, array $payload, ?string $baseUrl = null): array
    {
        return json_decode($this->searchResponse(), true);
    }

    public function searchAnimals(string $token, array $payload, ?string $baseUrl = null): array
    {
        return json_decode($this->searchResponse(), true);
    }

    public function downloadTaskResult(string $token, int $taskId, ?string $baseUrl = null): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $this->request('GET', \sprintf('/tasks/api/1/integration/%d/download', $taskId));
    }

    public function toStream(\Symfony\Contracts\HttpClient\ResponseInterface $response): mixed
    {
        return \Symfony\Component\HttpClient\Response\StreamWrapper::createResource($response, $this);
    }

    public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): mixed
    {
        // Handled by MockHttpClient callback
        return [];
    }

    private function testApiKeyResponse(): string
    {
        return '"f1234"';
    }

    private function metricsList(): string
    {
        return <<<'JSON'
        [
            {
                "id": "ACTIVATION",
                "description": "Electrodes average",
                "type": "METRIC"
            },
            {
                "id": "FOOD_BIN",
                "description": "Food consumption events",
                "type": "ENV_PARAM"
            }
        ]
        JSON;
    }

    private function submitTaskResponse(): string
    {
        return <<<'JSON'
        {
            "taskId": 101,
            "requestId": "req-xyz"
        }
        JSON;
    }

    private function taskStateResponse(): string
    {
        return <<<'JSON'
        [
            {
                "id": 101,
                "state": "COMPLETED",
                "createdAt": "2026-02-23T08:00:00Z",
                "finishedAt": "2026-02-23T08:05:00Z",
                "fileSize": 1024,
                "checksum": "abc123sha"
            }
        ]
        JSON;
    }

    private function searchResponse(): string
    {
        return <<<'JSON'
        [
            {
                "uuid": "cage-uuid-1",
                "humanReadableId": "C123",
                "status": "RUNNING",
                "lastEventDate": "2023-01-01T00:00:00Z",
                "registrationEventDate": "2023-01-01T00:00:00Z",
                "rfids": []
            }
        ]
        JSON;
    }

    private function downloadTaskResultResponse(): string
    {
        return "timestamp,metric,value\n2026-02-23T08:00:00Z,ACTIVATION,1.5\n";
    }
}
