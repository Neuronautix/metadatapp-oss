<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Tecniplast\Client;

use App\ConnectedApps\Shared\ConnectedAppHttpClientInterface;

interface TecniplastHttpClientInterface extends ConnectedAppHttpClientInterface
{
    /**
     * @param string $token The API key
     */
    public function getMetrics(string $token, ?string $baseUrl = null): array;

    /**
     * @param string $token The API key
     */
    public function testApiKey(string $token, ?string $baseUrl = null): string|array;

    /**
     * @param string $token   The API key
     * @param array  $payload The task submission payload
     */
    public function submitTask(string $token, array $payload, ?string $baseUrl = null): array;

    /**
     * @param string $token  The API key
     * @param int    $taskId The task ID
     */
    public function getTaskState(string $token, int $taskId, ?string $baseUrl = null): array;

    /**
     * @param string $token   The API key
     * @param array  $payload The search payload
     */
    public function searchCages(string $token, array $payload, ?string $baseUrl = null): array;

    /**
     * @param string $token   The API key
     * @param array  $payload The search payload
     */
    public function searchAnimals(string $token, array $payload, ?string $baseUrl = null): array;

    /**
     * @param string $token  The API key
     * @param int    $taskId The task ID
     *
     * @return \Symfony\Contracts\HttpClient\ResponseInterface The raw response to stream
     */
    public function downloadTaskResult(string $token, int $taskId, ?string $baseUrl = null): \Symfony\Contracts\HttpClient\ResponseInterface;

    /**
     * @return resource|false The stream resource
     */
    public function toStream(\Symfony\Contracts\HttpClient\ResponseInterface $response): mixed;
}
