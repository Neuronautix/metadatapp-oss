<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\PreclinicalTrials\Client;

use App\Entity\ConnectedApp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PreclinicalTrialsClient
{
    private const string DEFAULT_API_URL = 'https://preclinicaltrials.eu/api';
    private const string VIEWABLE_PROTOCOLS_PATH = '/external/viewable-protocols';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getBaseUrl(ConnectedApp $connectedApp): string
    {
        $externalUrl = $connectedApp->getExternalUrl();

        return null === $externalUrl || '' === trim($externalUrl) ? self::DEFAULT_API_URL : rtrim(trim($externalUrl), '/');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProtocols(ConnectedApp $connectedApp): array
    {
        $payload = $this->requestAbsolute($connectedApp, 'GET', $this->getViewableProtocolsUrl($connectedApp));
        $protocols = $payload['protocols'] ?? $payload['data'] ?? $payload;

        if (!\is_array($protocols)) {
            return [];
        }

        $items = [];
        foreach ($protocols as $protocol) {
            if (\is_array($protocol)) {
                $items[] = $protocol;
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProtocol(ConnectedApp $connectedApp, string $protocolId): array
    {
        $payload = $this->request($connectedApp, 'GET', self::VIEWABLE_PROTOCOLS_PATH . '/' . rawurlencode($protocolId));
        $protocol = $payload['protocol'] ?? $payload['data'] ?? $payload;

        return \is_array($protocol) ? $protocol : [];
    }

    public function getViewableProtocolsUrl(ConnectedApp $connectedApp): string
    {
        $baseUrl = $this->getBaseUrl($connectedApp);

        return str_ends_with($baseUrl, self::VIEWABLE_PROTOCOLS_PATH)
            ? $baseUrl
            : $baseUrl . self::VIEWABLE_PROTOCOLS_PATH;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(ConnectedApp $connectedApp, string $method, string $path): array
    {
        return $this->requestAbsolute($connectedApp, $method, $this->getBaseUrl($connectedApp) . $path);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestAbsolute(ConnectedApp $connectedApp, string $method, string $url): array
    {
        $headers = ['Accept' => 'application/json'];
        $token = $connectedApp->getToken();
        if (null !== $token && '' !== trim($token)) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = $this->httpClient->request($method, $url, [
            'headers' => $headers,
        ]);
        $payload = $response->toArray(false);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $message = \is_string($payload['message'] ?? null) ? $payload['message'] : 'PreclinicalTrials.eu request failed.';
            throw new \RuntimeException($message);
        }

        return $payload;
    }
}
