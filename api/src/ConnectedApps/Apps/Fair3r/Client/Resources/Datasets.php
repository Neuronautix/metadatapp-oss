<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Resources;

use App\ConnectedApps\Apps\Fair3r\Client\Dto\DatasetDto;
use App\ConnectedApps\Apps\Fair3r\Client\Fair3rHttpClientInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

readonly class Datasets
{
    public function __construct(private Fair3rHttpClientInterface $httpClient)
    {
    }

    /**
     * @return array<DatasetDto> List of datasets
     */
    public function list(): array
    {
        $fair3rResponse = $this->httpClient->sendRequest('POST', '/action/package_list');

        // https://chatgpt.com/g/g-p-676212f8d8848191aba02f03781b8dab-mapp/c/683b2d03-dd94-8006-8182-8583d65dd428
        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        try {
            /** @var DatasetDto[] $datasets */
            $datasets = $serializer->denormalize($fair3rResponse->result, DatasetDto::class . '[]');
        } catch (\Throwable $e) {
            return [];
        }

        return $datasets;
    }

    public function get(string $idOrName): ?DatasetDto
    {
        $fair3rResponse = $this->httpClient->sendRequest('GET', '/action/package_show?id=' . $idOrName);

        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        try {
            /** @var DatasetDto $dataset */
            $dataset = $serializer->denormalize($fair3rResponse->result, DatasetDto::class);
        } catch (\Throwable $e) {
            return null;
        }

        return $dataset;
    }

    public function show(string $idOrName): ?DatasetDto
    {
        return $this->get($idOrName);
    }
}
