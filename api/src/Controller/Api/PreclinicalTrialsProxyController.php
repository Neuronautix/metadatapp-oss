<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Apps\PreclinicalTrials\Client\PreclinicalTrialsClient;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class PreclinicalTrialsProxyController extends AbstractController
{
    public function __construct(
        private readonly PreclinicalTrialsClient $client,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function testConnection(ConnectedApp $connectedApp): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);
            $protocols = $this->client->listProtocols($connectedApp);

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $this->client->getViewableProtocolsUrl($connectedApp),
                'protocolsCount' => \count($protocols),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('PreclinicalTrials.eu test-connection failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to validate PreclinicalTrials.eu connection. Check URL and API availability.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    private function assertValidConnectedApp(ConnectedApp $connectedApp): void
    {
        $this->assertConnectedAppAccount($connectedApp);

        if (AppCode::PreclinicalTrials !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not PreclinicalTrials.eu.');
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
}
