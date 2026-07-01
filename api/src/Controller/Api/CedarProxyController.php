<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\ConnectedApps\Apps\Cedar\Client\CedarClientInterface;
use App\ConnectedApps\Apps\Cedar\Enum\CedarArtifactType;
use App\ConnectedApps\Apps\Cedar\Export\CanonicalFormCedarTemplateBuilder;
use App\ConnectedApps\Http\ConnectedAppRateLimiter;
use App\Crosswalk\CrosswalkBootstrapper;
use App\Entity\CanonicalForm;
use App\Entity\ConnectedApp;
use App\Entity\ExternalTemplate;
use App\Entity\ExtractedTemplateField;
use App\Entity\User;
use App\Enum\AppCode;
use App\Enum\ExternalTemplateFormat;
use App\Enum\ExternalTemplateSource;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Proxies the CEDAR resource-server REST API behind Metadatapp's account
 * isolation. The artifact surface (fetch / create / update / delete for
 * templates, elements, fields and instances, plus validation) mirrors the
 * official `cedar-artifact-rest-mcp` one-to-one, so anything that targets
 * CEDAR or its MCP works through Metadatapp too.
 *
 * @see https://github.com/metadatacenter/cedar-artifact-rest-mcp
 */
#[IsGranted('ROLE_USER')]
final class CedarProxyController extends AbstractController
{
    private const string RATE_BUCKET = 'cedar';

    public function __construct(
        private readonly CedarClientInterface $client,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly CrosswalkBootstrapper $crosswalkBootstrapper,
        private readonly CanonicalFormCedarTemplateBuilder $templateBuilder,
        private readonly ConnectedAppRateLimiter $rateLimiter,
    ) {
    }

    public function testConnection(ConnectedApp $connectedApp): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $payload = $this->client->getUsers($connectedApp);

            return new JsonResponse([
                'ok' => true,
                'externalUrl' => $this->client->getBaseUrl($connectedApp),
                'usersCount' => \is_array($payload['users'] ?? null) ? \count($payload['users']) : null,
            ]);
        } catch (\Throwable $e) {
            return $this->failure('test-connection', $connectedApp, $e, 'Unable to validate CEDAR connection. Check URL and API key.');
        }
    }

    public function getArtifact(ConnectedApp $connectedApp, Request $request, string $type): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $artifactType = $this->resolveType($type);
            $iri = trim((string) ($request->query->get('iri') ?? ''));
            if ('' === $iri) {
                return new JsonResponse(['message' => 'Query parameter "iri" is required.'], 400);
            }

            return new JsonResponse(['artifact' => $this->client->getArtifact($connectedApp, $artifactType, $iri)]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->failure('get-artifact', $connectedApp, $e, 'Unable to fetch CEDAR artifact.');
        }
    }

    public function createArtifact(ConnectedApp $connectedApp, Request $request, string $type): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $artifactType = $this->resolveType($type);
            $artifact = $this->readArtifactBody($request);

            return new JsonResponse(['artifact' => $this->client->createArtifact($connectedApp, $artifactType, $artifact)], 201);
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Invalid JSON body.'], 400);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->failure('create-artifact', $connectedApp, $e, 'Unable to create CEDAR artifact.');
        }
    }

    public function updateArtifact(ConnectedApp $connectedApp, Request $request, string $type): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $artifactType = $this->resolveType($type);
            $body = $this->decodeBody($request);
            $iri = \is_string($body['iri'] ?? null) ? trim($body['iri']) : '';
            $artifact = $body['artifact'] ?? null;
            if ('' === $iri) {
                return new JsonResponse(['message' => '"iri" is required.'], 400);
            }
            if (!\is_array($artifact) || [] === $artifact) {
                return new JsonResponse(['message' => '"artifact" object is required.'], 400);
            }

            return new JsonResponse(['artifact' => $this->client->updateArtifact($connectedApp, $artifactType, $iri, $artifact)]);
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Invalid JSON body.'], 400);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->failure('update-artifact', $connectedApp, $e, 'Unable to update CEDAR artifact.');
        }
    }

    public function deleteArtifact(ConnectedApp $connectedApp, Request $request, string $type): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $artifactType = $this->resolveType($type);
            $body = $this->decodeBody($request);
            $iri = \is_string($body['iri'] ?? null) ? trim($body['iri']) : '';
            if ('' === $iri) {
                return new JsonResponse(['message' => '"iri" is required.'], 400);
            }

            $this->client->deleteArtifact($connectedApp, $artifactType, $iri);

            return new JsonResponse(['ok' => true, 'deleted' => $iri]);
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Invalid JSON body.'], 400);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->failure('delete-artifact', $connectedApp, $e, 'Unable to delete CEDAR artifact.');
        }
    }

    public function validateArtifact(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $body = $this->decodeBody($request);
            $artifact = $body['artifact'] ?? null;
            if (!\is_array($artifact) || [] === $artifact) {
                return new JsonResponse(['message' => '"artifact" object is required.'], 400);
            }

            $type = \is_string($body['type'] ?? null) ? CedarArtifactType::tryFrom(strtolower(trim($body['type']))) : null;

            return new JsonResponse($this->client->validateArtifact($connectedApp, $artifact, $type));
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Invalid JSON body.'], 400);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->failure('validate-artifact', $connectedApp, $e, 'Unable to validate CEDAR artifact.');
        }
    }

    /**
     * Fetch a CEDAR template by IRI and persist it locally as an
     * {@see ExternalTemplate} so it can be crosswalked into Metadatapp.
     */
    public function importTemplate(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $body = $this->decodeBody($request);
            $iri = \is_string($body['iri'] ?? null) ? trim($body['iri']) : '';
            if ('' === $iri) {
                return new JsonResponse(['message' => '"iri" is required.'], 400);
            }

            $template = $this->client->getArtifact($connectedApp, CedarArtifactType::Template, $iri);

            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return new JsonResponse(['message' => 'Authenticated user required.'], 403);
            }

            // The security token's user is detached under the stateless firewall;
            // load the managed instance so the account/user associations persist
            // cleanly (mirrors SetUserProcessor / ElnImportService).
            $managedUser = null !== $user->getId() ? $this->em->find(User::class, $user->getId()) : null;
            if (!$managedUser instanceof User) {
                return new JsonResponse(['message' => 'Authenticated user could not be resolved.'], 403);
            }

            $externalTemplate = (new ExternalTemplate())
                ->setSource(ExternalTemplateSource::CEDAR)
                ->setFormat(ExternalTemplateFormat::CEDAR_TEMPLATE)
                ->setExternalUrl($iri)
                ->setTitle($this->extractString($template, ['schema:name', 'title', 'name']) ?? 'CEDAR template')
                ->setDescription($this->extractString($template, ['schema:description', 'description']))
                ->setVersion($this->extractString($template, ['pav:version', 'schema:version', 'version']))
                ->setRawPayload($template)
                ->setFileHash(hash('sha256', $iri))
                ->setImportedAt(new \DateTimeImmutable())
                ->setAccount($managedUser->getAccount())
                ->setUser($managedUser)
            ;

            foreach ($this->extractCedarFields($template) as $field) {
                $externalTemplate->addExtractedField($field);
            }

            $this->em->persist($externalTemplate);

            // Give the imported template an immediate, reviewable entry in the
            // Crosswalk Studio (a draft canonical form + suggested crosswalk), so
            // it can actually be mapped — same flow as the ELN import.
            $crosswalk = $this->crosswalkBootstrapper->bootstrapDraft($externalTemplate, $managedUser);

            $this->em->flush();

            return new JsonResponse([
                'ok' => true,
                'id' => $externalTemplate->getId()?->toRfc4122(),
                'title' => $externalTemplate->getTitle(),
                'externalUrl' => $iri,
                'fieldCount' => $externalTemplate->getExtractedFields()->count(),
                'crosswalkId' => $crosswalk->getId()?->toRfc4122(),
            ], 201);
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Invalid JSON body.'], 400);
        } catch (\Throwable $e) {
            return $this->failure('import-template', $connectedApp, $e, 'Unable to import CEDAR template.');
        }
    }

    /**
     * Write a Metadatapp {@see CanonicalForm} back to CEDAR as a template
     * (the inverse of {@see self::importTemplate()}). Creates a new template, or
     * updates an existing one when an `iri` is supplied.
     */
    public function exportForm(ConnectedApp $connectedApp, Request $request): JsonResponse
    {
        $this->rateLimiter->consume($connectedApp, self::RATE_BUCKET);

        try {
            $this->assertValidConnectedApp($connectedApp);
            $body = $this->decodeBody($request);

            $formId = \is_string($body['canonicalFormId'] ?? null) ? trim($body['canonicalFormId']) : '';
            if ('' === $formId || !Uuid::isValid($formId)) {
                return new JsonResponse(['message' => 'A valid "canonicalFormId" is required.'], 400);
            }

            $form = $this->em->getRepository(CanonicalForm::class)->find(Uuid::fromString($formId));
            if (!$form instanceof CanonicalForm) {
                return new JsonResponse(['message' => 'Canonical form not found.'], 404);
            }

            // Tenant isolation: only export a form owned by the same account as
            // the CEDAR connection.
            if ($form->getAccount()->getId()?->toRfc4122() !== $connectedApp->getAccount()->getId()?->toRfc4122()) {
                return new JsonResponse(['message' => 'Canonical form does not belong to the connected app account.'], 403);
            }

            $template = $this->templateBuilder->build($form);

            $iri = \is_string($body['iri'] ?? null) ? trim($body['iri']) : '';
            if ('' !== $iri) {
                $artifact = $this->client->updateArtifact($connectedApp, CedarArtifactType::Template, $iri, $template);
                $status = 200;
            } else {
                $artifact = $this->client->createArtifact($connectedApp, CedarArtifactType::Template, $template);
                $status = 201;
            }

            return new JsonResponse([
                'ok' => true,
                'id' => \is_string($artifact['@id'] ?? null) ? $artifact['@id'] : ($iri ?: null),
                'title' => $form->getTitle(),
                'fieldCount' => $form->getFields()->count(),
                'artifact' => $artifact,
            ], $status);
        } catch (\JsonException) {
            return new JsonResponse(['message' => 'Invalid JSON body.'], 400);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['message' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->failure('export-form', $connectedApp, $e, 'Unable to export canonical form to CEDAR.');
        }
    }

    private function resolveType(string $type): CedarArtifactType
    {
        $resolved = CedarArtifactType::tryFrom(strtolower(trim($type)));
        if (null === $resolved) {
            throw new \InvalidArgumentException(\sprintf('Unknown CEDAR artifact type "%s". Expected one of: template, element, field, instance.', $type));
        }

        return $resolved;
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws \JsonException
     */
    private function readArtifactBody(Request $request): array
    {
        $artifact = $this->decodeBody($request);
        // Allow either the raw artifact or an { "artifact": {...} } envelope.
        if (isset($artifact['artifact']) && \is_array($artifact['artifact'])) {
            $artifact = $artifact['artifact'];
        }

        if ([] === $artifact) {
            throw new \InvalidArgumentException('A non-empty artifact body is required.');
        }

        return $artifact;
    }

    /**
     * @return array<int|string, mixed>
     *
     * @throws \JsonException
     */
    private function decodeBody(Request $request): array
    {
        $content = trim((string) $request->getContent());
        if ('' === $content) {
            return [];
        }

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int|string, mixed> $data
     * @param list<string>             $keys
     */
    private function extractString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            if (\is_scalar($value) && '' !== trim((string) $value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    // CEDAR templates can nest elements arbitrarily deep; bound the recursion so
    // a pathological (or cyclic) artifact can't blow the stack or the import.
    private const int MAX_FIELD_DEPTH = 12;

    /**
     * Extract the fields of a CEDAR template into {@see ExtractedTemplateField}
     * rows, preserving the declared display order (`_ui.order`) and recursing
     * depth-first into nested `Element` nodes so the crosswalk skeleton is
     * complete rather than top-level only. Best-effort and defensive: anything we
     * can't read is skipped rather than failing the import.
     *
     * @param array<int|string, mixed> $template
     *
     * @return list<ExtractedTemplateField>
     */
    private function extractCedarFields(array $template): array
    {
        $fields = [];
        $orderIndex = 0;
        $this->collectCedarFields($template, '/properties', 0, $fields, $orderIndex);

        return $fields;
    }

    /**
     * Walk one artifact node's `properties`, appending a field per leaf and
     * descending into element nodes. `$orderIndex` is threaded by reference so
     * order is a single flattened depth-first sequence across all nesting levels.
     *
     * @param array<int|string, mixed>     $node
     * @param list<ExtractedTemplateField> $fields
     */
    private function collectCedarFields(array $node, string $pathPrefix, int $depth, array &$fields, int &$orderIndex): void
    {
        $properties = $node['properties'] ?? null;
        if (!\is_array($properties) || $depth > self::MAX_FIELD_DEPTH) {
            return;
        }

        $order = (\is_array($node['_ui'] ?? null) && \is_array($node['_ui']['order'] ?? null))
            ? $node['_ui']['order']
            : array_keys($properties);

        $context = (\is_array($properties['@context'] ?? null)) ? $properties['@context'] : [];

        foreach ($order as $key) {
            if (!\is_string($key) || !\is_array($properties[$key] ?? null)) {
                continue;
            }

            $property = $properties[$key];
            // Multi-instance fields/elements wrap the artifact node under `items`.
            $artifactNode = (\is_array($property['items'] ?? null)) ? $property['items'] : $property;
            $path = $pathPrefix . '/' . $key;

            $propertyId = \is_string($context[$key] ?? null) ? $context[$key] : $this->extractString($artifactNode, ['@id']);

            $fields[] = (new ExtractedTemplateField())
                ->setLabel($this->cedarNodeLabel($artifactNode, $key))
                ->setJsonldId($this->extractString($artifactNode, ['@id']))
                ->setJsonldPath($path)
                ->setPropertyId($propertyId)
                ->setDatatype(\is_array($artifactNode['_ui'] ?? null) ? $this->extractString($artifactNode['_ui'], ['inputType']) : null)
                ->setRawNode($property)
                ->setOrderIndex($orderIndex++)
                ->setMapped(false)
            ;

            // Descend into nested elements (their own `properties` define children).
            if ($this->isCedarElement($artifactNode)) {
                $this->collectCedarFields($artifactNode, $path . '/properties', $depth + 1, $fields, $orderIndex);
            }
        }
    }

    /**
     * @param array<int|string, mixed> $node
     */
    private function cedarNodeLabel(array $node, string $fallback): string
    {
        return $this->extractString($node, ['schema:name', 'title'])
            ?? (\is_array($node['_ui'] ?? null) ? $this->extractString($node['_ui'], ['title']) : null)
            ?? $fallback;
    }

    /**
     * A CEDAR node is an element (a container of further fields/elements) when
     * its JSON-LD type node resolves to TemplateElement, regardless of how the
     * type is spelled (string or array of IRIs).
     *
     * @param array<int|string, mixed> $node
     */
    private function isCedarElement(array $node): bool
    {
        if (!\is_array($node['properties'] ?? null)) {
            return false;
        }

        $type = $node['@type'] ?? null;
        foreach (\is_array($type) ? $type : [$type] as $candidate) {
            if (\is_string($candidate) && str_contains($candidate, 'TemplateElement')) {
                return true;
            }
        }

        return false;
    }

    private function failure(string $operation, ConnectedApp $connectedApp, \Throwable $e, string $message): JsonResponse
    {
        $this->logger->error(\sprintf('CEDAR %s failed', $operation), [
            'connected_app_id' => (string) $connectedApp->getId(),
            'error' => $e->getMessage(),
            'exception' => $e,
        ]);

        return new JsonResponse([
            'message' => $message,
            'detail' => $e->getMessage(),
        ], 502);
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
}
