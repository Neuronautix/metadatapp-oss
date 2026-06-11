<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SensorAgentClient implements SensorAgentClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $baseUrl,
        private string $token,
        private float $timeout,
        private bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled && '' !== trim($this->baseUrl);
    }

    /**
     * @return array<string, mixed>
     */
    public function getHealth(): array
    {
        return $this->request('/health');
    }

    /**
     * @return array<string, mixed>
     */
    public function getLatestObservation(): array
    {
        return $this->request('/latest');
    }

    /**
     * @return array<string, mixed>
     */
    public function getLatestMetric(string $metric): array
    {
        $metric = $this->normalizeMetric($metric);
        $observation = $this->getLatestObservation();
        $value = $observation[$metric] ?? null;

        if ('temperatureC' === $metric && null === $value) {
            $value = $observation['temperature'] ?? $observation['temp'] ?? null;
        }

        if ('humidityPct' === $metric && null === $value) {
            $value = $observation['humidity'] ?? null;
        }

        if ('pressureHpa' === $metric && null === $value) {
            $value = $observation['pressure'] ?? null;
        }

        return [
            'value' => $value,
            'observedAt' => $observation['observedAt'] ?? $observation['timestamp'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getThresholdStatus(): array
    {
        return $this->request('/latest/threshold-status');
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $path): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('Sensor agent integration is disabled.');
        }

        $startedAt = microtime(true);
        $headers = ['Accept' => 'application/json'];
        if ('' !== trim($this->token)) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        try {
            $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . $path, [
                'headers' => $headers,
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            if ($statusCode >= 400) {
                throw new \RuntimeException(\sprintf('Sensor agent returned HTTP %d.', $statusCode));
            }

            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            // Some upstream metric endpoints can reply with a bare scalar payload.
            // Wrapping it keeps downstream normalization logic on a consistent object shape.
            if (\is_scalar($decoded) || null === $decoded) {
                $decoded = ['value' => $decoded];
            }

            if (!\is_array($decoded)) {
                throw new \RuntimeException('Sensor agent response must decode to an object.');
            }

            $this->logger->debug('Sensor agent request succeeded.', [
                'path' => $path,
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return $decoded;
        } catch (\JsonException|ExceptionInterface|\RuntimeException $exception) {
            $this->logger->warning('Sensor agent request failed.', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);

            throw new \RuntimeException('Unable to fetch live sensor data.', 0, $exception);
        }
    }

    private function normalizeMetric(string $metric): string
    {
        return match ($metric) {
            'temperature' => 'temperatureC',
            'humidity' => 'humidityPct',
            'pressure' => 'pressureHpa',
            default => throw new \InvalidArgumentException(\sprintf('Unsupported sensor metric "%s".', $metric)),
        };
    }
}
