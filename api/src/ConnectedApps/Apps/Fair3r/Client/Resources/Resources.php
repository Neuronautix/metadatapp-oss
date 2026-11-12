<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client\Resources;

use App\ConnectedApps\Apps\Fair3r\Client\Dto\ResourceDto;
use App\ConnectedApps\Apps\Fair3r\Client\Fair3rHttpClientInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Resources
{
    public function __construct(private readonly Fair3rHttpClientInterface $httpClient)
    {
    }

    public function get(string $id): ResourceDto
    {
        $response = $this->httpClient->sendRequest('POST', '/action/resource_show?id=' . $id);

        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        try {
            /** @var ResourceDto $resource */
            $resource = $serializer->denormalize($response->result, ResourceDto::class);
        } catch (\Throwable $e) {
            throw $e; // improve error handling
        }

        return $resource;
    }
}
