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
final class CedarProxyController extends AbstractController
{
    private const string DEFAULT_API_URL = 'https://resource.metadatacenter.org';

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

            $payload = $this->httpClient->request('GET', $this->buildApiUrl($connectedApp) . '/users', [
                'headers' => [
                    'Authorization' => 'apiKey ' . $connectedApp->getToken(),
                    'Accept' => 'application/json',
                ],
            ])->toArray(false);

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $this->buildApiUrl($connectedApp),
                'usersCount' => \is_array($payload['users'] ?? null) ? \count($payload['users']) : null,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('CEDAR test-connection failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to validate CEDAR connection. Check URL and API key.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    private function assertValidConnectedApp(ConnectedApp $connectedApp): void
    {
        $this->assertConnectedAppAccount($connectedApp);

        if (AppCode::Cedar !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not CEDAR.');
        }

        if (null === $connectedApp->getToken()) {
            throw $this->createAccessDeniedException('No CEDAR API key configured.');
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

        return null === $externalUrl || '' === trim($externalUrl) ? self::DEFAULT_API_URL : rtrim(trim($externalUrl), '/');
    }
}
