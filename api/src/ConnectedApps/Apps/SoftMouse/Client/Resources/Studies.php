<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Resources;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\SoftMouseResponseDto;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\StudyDto;
use App\ConnectedApps\Apps\SoftMouse\Client\SoftMouseHttpClientInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Studies
{
    private const ENDPOINT = 'external/v1.2/study';

    public function __construct(private readonly SoftMouseHttpClientInterface $httpClient)
    {
    }

    /**
     * @return StudyDto[]
     */
    private function denormalizeResponse(SoftMouseResponseDto $softMouseResponse): array
    {
        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        try {
            /** @var StudyDto[] $studies */
            $studies = $serializer->denormalize($softMouseResponse->respObj, StudyDto::class . '[]');
        } catch (\Throwable $e) {
            return [];
        }

        return $studies;
    }

    /**
     * @return StudyDto[]
     */
    public function all(): array
    {
        $softMouseResponse = $this->httpClient->sendRequest('GET', self::ENDPOINT);

        return $this->denormalizeResponse($softMouseResponse);
    }

    /**
     * @return StudyDto[]
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
     * @return StudyDto[]
     */
    public function search(array $criteria, int $limit = 100, int $page = 1): array
    {
        $softMouseResponse = $this->httpClient->sendRequest('GET', self::ENDPOINT, [
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

    public function get(string $code): ?StudyDto
    {
        $softMouseResponse = $this->httpClient->sendRequest('GET', self::ENDPOINT, [
            'query' => ['code' => $code],
        ]);

        $studies = $this->denormalizeResponse($softMouseResponse);

        return $studies[0] ?? null;
    }
}
