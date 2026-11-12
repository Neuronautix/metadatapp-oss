<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Fair3r\Client;

use App\ConnectedApps\Apps\Fair3r\Client\Dto\Fair3rResponseDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class Fair3rHttpClient implements Fair3rHttpClientInterface
{
    public function __construct(
        private HttpClientInterface $fair3rClient,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws \JsonException
     */
    public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): Fair3rResponseDto
    {
        $this->logger->debug('Sending request to Fair3r.', [
            'method' => $method,
            'uri' => $uri,
            'options' => $this->redactOptions($options),
        ]);
        $responseContent = $this->fair3rClient->request($method, $uri, $options)->getContent(false);
        $this->logger->debug('Fair3r Response: ', ['responseContent' => $responseContent]);

        return $this->reponseToDto($responseContent);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function redactOptions(array $options): array
    {
        if (isset($options['headers']) && \is_array($options['headers'])) {
            foreach (['Authorization', 'authorization', 'X-CKAN-API-Key', 'x-ckan-api-key'] as $header) {
                if (isset($options['headers'][$header])) {
                    $options['headers'][$header] = 'REDACTED';
                }
            }
        }

        return $options;
    }

    public function denormalizeResponse(array $content): Fair3rResponseDto
    {
        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        return $serializer->denormalize($content, Fair3rResponseDto::class);
    }

    protected function reponseToDto(string $responseContent): Fair3rResponseDto
    {
        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );
        $fair3rResponseContent = $serializer->decode($responseContent, 'json');
        if (\is_array($fair3rResponseContent)
            && !isset($fair3rResponseContent['result'])
            && (isset($fair3rResponseContent['fields']) || isset($fair3rResponseContent['records']))
        ) {
            $fair3rResponseContent = [
                'help' => null,
                'success' => true,
                'result' => $fair3rResponseContent,
            ];
        }

        return $this->denormalizeResponse($fair3rResponseContent);
    }
}
