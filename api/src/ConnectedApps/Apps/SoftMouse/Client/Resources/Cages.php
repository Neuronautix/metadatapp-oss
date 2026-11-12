<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Resources;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\CageDto;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\SoftMouseResponseDto;
use App\ConnectedApps\Apps\SoftMouse\Client\SoftMouseHttpClientInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * SoftMouse Cages API wrapper – v1.2 endpoint.
 */
class Cages
{
    private const string ENDPOINT = 'external/v1.2/cage';

    public function __construct(private readonly SoftMouseHttpClientInterface $httpClient)
    {
    }

    /**
     * Fetch *all* cages (no paging) – beware of large datasets.
     *
     * @return CageDto[]
     */
    public function all(): array
    {
        $response = $this->httpClient->sendRequest('GET', self::ENDPOINT);

        return $this->denormalizeResponse($response);
    }

    /**
     * Page‑limited list.
     *
     * @param int $limit numberPerPage param (SoftMouse default 20)
     * @param int $page  pageNo param (1‑based)
     *
     * @return CageDto[]
     */
    public function list(int $limit = 50, int $page = 1): array
    {
        $response = $this->httpClient->sendRequest('GET', self::ENDPOINT, [
            'query' => [
                'numberPerPage' => $limit,
                'pageNo' => $page,
            ],
        ]);

        return $this->denormalizeResponse($response);
    }

    /**
     * Search cages using any supported filter keys (strain, cageTag …).
     *
     * @param array<string, mixed> $criteria SoftMouse query params
     *
     * @return CageDto[]
     */
    public function search(array $criteria, int $limit = 50, int $page = 1): array
    {
        $response = $this->httpClient->sendRequest('GET', self::ENDPOINT, [
            'query' => array_merge($criteria, [
                'numberPerPage' => $limit,
                'pageNo' => $page,
            ]),
        ]);

        return $this->denormalizeResponse($response);
    }

    /**
     * Get a single cage by SID.
     */
    public function get(string $cageSid): ?CageDto
    {
        $json = $this->httpClient->sendRequest('GET', self::ENDPOINT, [
            'query' => ['cageSID' => $cageSid],
        ]);

        $cages = $this->denormalizeResponse($json);

        return $cages[0] ?? null;
    }

    /**
     * @return CageDto[]
     */
    private function denormalizeResponse(SoftMouseResponseDto $softMouseResponse): array
    {
        $serializer = new Serializer([
            new ObjectNormalizer(),
            new ArrayDenormalizer(),
            new DateTimeNormalizer(),
        ], [new JsonEncoder()]);

        $groups = $softMouseResponse->respObj ?? [];
        $data = [];
        foreach ($groups as $group) {
            if (\is_array($group)) {
                $data = array_merge($data, $group);
            }
        }

        return $serializer->denormalize($data, CageDto::class . '[]');
    }
}
