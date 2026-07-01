<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\NihCde\Client;

use App\Entity\ConnectedApp;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NihCdeClient implements NihCdeClientInterface
{
    private const string DEFAULT_BASE_URL = 'https://cde.nlm.nih.gov';
    private const string SEARCH_PATH = '/server/de/search';
    private const string FORM_SEARCH_PATH = '/server/form/search';
    private const string ELEMENT_PATH = '/api/de/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function getBaseUrl(ConnectedApp $connectedApp): string
    {
        $externalUrl = $connectedApp->getExternalUrl();
        if (null === $externalUrl || '' === trim($externalUrl)) {
            return self::DEFAULT_BASE_URL;
        }

        $url = rtrim(trim($externalUrl), '/');

        // Strip legacy /api suffix — the search API moved to /server/de/search
        if (str_ends_with($url, '/api')) {
            $url = substr($url, 0, -4);
        }

        return $url;
    }

    /**
     * @return array{totalItems: int, elements: list<array<string, mixed>>}
     */
    public function search(ConnectedApp $connectedApp, string $searchTerm, int $resultPerPage = 20, int $skip = 0): array
    {
        $payload = $this->request($connectedApp, 'POST', self::SEARCH_PATH, json: [
            'searchTerm' => $searchTerm,
            'resultPerPage' => $resultPerPage,
            'skip' => $skip,
        ]);

        $elements = $payload['cdes'] ?? $payload['elements'] ?? $payload['results'] ?? [];
        if (!\is_array($elements)) {
            $elements = [];
        }

        $items = [];
        foreach ($elements as $element) {
            if (\is_array($element)) {
                $items[] = $element;
            }
        }

        return [
            'totalItems' => \is_int($payload['totalItems'] ?? null) ? $payload['totalItems'] : \count($items),
            'elements' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getElement(ConnectedApp $connectedApp, string $tinyId): array
    {
        return $this->request($connectedApp, 'GET', self::ELEMENT_PATH . rawurlencode($tinyId));
    }

    /**
     * @return array{totalItems?: int, forms: list<array<string, mixed>>}
     */
    public function searchForms(ConnectedApp $connectedApp, string $searchTerm, int $resultPerPage = 20, int $skip = 0): array
    {
        $payload = $this->request($connectedApp, 'POST', self::FORM_SEARCH_PATH, json: [
            'searchTerm' => $searchTerm,
            'resultPerPage' => $resultPerPage,
            'skip' => $skip,
        ]);

        $forms = $payload['forms'] ?? [];
        if (!\is_array($forms)) {
            $forms = [];
        }

        $items = [];
        foreach ($forms as $form) {
            if (\is_array($form)) {
                $items[] = $form;
            }
        }

        return ['forms' => $items];
    }

    /**
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $json
     *
     * @return array<string, mixed>
     */
    private function request(ConnectedApp $connectedApp, string $method, string $path, array $query = [], ?array $json = null): array
    {
        $options = [
            'headers' => ['Accept' => 'application/json'],
        ];

        if ([] !== $query) {
            $options['query'] = $query;
        }

        if (null !== $json) {
            $options['json'] = $json;
        }

        $token = $connectedApp->getToken();
        if (null !== $token && '' !== trim($token)) {
            $options['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = $this->httpClient->request($method, $this->getBaseUrl($connectedApp) . $path, $options);
        $payload = $response->toArray(false);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            $message = \is_string($payload['message'] ?? null) ? $payload['message'] : 'NIH CDE API request failed.';
            throw new \RuntimeException($message);
        }

        return $payload;
    }
}
