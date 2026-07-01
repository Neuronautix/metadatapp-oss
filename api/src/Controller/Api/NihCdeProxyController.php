<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Apps\NihCde\Client\NihCdeClientInterface;
use App\Entity\CdeElement;
use App\Entity\ConnectedApp;
use App\Entity\User;
use App\Enum\AppCode;
use App\Repository\CdeElementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class NihCdeProxyController extends AbstractController
{
    public function __construct(
        private readonly NihCdeClientInterface $client,
        private readonly CdeElementRepository $cdeElementRepository,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function testConnection(ConnectedApp $connectedApp): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);
            $result = $this->client->search($connectedApp, 'body weight', 1, 0);

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $this->client->getBaseUrl($connectedApp),
                'cdesCount' => $result['totalItems'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('NIH CDE test-connection failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'Unable to validate NIH CDE connection. Check URL and API availability.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    public function search(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);
            $searchTerm = (string) ($request->query->get('searchTerm') ?? '');
            $resultPerPage = max(1, min(100, (int) ($request->query->get('resultPerPage') ?? 20)));
            $skip = max(0, (int) ($request->query->get('skip') ?? 0));

            if ('' === trim($searchTerm)) {
                return new JsonResponse(['totalItems' => 0, 'elements' => []]);
            }

            $result = $this->client->search($connectedApp, $searchTerm, $resultPerPage, $skip);

            return new JsonResponse($result);
        } catch (\Throwable $e) {
            $this->logger->error('NIH CDE search failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'NIH CDE search failed.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    public function getElement(ConnectedApp $connectedApp, string $tinyId): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);
            $element = $this->client->getElement($connectedApp, $tinyId);

            return new JsonResponse($element);
        } catch (\Throwable $e) {
            $this->logger->error('NIH CDE get-element failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'tinyId' => $tinyId,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'NIH CDE element lookup failed.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    public function importElement(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        try {
            $this->assertValidConnectedApp($connectedApp);
            $body = json_decode((string) $request->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            $tinyId = \is_string($body['tinyId'] ?? null) ? trim($body['tinyId']) : '';

            if ('' === $tinyId) {
                return new JsonResponse(['message' => 'tinyId is required.'], 400);
            }

            $rawElement = $this->client->getElement($connectedApp, $tinyId);
            $account = $connectedApp->getAccount();

            $cdeElement = $this->cdeElementRepository->findByAccountAndTinyId($account, $tinyId)
                ?? new CdeElement();

            $cdeElement
                ->setAccount($account)
                ->setTinyId($tinyId)
                ->setVersion($this->extractString($rawElement, ['version']))
                ->setSource($this->extractString($rawElement, ['stewardOrg.name']) ?? 'NIH')
                ->setName($this->extractName($rawElement) ?? $tinyId)
                ->setDefinition($this->extractDefinition($rawElement))
                ->setPermissibleValues($this->extractPermissibleValues($rawElement))
                ->setRawData($rawElement)
                ->setImportedAt(new \DateTimeImmutable())
            ;

            $this->em->persist($cdeElement);
            $this->em->flush();

            return new JsonResponse([
                'ok' => true,
                'tinyId' => $tinyId,
                'name' => $cdeElement->getName(),
                'id' => $cdeElement->getId()?->toRfc4122(),
            ], 201);
        } catch (\JsonException $e) {
            return new JsonResponse(['message' => 'Invalid JSON body.'], 400);
        } catch (\Throwable $e) {
            $this->logger->error('NIH CDE import failed', [
                'connected_app_id' => (string) $connectedApp->getId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return new JsonResponse([
                'message' => 'NIH CDE import failed.',
                'detail' => $e->getMessage(),
            ], 502);
        }
    }

    private function assertValidConnectedApp(ConnectedApp $connectedApp): void
    {
        $this->assertConnectedAppAccount($connectedApp);

        if (AppCode::NihCde !== $connectedApp->getCode()) {
            throw $this->createAccessDeniedException('App is not NIH CDE.');
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
     * @param array<string, mixed> $element
     * @param list<string>         $keys
     */
    private function extractString(array $element, array $keys): ?string
    {
        foreach ($keys as $key) {
            // Support dot-notation for nested keys
            $parts = explode('.', $key);
            $value = $element;
            foreach ($parts as $part) {
                if (!\is_array($value) || !\array_key_exists($part, $value)) {
                    $value = null;
                    break;
                }
                $value = $value[$part];
            }
            if (\is_scalar($value) && '' !== trim((string) $value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $element
     */
    private function extractName(array $element): ?string
    {
        // Try designations[0].designation first
        $designations = $element['designations'] ?? null;
        if (\is_array($designations) && isset($designations[0]['designation']) && \is_string($designations[0]['designation'])) {
            return trim($designations[0]['designation']);
        }

        return $this->extractString($element, ['name', 'label', 'title']);
    }

    /**
     * @param array<string, mixed> $element
     */
    private function extractDefinition(array $element): ?string
    {
        $definitions = $element['definitions'] ?? null;
        if (\is_array($definitions) && isset($definitions[0]['definition']) && \is_string($definitions[0]['definition'])) {
            return trim($definitions[0]['definition']);
        }

        return $this->extractString($element, ['definition', 'description']);
    }

    /**
     * @param array<string, mixed> $element
     *
     * @return list<mixed>|null
     */
    private function extractPermissibleValues(array $element): ?array
    {
        $pvs = $element['permissibleValues'] ?? null;
        if (!\is_array($pvs) || [] === $pvs) {
            return null;
        }

        return array_values($pvs);
    }
}
