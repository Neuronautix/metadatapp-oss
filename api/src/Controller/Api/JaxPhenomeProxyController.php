<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Apps\JaxPhenome\Client\JaxPhenomeClientInterface;
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

#[IsGranted('ROLE_USER')]
final class JaxPhenomeProxyController extends AbstractController
{
    private const string RATE_BUCKET = 'jax_phenome';

    public function __construct(
        private readonly JaxPhenomeClientInterface $client,
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
            $result = $this->client->listStrains($connectedApp, 1, 0, false);

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $this->client->getBaseUrl($connectedApp),
                'strainsCount' => $result['totalItems'],
            ]);
        } catch (\Throwable $e) {
            return $this->failure('test-connection', $connectedApp, $e, 'Unable to validate JAX Phenome connection. Check URL and API availability.');
        }
    }

    public function searchMeasures(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $searchTerm = (string) ($request->query->get('searchTerm') ?? '');
            $limit = max(1, min(100, (int) ($request->query->get('limit') ?? 20)));
            $offset = max(0, (int) ($request->query->get('offset') ?? 0));

            return new JsonResponse($this->client->searchMeasures($connectedApp, $searchTerm, $limit, $offset));
        } catch (\Throwable $e) {
            return $this->failure('measures-search', $connectedApp, $e, 'JAX Phenome measures search failed.');
        }
    }

    public function getMeasure(ConnectedApp $connectedApp, string $measureId): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);

            return new JsonResponse($this->client->getMeasure($connectedApp, $measureId));
        } catch (\Throwable $e) {
            return $this->failure('get-measure', $connectedApp, $e, 'JAX Phenome measure lookup failed.', ['measureId' => $measureId]);
        }
    }

    public function listStrains(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $limit = max(1, min(100, (int) ($request->query->get('limit') ?? 20)));
            $offset = max(0, (int) ($request->query->get('offset') ?? 0));

            return new JsonResponse($this->client->listStrains($connectedApp, $limit, $offset));
        } catch (\Throwable $e) {
            return $this->failure('strains-list', $connectedApp, $e, 'JAX Phenome strains list failed.');
        }
    }

    /**
     * @param array<string, scalar> $context
     */
    private function failure(string $operation, ConnectedApp $connectedApp, \Throwable $e, string $message, array $context = []): JsonResponse
    {
        $this->logger->error(\sprintf('JAX Phenome %s failed', $operation), [
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

        if (AppCode::JaxPhenome !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not JAX Phenome.');
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
