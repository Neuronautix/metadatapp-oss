<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Osf\Client;

use App\Entity\ConnectedApp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OsfClient implements OsfClientInterface
{
    private const string DEFAULT_API_URL = 'https://api.osf.io/v2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getCurrentUser(ConnectedApp $connectedApp): array
    {
        $payload = $this->request($connectedApp, 'GET', '/users/me/');
        $data = $payload['data'] ?? [];

        return \is_array($data) ? $data : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listProjects(ConnectedApp $connectedApp): array
    {
        $items = [];
        $url = $this->getBaseApiUrl($connectedApp) . '/users/me/nodes/';
        $options = [
            'query' => [
                'page[size]' => 100,
            ],
        ];

        while (null !== $url) {
            $payload = $this->requestAbsolute($connectedApp, $url, $options);
            $options = [];

            $data = $payload['data'] ?? [];
            if (\is_array($data)) {
                foreach ($data as $item) {
                    if (\is_array($item)) {
                        $items[] = $item;
                    }
                }
            }

            $links = \is_array($payload['links'] ?? null) ? $payload['links'] : [];
            $next = $links['next'] ?? null;
            $url = \is_string($next) && '' !== trim($next) ? $next : null;
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listRegistrationSchemas(ConnectedApp $connectedApp): array
    {
        $url = $this->getBaseApiUrl($connectedApp) . '/schemas/registrations/?filter[active]=true&page[size]=100';
        $schemas = [];
        while ($url) {
            $response = $this->requestAbsolute($connectedApp, $url);
            foreach ($response['data'] ?? [] as $schema) {
                $schemas[] = $schema;
            }
            $url = $response['links']['next'] ?? null;
        }

        return $schemas;
    }

    public function getBaseApiUrl(ConnectedApp $connectedApp): string
    {
        $externalUrl = $connectedApp->getExternalUrl();
        if (null === $externalUrl || '' === trim($externalUrl)) {
            return self::DEFAULT_API_URL;
        }

        $trimmedUrl = rtrim(trim($externalUrl), '/');

        return str_contains($trimmedUrl, 'api.osf.io') ? $trimmedUrl : self::DEFAULT_API_URL;
    }

    /**
     * @return array<string, mixed>
     */
    private function request(ConnectedApp $connectedApp, string $method, string $path): array
    {
        return $this->requestAbsolute($connectedApp, $this->getBaseApiUrl($connectedApp) . $path, ['method' => $method]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function requestAbsolute(ConnectedApp $connectedApp, string $url, array $options = []): array
    {
        $token = $connectedApp->getToken();
        if (null === $token || '' === trim($token)) {
            throw new \RuntimeException('OSF API token is missing for connected app.');
        }

        $method = \is_string($options['method'] ?? null) ? (string) $options['method'] : 'GET';
        unset($options['method']);

        $response = $this->httpClient->request($method, $url, array_replace_recursive([
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ], $options));

        $payload = $response->toArray(false);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $message = \is_string($payload['message'] ?? null) ? $payload['message'] : 'OSF request failed.';
            throw new \RuntimeException($message);
        }

        return $payload;
    }
}
