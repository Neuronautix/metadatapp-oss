<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Elabftw\Client\Resources;

use App\ConnectedApps\Apps\Elabftw\Client\Dto\ElabftwResponseDto;
use App\ConnectedApps\Apps\Elabftw\Client\ElabftwHttpClientInterface;
use App\ConnectedApps\Apps\Elabftw\Exception\ElabftwException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

abstract class AbstractResource
{
    protected Serializer $serializer;

    public function __construct(protected readonly ElabftwHttpClientInterface $httpClient)
    {
        $this->serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );
    }

    /**
     * @throws ElabftwException
     */
    protected function send(string $method, string $uri, array $options = []): ElabftwResponseDto
    {
        try {
            $response = $this->httpClient->sendRequest($method, $uri, $options);
        } catch (TransportExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface|ClientExceptionInterface $e) {
            throw new ElabftwException('Request to eLabFTW API failed', 0, $e);
        } catch (\Throwable $e) {
            throw new ElabftwException('Unexpected error communicating with eLabFTW API', 0, $e);
        }

        return $response;
    }

    protected function denormalize(mixed $data, string $type): mixed
    {
        return $this->serializer->denormalize($data, $type);
    }
}
