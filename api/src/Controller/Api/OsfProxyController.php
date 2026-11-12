<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[IsGranted('ROLE_USER')]
class OsfProxyController extends AbstractController
{
    private const DEFAULT_API_URL = 'https://api.osf.io/v2';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function testConnection(ConnectedApp $connectedApp): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);

            $response = $this->httpClient->request('GET', $this->buildApiUrl($connectedApp) . '/users/me/', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $connectedApp->getToken(),
                    'Accept' => 'application/json',
                ],
            ]);
            $payload = $response->toArray(false);
            $data = \is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $attributes = \is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

            if (($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) || [] === $data) {
                throw new \RuntimeException('OSF did not return the current user.');
            }

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $connectedApp->getExternalUrl() ?: 'https://osf.io',
                'user' => [
                    'id' => $data['id'] ?? null,
                    'fullName' => $attributes['full_name'] ?? null,
                    'email' => $attributes['email'] ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('OSF test-connection failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to validate OSF connection. Check URL and API token.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    private function assertValidConnectedApp(ConnectedApp $connectedApp): void
    {
        $this->assertConnectedAppAccount($connectedApp);

        if (AppCode::Osf !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not OSF.');
        }

        $token = $connectedApp->getToken();
        if (null === $token || '' === trim($token)) {
            throw $this->createAccessDeniedException('No OSF API token configured.');
        }
    }

    private function assertConnectedAppAccount(ConnectedApp $connectedApp): void
    {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $connectedApp->getAccount()->getId()?->toRfc4122() !== $user->getAccount()->getId()?->toRfc4122()) {
            throw $this->createAccessDeniedException('Connected app does not belong to the current account.');
        }
    }

    private function buildApiUrl(ConnectedApp $connectedApp): string
    {
        $externalUrl = $connectedApp->getExternalUrl();
        if (null === $externalUrl || '' === trim($externalUrl)) {
            return self::DEFAULT_API_URL;
        }

        $trimmedUrl = rtrim(trim($externalUrl), '/');

        return str_contains($trimmedUrl, 'api.osf.io') ? $trimmedUrl : self::DEFAULT_API_URL;
    }
}
