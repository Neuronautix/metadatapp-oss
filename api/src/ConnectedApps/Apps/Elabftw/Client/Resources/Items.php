<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Resources;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\CreateItemDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\ItemDto;
use App\ConnectedApps\Apps\Elabftw\Client\Dto\UpdateItemDto;
use App\ConnectedApps\Apps\Elabftw\Client\ElabftwHttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class Items
{
    private const string ENDPOINT = 'items';

    /**
     * @param array<string, mixed> $requestOptions
     */
    public function __construct(
        private readonly ElabftwHttpClientInterface $httpClient,
        private readonly array $requestOptions = [],
    ) {
    }

    /**
     * @return ItemDto[]
     */
    public function list(): array
    {
        $response = $this->httpClient->sendRequest('GET', self::ENDPOINT, $this->requestOptions);
        $data = $response->data;

        if (!\is_array($data)) {
            return [];
        }

        return array_map(fn (array $item): ItemDto => $this->denormalize($item), $data);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function get(int $id): ItemDto
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
    public function create(CreateItemDto $dto): ItemDto
    {
        $response = $this->httpClient->sendRequest('POST', self::ENDPOINT, array_replace_recursive($this->requestOptions, ['json' => $dto->toArray()]));
        $id = $response->id ?? ($response->data['id'] ?? null);

        if (null === $id || 0 === $id) {
            throw new \App\ConnectedApps\Apps\Elabftw\Exception\ElabftwException('ElabFTW create item returned no valid ID.');
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
    public function update(int $id, UpdateItemDto $dto): ItemDto
    {
        $response = $this->httpClient->sendRequest('PATCH', self::ENDPOINT . '/' . $id, array_replace_recursive($this->requestOptions, ['json' => $dto->toArray()]));

        return $this->denormalize((array) $response->data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function denormalize(array $data): ItemDto
    {
        $metadata = isset($data['metadata']) && \is_string($data['metadata'])
            ? (array) json_decode($data['metadata'], true)
            : [];

        return new ItemDto(
            id: (int) ($data['id'] ?? 0),
            title: (string) ($data['title'] ?? ''),
            body: isset($data['body']) ? (string) $data['body'] : null,
            metadata: $metadata,
        );
    }
}
