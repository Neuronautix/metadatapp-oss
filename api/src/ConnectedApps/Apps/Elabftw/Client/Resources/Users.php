<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Resources;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\UserDto;
use App\ConnectedApps\Apps\Elabftw\Exception\ElabftwException;

class Users extends AbstractResource
{
    private const string ENDPOINT = 'users';

    /**
     * @param array<string, mixed> $requestOptions
     */
    public function __construct(
        \App\ConnectedApps\Apps\Elabftw\Client\ElabftwHttpClientInterface $httpClient,
        private readonly array $requestOptions = [],
    ) {
        parent::__construct($httpClient);
    }

    /**
     * @return UserDto[]
     *
     * @throws ElabftwException
     */
    public function list(int $page = 1, int $limit = 20): array
    {
        $response = $this->send('GET', self::ENDPOINT, array_replace_recursive($this->requestOptions, ['query' => ['page' => $page, 'limit' => $limit]]));

        return $this->denormalize($response->data, UserDto::class . '[]');
    }

    /**
     * @throws ElabftwException
     */
    public function get(int|string $id): UserDto
    {
        $response = $this->send('GET', self::ENDPOINT . '/' . $id, $this->requestOptions);

        return $this->denormalize($response->data, UserDto::class);
    }
}
