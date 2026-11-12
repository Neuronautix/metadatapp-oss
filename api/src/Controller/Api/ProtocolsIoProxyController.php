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
final class ProtocolsIoProxyController extends AbstractController
{
    private const string DEFAULT_API_URL = 'https://www.protocols.io/api/v3';

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

            $payload = $this->httpClient->request('GET', $this->buildApiUrl($connectedApp) . '/session/profile', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $connectedApp->getToken(),
                    'Accept' => 'application/json',
                ],
            ])->toArray(false);

            $user = \is_array($payload['user'] ?? null) ? $payload['user'] : $payload;
            if ([] === $user) {
                throw new \RuntimeException('protocols.io did not return a user profile.');
            }

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $connectedApp->getExternalUrl() ?: 'https://www.protocols.io',
                'user' => [
                    'username' => $this->stringOrNull($user['username'] ?? null),
                    'name' => $this->stringOrNull($user['name'] ?? null),
                    'affiliation' => $this->stringOrNull($user['affiliation'] ?? null),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('protocols.io test-connection failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to validate protocols.io connection. Check URL and API token.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    private function assertValidConnectedApp(ConnectedApp $connectedApp): void
    {
        $this->assertConnectedAppAccount($connectedApp);

        if (AppCode::ProtocolIo !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not protocols.io.');
        }

        if (null === $connectedApp->getToken()) {
            throw $this->createAccessDeniedException('No protocols.io API token configured.');
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

        return str_contains($trimmedUrl, '/api/') ? $trimmedUrl : $trimmedUrl . '/api/v3';
    }

    private function stringOrNull(mixed $value): ?string
    {
        return \is_scalar($value) && '' !== trim((string) $value) ? trim((string) $value) : null;
    }
}
