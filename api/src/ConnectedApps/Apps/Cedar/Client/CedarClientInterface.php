<?php

declare(strict_types=1);

namespace App\ConnectedApps\Apps\Cedar\Client;

use App\ConnectedApps\Apps\Cedar\Enum\CedarArtifactType;
use App\Entity\ConnectedApp;

/**
 * Thin, typed wrapper over the CEDAR resource-server REST API. The surface
 * mirrors the official `cedar-artifact-rest-mcp` tool set one-to-one (fetch /
 * create / update / delete for each artifact kind, plus `validate_artifact`),
 * so Metadatapp is fully compatible with CEDAR and anything built on its MCP.
 *
 * @see https://github.com/metadatacenter/cedar-artifact-rest-mcp
 */
interface CedarClientInterface
{
    /**
     * The resource-server base URL for this connection (configured external URL
     * or the public CEDAR default), with any trailing slash removed.
     */
    public function getBaseUrl(ConnectedApp $connectedApp): string;

    /**
     * Fetch the authenticated user list — used to validate a connection.
     *
     * @return array<string, mixed>
     */
    public function getUsers(ConnectedApp $connectedApp): array;

    /**
     * Fetch an artifact by its `@id` IRI (`GET /{segment}/{url-encoded IRI}`).
     *
     * @return array<string, mixed>
     */
    public function getArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, string $iri): array;

    /**
     * Create an artifact (`POST /{segment}`). The top-level `@id` is nulled so
     * the server mints the authoritative one, exactly like the CEDAR MCP.
     *
     * @param array<int|string, mixed> $artifact
     *
     * @return array<string, mixed>
     */
    public function createArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, array $artifact): array;

    /**
     * Update an existing artifact (`PUT /{segment}/{url-encoded IRI}`).
     *
     * @param array<int|string, mixed> $artifact
     *
     * @return array<string, mixed>
     */
    public function updateArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, string $iri, array $artifact): array;

    /**
     * Permanently delete an artifact (`DELETE /{segment}/{url-encoded IRI}`).
     */
    public function deleteArtifact(ConnectedApp $connectedApp, CedarArtifactType $type, string $iri): void;

    /**
     * Validate an artifact against the CEDAR meta-model via the authoritative
     * `POST /command/validate?resource_type=...`. The kind is auto-detected from
     * the artifact's JSON-LD type when not supplied. Returns the server's report
     * (`{validates, warnings, errors}`).
     *
     * @param array<int|string, mixed> $artifact
     *
     * @return array<string, mixed>
     */
    public function validateArtifact(ConnectedApp $connectedApp, array $artifact, ?CedarArtifactType $type = null): array;

    /**
     * Full-text search across CEDAR artifacts.
     * Hits `GET /search?q={query}&resource_types[]=template&page=1&page_size={limit}`.
     *
     * @return array{totalCount: int, resources: list<array<string, mixed>>}
     */
    public function searchArtifacts(ConnectedApp $connectedApp, string $query, int $limit = 20): array;
}
