<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client\Resources;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\ProtocolDto;
use App\ConnectedApps\Apps\SoftMouse\Client\Dto\SoftMouseResponseDto;
use App\ConnectedApps\Apps\SoftMouse\Client\SoftMouseHttpClientInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class Protocols
{
    public const string ENDPOINT = 'external/protocol';

    public function __construct(private readonly SoftMouseHttpClientInterface $httpClient)
    {
    }

    /**
     * @return ProtocolDto[]
     */
    public function all(): array
    {
        $data = $this->httpClient->sendRequest('GET', self::ENDPOINT);

        return $this->denormalizeResponse($data);
    }

    public function get(string $id): ProtocolDto
    {
        $data = $this->httpClient->sendRequest('GET', self::ENDPOINT . '/' . $id);

        return $this->denormalizeResponse($data)[0];
    }

    /**
     * @return ProtocolDto[]
     */
    private function denormalizeResponse(SoftMouseResponseDto $softMouseResponse): array
    {
        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        try {
            /** @var ProtocolDto[] $animals */
            $animals = $serializer->denormalize($softMouseResponse->respObj, ProtocolDto::class . '[]');
        } catch (\Throwable $e) {
            return [];
        }

        return $animals;
    }
}
