<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Apps\Impc\Client\ImpcClientInterface;
use App\ConnectedApps\Http\ConnectedAppRateLimiter;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Backend proxy for the IMPReSS (IMPC) standardized-screen catalogue.
 *
 * Keeps the upstream API server-side so the frontend can browse standardized
 * phenotyping procedures and parameters for behavioral studies without ever
 * calling mousephenotype.org directly — mirroring the JAX Phenome and NIH CDE
 * standardized-resource proxies.
 */
#[IsGranted('ROLE_USER')]
final class ImpcProxyController extends AbstractController
{
    private const string RATE_BUCKET = 'impc';

    public function __construct(
        private readonly ImpcClientInterface $client,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly ConnectedAppRateLimiter $rateLimiter,
    ) {
    }

    public function testConnection(ConnectedApp $connectedApp): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            // Connectivity probe must hit the upstream live, not a cached read.
            $result = $this->client->listPipelines($connectedApp, 1, 0, false);

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $this->client->getBaseUrl($connectedApp),
                'pipelinesCount' => $result['totalItems'],
            ]);
        } catch (\Throwable $e) {
            return $this->failure('test-connection', $connectedApp, $e, 'Unable to validate IMPReSS connection. Check URL and API availability.');
        }
    }

    public function searchProcedures(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $searchTerm = (string) ($request->query->get('searchTerm') ?? '');
            $limit = max(1, min(100, (int) ($request->query->get('limit') ?? 20)));
            $offset = max(0, (int) ($request->query->get('offset') ?? 0));

            return new JsonResponse($this->client->searchProcedures($connectedApp, $searchTerm, $limit, $offset));
        } catch (\Throwable $e) {
            return $this->failure('procedures-search', $connectedApp, $e, 'IMPReSS procedure search failed.');
        }
    }

    public function getProcedure(ConnectedApp $connectedApp, string $procedureId): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);

            return new JsonResponse($this->client->getProcedure($connectedApp, $procedureId));
        } catch (\Throwable $e) {
            return $this->failure('get-procedure', $connectedApp, $e, 'IMPReSS procedure lookup failed.', ['procedureId' => $procedureId]);
        }
    }

    public function listPipelines(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $limit = max(1, min(100, (int) ($request->query->get('limit') ?? 20)));
            $offset = max(0, (int) ($request->query->get('offset') ?? 0));

            return new JsonResponse($this->client->listPipelines($connectedApp, $limit, $offset));
        } catch (\Throwable $e) {
            return $this->failure('pipelines-list', $connectedApp, $e, 'IMPReSS pipelines list failed.');
        }
    }

    /**
     * @param array<string, scalar> $context
     */
    private function failure(string $operation, ConnectedApp $connectedApp, \Throwable $e, string $message, array $context = []): JsonResponse
    {
        $this->logger->error(\sprintf('IMPReSS %s failed', $operation), [
            'connected_app_id' => (string) $connectedApp->getId(),
            'error' => $e->getMessage(),
            'exception' => $e,
            ...$context,
        ]);

        return new JsonResponse([
            'message' => $message,
            'detail' => $e->getMessage(),
        ], 502);
    }

    private function assertValidConnectedApp(ConnectedApp $connectedApp): void
    {
        $this->assertConnectedAppAccount($connectedApp);

        if (AppCode::Impc !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not IMPReSS.');
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
