<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Resources;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\AnimalDto;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\SoftMouseResponseDto;
use App\ConnectedApps\Apps\SoftMouse\Client\SoftMouseHttpClientInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Animals
{
    private const ENDPOINT = 'external/v1.3/animal';
    private const PUT_ENDPOINT = 'external/v1.2/animal';

    public function __construct(private readonly SoftMouseHttpClientInterface $httpClient)
    {
    }

    /**
     * @return AnimalDto[]
     */
    private function denormalizeResponse(SoftMouseResponseDto $softMouseResponse): array
    {
        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        try {
            /** @var AnimalDto[] $animals */
            $animals = $serializer->denormalize($softMouseResponse->respObj, AnimalDto::class . '[]');
        } catch (\Throwable $e) {
            //            $this->logger->error('Error denormalizing SoftMouse response', [
            //                'exception' => $e,
            //                'response' => $softMouseResponse,
            //            ]);
            //            return [];
            throw $e;
        }

        return $animals;
    }

    /**
     * @return AnimalDto[]
     */
    public function all(): array
    {
        $softMouseResponse = $this->httpClient->sendRequest('GET', self::ENDPOINT);

        return $this->denormalizeResponse($softMouseResponse);
    }

    /**
     * @return AnimalDto[]
     */
    public function list(int $limit = 100, int $page = 1): array
    {
        $softMouseResponse = $this->httpClient->sendRequest('GET', self::ENDPOINT, [
            'query' => [
                'pageSize' => $limit,
                'pageIndex' => $page,
            ],
        ]);

        return $this->denormalizeResponse($softMouseResponse);
    }

    /**
     * @param array<string, string> $criteria
     *
     * @return AnimalDto[]
     */
    public function search(array $criteria, int $limit = 100, int $page = 1): array
    {
        $softMouseResponse = $this->httpClient->sendRequest('GET',
            self::ENDPOINT, [
                'query' => array_merge(
                    $criteria,
                    [
                        'numberPerPage' => $limit,
                        'pageNo' => $page,
                    ],
                ),
            ]);

        return $this->denormalizeResponse($softMouseResponse);
    }

    /** ------------------------------------------------------------------
     *  4) One animal by SoftMouse SID
     * ------------------------------------------------------------------*/
    public function get(string $id): ?AnimalDto
    {
        $softMouseResponse = $this->httpClient->sendRequest('GET', self::ENDPOINT, [
            'query' => ['animalSID' => $id],
        ]);

        $animals = $this->denormalizeResponse($softMouseResponse);

        return $animals[0] ?? null;
    }

    /**
     * The End Animal call is used to end one, or group of queried animals given an End Date and Type. The API user must have Edit access on those records.
     *
     * @param array<string> $ids
     */
    public function putEnd(array $ids, ?string $endDate = null, ?string $type = null): void
    {
        if ([] === $ids) {
            throw new \InvalidArgumentException('No animal IDs provided');
        }
        $query = [
            'animalSID' => implode(',', $ids),
            'type' => $type,
        ];

        $this->httpClient->sendRequest('PUT', self::PUT_ENDPOINT, [
            'query' => $query,
            'json' => [
                'endType' => 'Missing',
                'endReason' => 'Flood',
            ],
        ]);
    }
}
