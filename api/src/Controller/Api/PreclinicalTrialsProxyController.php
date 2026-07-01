<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Apps\PreclinicalTrials\Client\PreclinicalTrialsClientInterface;
use App\ConnectedApps\Apps\PreclinicalTrials\PreclinicalTrialsServiceInterface;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
final class PreclinicalTrialsProxyController extends AbstractController
{
    public function __construct(
        private readonly PreclinicalTrialsClientInterface $client,
        private readonly PreclinicalTrialsServiceInterface $service,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function listProtocols(ConnectedApp $connectedApp): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);

            return new JsonResponse([
                'protocols' => $this->service->listProtocolSummaries($connectedApp),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('PreclinicalTrials.eu protocol listing failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to read PreclinicalTrials.eu protocols.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    public function importProtocol(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);
            $payload = $request->toArray();
            $protocolId = $this->requiredString($payload, 'protocolId');
            $target = $this->optionalString($payload, 'target') ?? 'new-investigation';

            $result = match ($target) {
                'new-investigation' => $this->service->importSelectedProtocol($connectedApp, $protocolId),
                'existing-investigation' => $this->service->importSelectedProtocol($connectedApp, $protocolId, $this->requiredUuid($payload, 'investigationId')),
                'existing-study' => $this->service->linkSelectedProtocolToStudy($connectedApp, $protocolId, $this->requiredUuid($payload, 'studyId')),
                default => throw new \InvalidArgumentException('Unsupported PreclinicalTrials.eu import target.'),
            };

            return new JsonResponse([
                'ok' => true,
                'target' => $result,
            ]);
        } catch (\InvalidArgumentException|\JsonException $e) {
            return new JsonResponse([
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            $this->logger->error('PreclinicalTrials.eu protocol import failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to import PreclinicalTrials.eu protocol.',
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
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

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!\is_scalar($value) || '' === trim((string) $value)) {
            throw new \InvalidArgumentException(\sprintf('Missing required field "%s".', $key));
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if (null === $value || !\is_scalar($value) || '' === trim((string) $value)) {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredUuid(array $payload, string $key): Uuid
    {
        $value = $this->requiredString($payload, $key);
        try {
            return Uuid::fromString($value);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException(\sprintf('Field "%s" must be a valid UUID.', $key));
        }
    }
}
