<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\SoftMouse\Client;

use App\ConnectedApps\Apps\SoftMouse\Client\Dto\SoftMouseResponseDto;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SoftMouseHttpClient implements SoftMouseHttpClientInterface
{
    private ?string $token = null;

    public function __construct(
        private HttpClientInterface $softMouseClient,
        private LoggerInterface $logger,
        private Authentication $authentication,
    ) {
    }

    public function getSoftMouseClient(): HttpClientInterface
    {
        return $this->softMouseClient;
    }

    public function sendRequest(string $method, string $uri, array $options = [], bool $retryAuth = true): SoftMouseResponseDto
    {
        if (null === $this->token) {
            $this->token = $this->authentication->authenticate($this->softMouseClient);
        }
        $this->logger->debug('Obtained token from SoftMouse.');
        $options['headers']['Authorization'] = \sprintf('Bearer %s', $this->token);
        $options['headers']['Content-Type'] = 'application/json';

        try {
            $this->logger->debug('Sending request to SoftMouse.', [
                'method' => $method,
                'uri' => $uri,
                'options' => $this->redactSensitiveOptions($options),
            ]);
            $responseContent = $this->softMouseClient->request($method, $uri, $options)->getContent();
            $this->logger->debug('Response from SoftMouse: ', ['responseContent' => $responseContent]);
        } catch (\Exception $e) {
            $this->logger->error('Error during request to SoftMouse: ', ['exception' => $e]);
            // todo handle specific exceptions based on http code
            if ($retryAuth) {
                $this->token = null;

                return $this->sendRequest($method, $uri, $options, false);
            }

            throw $e;
        }

        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );
        $softMouseResponseContent = $serializer->decode($responseContent, 'json');

        $softMouseResponse = $this->denormalizeResponse($softMouseResponseContent);

        $this->logger->debug('Denormalized response from SoftMouse: ', ['softMouseResponse' => $softMouseResponse]);
        //        if ($retryAuth
        //            && (
        //                '455' === $softMouseResponse->messageCode
        //                || '401' === $softMouseResponse->messageCode
        //            )
        //        ) {
        //            throw new \Exception('implement authentication retry logic here');
        //            //            $this->authentication->authenticate();
        //            //
        //            //            return $this->sendRequest($method, $uri, $options, false);
        //        }

        //        if ('200' !== $softMouseResponse->messageCode || !$softMouseResponse->respObj) {
        //            // @todo: use a custom exception
        //            throw new \RuntimeException(\sprintf('SoftMouse API error: %s %s', $softMouseResponse->messageCode ?? '', $softMouseResponse->messageDesc ?? ''));
        //        }

        return $softMouseResponse;
    }

    public function denormalizeResponse(array $content): SoftMouseResponseDto
    {
        $serializer = new Serializer(
            [new ObjectNormalizer(), new ArrayDenormalizer()],
            [new JsonEncoder()],
        );

        return $serializer->denormalize($content, SoftMouseResponseDto::class);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function redactSensitiveOptions(array $options): array
    {
        if (isset($options['headers']) && \is_array($options['headers'])) {
            foreach (['Authorization', 'authorization', 'X-API-Key', 'x-api-key'] as $header) {
                if (\array_key_exists($header, $options['headers'])) {
                    $options['headers'][$header] = '[redacted]';
                }
            }
        }

        return $options;
    }
}
