<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Cedar\Client\Mock;

use App\ConnectedApps\Apps\Cedar\Client\CedarClientInterface;
use App\ConnectedApps\Apps\Cedar\Enum\CedarArtifactType;
use App\Entity\ConnectedApp;

/**
 * Deterministic, network-free CEDAR client used in dev/test. Honours the
 * Connected Apps contract (mocks must respect the API shape, be deterministic
 * and fast) so the resource-server REST surface can be exercised without a live
 * CEDAR account or API key.
 */
final class CedarClientMock implements CedarClientInterface
{
    private const string DEFAULT_BASE_URL = 'https://resource.metadatacenter.org';
    private const string REPO_BASE = 'https://repo.metadatacenter.org';

    public function getBaseUrl(ConnectedApp $connectedApp): string
    {
        $externalUrl = $connectedApp->getExternalUrl();
        if (null === $externalUrl || '' === trim($externalUrl)) {
            return self::DEFAULT_BASE_URL;
        }

        return rtrim(trim($externalUrl), '/');
    }

    public function getUsers(ConnectedApp $connectedApp): array
    {
        return [
            'users' => [
                ['@id' => self::REPO_BASE . '/users/mock-user', 'firstName' => 'Mock', 'lastName' => 'Curator'],
            ],
        ];
    }

    public function getArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, string $iri): array
    {
        return $this->stubArtifact($type, $iri);
    }

    public function createArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, array $artifact): array
    {
        $artifact['@id'] = \sprintf('%s/%s/%s', self::REPO_BASE, $type->pathSegment(), 'mock-' . substr(md5(serialize($artifact)), 0, 12));

        return $artifact;
    }

    public function updateArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, string $iri, array $artifact): array
    {
        $artifact['@id'] = $iri;

        return $artifact;
    }

    public function deleteArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, string $iri): void
    {
        // No-op: deletion always succeeds in the mock.
    }

    public function validateArtifact(ConnectedApp $connectedApp, array $artifact, ?CedarArtifactType $type = null): array
    {
        return [
            'validates' => true,
            'warnings' => [],
            'errors' => [],
        ];
    }

    public function searchArtifacts(ConnectedApp $connectedApp, string $query, int $limit = 20): array
    {
        return ['totalCount' => 0, 'resources' => []];
    }

    /**
     * @return array<string, mixed>
     */
    private function stubArtifact(CedarArtifactType $type, string $iri): array
    {
        return [
            '@id' => '' !== trim($iri) ? $iri : \sprintf('%s/%s/mock', self::REPO_BASE, $type->pathSegment()),
            '@type' => match ($type) {
                CedarArtifactType::Template => 'https://schema.metadatacenter.org/core/Template',
                CedarArtifactType::Element => 'https://schema.metadatacenter.org/core/TemplateElement',
                CedarArtifactType::Field => 'https://schema.metadatacenter.org/core/TemplateField',
                CedarArtifactType::Instance => self::REPO_BASE . '/templates/mock',
            },
            'schema:name' => 'Mock CEDAR ' . ucfirst($type->value),
            'schema:description' => 'Deterministic mock CEDAR ' . $type->value . ' artifact.',
            'pav:version' => '1.0.0',
            'bibo:status' => 'bibo:published',
        ];
    }
}
