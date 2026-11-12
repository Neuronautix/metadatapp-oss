<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Apps\Elabftw\Client\Client;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ElabftwProxyController extends AbstractController
{
    public function __construct(
        private readonly Client $client,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function testConnection(ConnectedApp $connectedApp): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);
            $user = $this->client->users($connectedApp)->get('me');

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $connectedApp->getExternalUrl(),
                'user' => [
                    'userid' => $user->userid,
                    'firstname' => $user->firstname,
                    'lastname' => $user->lastname,
                    'email' => $user->email,
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('ElabFTW test-connection failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to validate eLabFTW connection. Check URL and API token.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    private function assertValidConnectedApp(ConnectedApp $connectedApp): void
    {
        $this->assertConnectedAppAccount($connectedApp);

        if (AppCode::Elabftw !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not eLabFTW.');
        }

        $token = $connectedApp->getToken();
        if (null === $token || '' === trim($token)) {
            throw $this->createAccessDeniedException('No eLabFTW API token configured.');
        }

        $externalUrl = $connectedApp->getExternalUrl();
        if (null === $externalUrl || '' === trim($externalUrl)) {
            throw $this->createAccessDeniedException('No eLabFTW external URL configured.');
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
