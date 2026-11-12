<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Resources;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentCreateRequestDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ExperimentUpdateRequestDto;
use App\ConnectedApps\Apps\Elabftw\Client\ElabftwHttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Experiments
{
    private const string ENDPOINT = 'experiments';

    /**
     * @param array<string, mixed> $requestOptions
     */
    public function __construct(
        private readonly ElabftwHttpClientInterface $httpClient,
        private readonly array $requestOptions = [],
    ) {
    }

    /**
     * @return ExperimentDto[]
     */
    public function list(): array
    {
        $response = $this->httpClient->sendRequest('GET', self::ENDPOINT, $this->requestOptions);
        $data = $response->data;

        if (!\is_array($data)) {
            return [];
        }

        return array_map(fn (array $item): ExperimentDto => $this->denormalize($item), $data);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function get(int $id): ExperimentDto
    {
        $response = $this->httpClient->sendRequest('GET', \sprintf('%s/%d', self::ENDPOINT, $id), $this->requestOptions);

        return $this->denormalize((array) $response->data);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     * @throws \App\ConnectedApps\Apps\Elabftw\Exception\ElabftwException
     */
    public function create(ExperimentCreateRequestDto $dto): ExperimentDto
    {
        $response = $this->httpClient->sendRequest('POST', self::ENDPOINT, array_replace_recursive($this->requestOptions, ['json' => $dto]));
        $id = $response->id ?? ($response->data['id'] ?? null);

        if (null === $id || 0 === $id) {
            throw new \App\ConnectedApps\Apps\Elabftw\Exception\ElabftwException('ElabFTW create experiment returned no valid ID.');
        }

        return $this->denormalize((array) $response->data);
    }

    /**
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function update(int $id, ExperimentUpdateRequestDto $dto): ExperimentDto
    {
        $response = $this->httpClient->sendRequest('PATCH', self::ENDPOINT . '/' . $id, array_replace_recursive($this->requestOptions, ['json' => $dto]));

        return $this->denormalize((array) $response->data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function denormalize(array $data): ExperimentDto
    {
        return new ExperimentDto(
            id: (int) ($data['id'] ?? 0),
            title: (string) ($data['title'] ?? ''),
            body: isset($data['body']) ? (string) $data['body'] : null,
            status: isset($data['status']) ? (int) $data['status'] : null,
            tags: $this->normalizeTags($data['tags'] ?? []),
            category: isset($data['category']) ? (int) $data['category'] : null,
            metadata: $this->normalizeMetadata($data['metadata'] ?? null),
        );
    }

    /**
     * @return list<string>
     */
    private function normalizeTags(mixed $value): array
    {
        if (\is_array($value)) {
            return array_values(array_map('strval', $value));
        }

        if (\is_string($value) && '' !== trim($value)) {
            return array_values(array_filter(array_map('trim', explode('|', str_replace(',', '|', $value)))));
        }

        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMetadata(mixed $value): ?array
    {
        if (\is_array($value)) {
            return $value;
        }

        if (\is_string($value) && '' !== trim($value)) {
            $decoded = json_decode($value, true);

            return \is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
