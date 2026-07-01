<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\BooleanFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\AppCode;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Represents a Connected Application. (ex: LIMS, ELABOOK, AMS).
 */
#[ORM\Entity]
#[ApiResource(
    security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN') or is_granted('ROLE_SUPER_ADMIN')",
    normalizationContext: ['groups' => ['connected_app.read', 'enum:read']],
    denormalizationContext: ['groups' => ['connected_app.write']],
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Patch(),
        new Delete(),
        new Post(
            uriTemplate: '/connected_apps/{id}/sync',
            status: 202,
            processor: \App\ConnectedApps\State\Processor\ConnectedAppFullSyncProcessor::class,
            deserialize: false,
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/push',
            status: 202,
            processor: \App\ConnectedApps\State\Processor\ConnectedAppFullPushProcessor::class,
            deserialize: false,
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Queue a full outbound synchronization to the external app',
                ],
            ],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/elabftw/test-connection',
            name: 'api_elabftw_test_connection',
            controller: \App\Controller\Api\ElabftwProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured eLabFTW URL and API token',
                    'responses' => [
                        '200' => [
                            'description' => 'Validation response from eLabFTW',
                        ],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/fair3r/test-connection',
            name: 'api_fair3r_test_connection',
            controller: \App\Controller\Api\Fair3rProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured Fair3R connection',
                    'responses' => [
                        '200' => [
                            'description' => 'Validation response from Fair3R',
                        ],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/osf/test-connection',
            name: 'api_osf_test_connection',
            controller: \App\Controller\Api\OsfProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured OSF URL and API token',
                    'responses' => [
                        '200' => [
                            'description' => 'Validation response from OSF',
                        ],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/protocols-io/test-connection',
            name: 'api_protocols_io_test_connection',
            controller: \App\Controller\Api\ProtocolsIoProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured protocols.io API token',
                    'responses' => [
                        '200' => ['description' => 'Validation response from protocols.io'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/preclinicaltrials/test-connection',
            name: 'api_preclinicaltrials_test_connection',
            controller: \App\Controller\Api\PreclinicalTrialsProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured PreclinicalTrials.eu API endpoint',
                    'responses' => [
                        '200' => ['description' => 'Validation response from PreclinicalTrials.eu'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/preclinicaltrials/protocols',
            name: 'api_preclinicaltrials_list_protocols',
            controller: \App\Controller\Api\PreclinicalTrialsProxyController::class . '::listProtocols',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'List published PreclinicalTrials.eu protocols available for selective import',
                    'responses' => [
                        '200' => ['description' => '{ "protocols": [...] }'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/preclinicaltrials/import-protocol',
            name: 'api_preclinicaltrials_import_protocol',
            controller: \App\Controller\Api\PreclinicalTrialsProxyController::class . '::importProtocol',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Import or link one selected PreclinicalTrials.eu protocol',
                    'responses' => [
                        '200' => ['description' => '{ "ok": true, "target": {...} }'],
                        '400' => ['description' => 'Bad request'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/cedar/test-connection',
            name: 'api_cedar_test_connection',
            controller: \App\Controller\Api\CedarProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured CEDAR API key',
                    'responses' => [
                        '200' => ['description' => 'Validation response from CEDAR'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/cedar/artifacts/{type}',
            name: 'api_cedar_get_artifact',
            requirements: ['type' => 'template|element|field|instance'],
            controller: \App\Controller\Api\CedarProxyController::class . '::getArtifact',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Fetch a CEDAR artifact (template|element|field|instance) by its @id IRI',
                    'parameters' => [
                        ['name' => 'type', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['template', 'element', 'field', 'instance']]],
                        ['name' => 'iri', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => '{ "artifact": {...} }'],
                        '400' => ['description' => 'Bad request'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/cedar/artifacts/{type}',
            name: 'api_cedar_create_artifact',
            requirements: ['type' => 'template|element|field|instance'],
            controller: \App\Controller\Api\CedarProxyController::class . '::createArtifact',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Create a CEDAR artifact (the server mints its @id)',
                    'parameters' => [
                        ['name' => 'type', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['template', 'element', 'field', 'instance']]],
                    ],
                    'responses' => [
                        '201' => ['description' => '{ "artifact": {...} } — the created artifact with its assigned @id'],
                        '400' => ['description' => 'Bad request'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/cedar/artifacts/{type}/update',
            name: 'api_cedar_update_artifact',
            requirements: ['type' => 'template|element|field|instance'],
            controller: \App\Controller\Api\CedarProxyController::class . '::updateArtifact',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Update an existing CEDAR artifact by its @id IRI',
                    'parameters' => [
                        ['name' => 'type', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['template', 'element', 'field', 'instance']]],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['iri', 'artifact'], 'properties' => ['iri' => ['type' => 'string'], 'artifact' => ['type' => 'object']]]]],
                    ],
                    'responses' => [
                        '200' => ['description' => '{ "artifact": {...} }'],
                        '400' => ['description' => 'Bad request'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/cedar/artifacts/{type}/delete',
            name: 'api_cedar_delete_artifact',
            requirements: ['type' => 'template|element|field|instance'],
            controller: \App\Controller\Api\CedarProxyController::class . '::deleteArtifact',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Permanently delete a CEDAR artifact by its @id IRI',
                    'parameters' => [
                        ['name' => 'type', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string', 'enum' => ['template', 'element', 'field', 'instance']]],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['iri'], 'properties' => ['iri' => ['type' => 'string']]]]],
                    ],
                    'responses' => [
                        '200' => ['description' => '{ "ok": true, "deleted": "<iri>" }'],
                        '400' => ['description' => 'Bad request'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/cedar/validate',
            name: 'api_cedar_validate_artifact',
            controller: \App\Controller\Api\CedarProxyController::class . '::validateArtifact',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate a CEDAR artifact against the meta-model (kind auto-detected from @type)',
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['artifact'], 'properties' => ['artifact' => ['type' => 'object'], 'type' => ['type' => 'string', 'enum' => ['template', 'element', 'field', 'instance']]]]]],
                    ],
                    'responses' => [
                        '200' => ['description' => '{ "validates": bool, "warnings": [...], "errors": [...] }'],
                        '400' => ['description' => 'Bad request'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/cedar/import-template',
            name: 'api_cedar_import_template',
            controller: \App\Controller\Api\CedarProxyController::class . '::importTemplate',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Import a CEDAR template by @id IRI into Metadatapp as an external template',
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['iri'], 'properties' => ['iri' => ['type' => 'string']]]]],
                    ],
                    'responses' => [
                        '201' => ['description' => '{ "ok": true, "id": "...", "title": "...", "externalUrl": "..." }'],
                        '400' => ['description' => 'Bad request'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/cedar/export-form',
            name: 'api_cedar_export_form',
            controller: \App\Controller\Api\CedarProxyController::class . '::exportForm',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Write a Metadatapp canonical form back to CEDAR as a template',
                    'requestBody' => [
                        'required' => true,
                        'content' => ['application/json' => ['schema' => ['type' => 'object', 'required' => ['canonicalFormId'], 'properties' => ['canonicalFormId' => ['type' => 'string', 'format' => 'uuid'], 'iri' => ['type' => 'string', 'description' => 'When set, update this CEDAR template instead of creating a new one']]]]],
                    ],
                    'responses' => [
                        '201' => ['description' => '{ "ok": true, "id": "<@id>", "title": "...", "fieldCount": int, "artifact": {...} }'],
                        '200' => ['description' => 'Updated existing template (when "iri" supplied)'],
                        '400' => ['description' => 'Bad request'],
                        '404' => ['description' => 'Canonical form not found'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/bioportal/test-connection',
            name: 'api_bioportal_test_connection',
            controller: \App\Controller\Api\BioPortalProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured BioPortal API key',
                    'responses' => [
                        '200' => ['description' => 'Validation response from BioPortal'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/nih-cde/test-connection',
            name: 'api_nih_cde_test_connection',
            controller: \App\Controller\Api\NihCdeProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured NIH CDE API endpoint',
                    'responses' => [
                        '200' => ['description' => 'Validation response from NIH CDE'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/nih-cde/search',
            name: 'api_nih_cde_search',
            controller: \App\Controller\Api\NihCdeProxyController::class . '::search',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Search NIH Common Data Elements',
                    'parameters' => [
                        ['name' => 'searchTerm', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'resultPerPage', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 20]],
                        ['name' => 'skip', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 0]],
                    ],
                    'responses' => [
                        '200' => ['description' => '{ "totalItems": int, "elements": [...] }'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/nih-cde/elements/{tinyId}',
            name: 'api_nih_cde_get_element',
            controller: \App\Controller\Api\NihCdeProxyController::class . '::getElement',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Get a single NIH CDE by its tinyId',
                    'responses' => [
                        '200' => ['description' => 'Full CDE payload from NIH CDE repository'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/nih-cde/import',
            name: 'api_nih_cde_import',
            controller: \App\Controller\Api\NihCdeProxyController::class . '::importElement',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Import a NIH CDE by tinyId into the local database',
                    'responses' => [
                        '201' => ['description' => '{ "ok": true, "tinyId": "...", "name": "...", "id": "..." }'],
                        '400' => ['description' => 'Bad request'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/jax-phenome/test-connection',
            name: 'api_jax_phenome_test_connection',
            controller: \App\Controller\Api\JaxPhenomeProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured JAX Phenome (MPD) API endpoint',
                    'responses' => [
                        '200' => ['description' => '{ "ok": true, "externalUrl": "...", "strainsCount": int }'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: true,
            security: "is_granted('ROLE_USER')",
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/jax-phenome/measures',
            name: 'api_jax_phenome_search_measures',
            controller: \App\Controller\Api\JaxPhenomeProxyController::class . '::searchMeasures',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Search JAX Phenome (MPD) phenotype measures',
                    'parameters' => [
                        ['name' => 'searchTerm', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 20]],
                        ['name' => 'offset', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 0]],
                    ],
                    'responses' => [
                        '200' => ['description' => '{ "totalItems": int, "measures": [...] }'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: true,
            security: "is_granted('ROLE_USER')",
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/jax-phenome/measures/{measureId}',
            name: 'api_jax_phenome_get_measure',
            requirements: ['measureId' => '\d+'],
            controller: \App\Controller\Api\JaxPhenomeProxyController::class . '::getMeasure',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Get a single JAX Phenome (MPD) measure by its measureId',
                    'responses' => [
                        '200' => ['description' => 'Full measure payload from the MPD repository'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: true,
            security: "is_granted('ROLE_USER')",
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/jax-phenome/strains',
            name: 'api_jax_phenome_list_strains',
            controller: \App\Controller\Api\JaxPhenomeProxyController::class . '::listStrains',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'List JAX Phenome (MPD) strains',
                    'parameters' => [
                        ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 20]],
                        ['name' => 'offset', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 0]],
                    ],
                    'responses' => [
                        '200' => ['description' => '{ "totalItems": int, "strains": [...] }'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: true,
            security: "is_granted('ROLE_USER')",
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/impc/test-connection',
            name: 'api_impc_test_connection',
            controller: \App\Controller\Api\ImpcProxyController::class . '::testConnection',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured IMPReSS (IMPC) API endpoint',
                    'responses' => [
                        '200' => ['description' => '{ "ok": true, "externalUrl": "...", "pipelinesCount": int }'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: true,
            security: "is_granted('ROLE_USER')",
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/impc/procedures',
            name: 'api_impc_search_procedures',
            controller: \App\Controller\Api\ImpcProxyController::class . '::searchProcedures',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Search IMPReSS standardized procedures for behavioral studies',
                    'parameters' => [
                        ['name' => 'searchTerm', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string']],
                        ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 20]],
                        ['name' => 'offset', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 0]],
                    ],
                    'responses' => [
                        '200' => ['description' => '{ "totalItems": int, "procedures": [...] }'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: true,
            security: "is_granted('ROLE_USER')",
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/impc/procedures/{procedureId}',
            name: 'api_impc_get_procedure',
            requirements: ['procedureId' => '[A-Za-z0-9_\-]+'],
            controller: \App\Controller\Api\ImpcProxyController::class . '::getProcedure',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Get a single IMPReSS procedure (with its standardized parameters) by its upstream numeric id',
                    'responses' => [
                        '200' => ['description' => 'Full procedure payload from the IMPReSS catalogue'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: true,
            security: "is_granted('ROLE_USER')",
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/impc/pipelines',
            name: 'api_impc_list_pipelines',
            controller: \App\Controller\Api\ImpcProxyController::class . '::listPipelines',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'List IMPReSS phenotyping pipelines',
                    'parameters' => [
                        ['name' => 'limit', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 20]],
                        ['name' => 'offset', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 0]],
                    ],
                    'responses' => [
                        '200' => ['description' => '{ "totalItems": int, "pipelines": [...] }'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: true,
            security: "is_granted('ROLE_USER')",
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/fair3r/datasets',
            name: 'api_fair3r_list_datasets',
            controller: \App\Controller\Api\Fair3rProxyController::class . '::listDatasets',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'List dataset names from the connected Fair3R / CKAN instance',
                    'responses' => [
                        '200' => ['description' => '{ "datasets": ["slug1", …] }'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/fair3r/datasets/{datasetName}',
            name: 'api_fair3r_show_dataset',
            controller: \App\Controller\Api\Fair3rProxyController::class . '::showDataset',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Read a FAIR3R / CKAN dataset and extract Metadatapp FDF metadata when present',
                    'responses' => [
                        '200' => ['description' => '{ "dataset": {...}, "fdf": {...}|null }'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/fair3r/push-investigation',
            name: 'api_fair3r_push_investigation',
            controller: \App\Controller\Api\Fair3rProxyController::class . '::pushInvestigation',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Push an investigation (Project) to Fair3R as a CKAN package',
                    'responses' => [
                        '200' => ['description' => '{ "ok": true, "ckanName": "…", "packageUrl": "…" }'],
                        '400' => ['description' => 'Bad request'],
                        '502' => ['description' => 'Proxy error'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/dvc/test-api-key',
            name: 'api_tecniplast_dvc_test_api_key',
            controller: \App\Controller\Api\TecniplastProxyController::class . '::testApiKey',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Validate the configured Tecniplast API key',
                    'responses' => [
                        '200' => [
                            'description' => 'Validation response from Tecniplast',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/TestApiKeyResponse'],
                                ],
                            ],
                        ],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/dvc/metrics',
            name: 'api_tecniplast_dvc_metrics',
            controller: \App\Controller\Api\TecniplastProxyController::class . '::getMetrics',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Fetch available Tecniplast metrics',
                    'responses' => [
                        '200' => [
                            'description' => 'Available metrics',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/MetricsResponse'],
                                ],
                            ],
                        ],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/dvc/cages/search',
            name: 'api_tecniplast_dvc_search_cages',
            controller: \App\Controller\Api\TecniplastProxyController::class . '::searchCages',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Search cages available in Tecniplast DVC',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/GlobSearchRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Matching cages and aggregate availability window',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/CageSearchResponse'],
                                ],
                            ],
                        ],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/dvc/animals/search',
            name: 'api_tecniplast_dvc_search_animals',
            controller: \App\Controller\Api\TecniplastProxyController::class . '::searchAnimals',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Search animals available in Tecniplast DVC',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/GlobSearchRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Matching animals',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/GlobResults'],
                                ],
                            ],
                        ],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/dvc/submit',
            name: 'api_tecniplast_dvc_submit',
            controller: \App\Controller\Api\TecniplastProxyController::class . '::submitTask',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Submit a Tecniplast extraction task',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/TaskSubmission'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Accepted extraction task',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/TaskSubmissionResponse'],
                                ],
                            ],
                        ],
                        '400' => ['description' => 'Proxy error response'],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/dvc/{taskId}/state',
            name: 'api_tecniplast_dvc_task_state',
            controller: \App\Controller\Api\TecniplastProxyController::class . '::getTaskState',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Fetch the current state of a Tecniplast extraction task',
                    'parameters' => [
                        [
                            'name' => 'taskId',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Current task state',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/TaskStateResponse'],
                                ],
                            ],
                        ],
                        '502' => ['description' => 'Proxy error response'],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Get(
            uriTemplate: '/connected_apps/{id}/dvc/{taskId}/download',
            name: 'api_tecniplast_dvc_download',
            controller: \App\Controller\Api\TecniplastProxyController::class . '::downloadTaskResult',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Download a completed Tecniplast extraction archive',
                    'parameters' => [
                        [
                            'name' => 'taskId',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'ZIP archive containing CSV exports',
                            'content' => [
                                'application/zip' => [
                                    'schema' => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            read: false,
            formats: ['json' => ['application/json']],
        ),
        new Post(
            uriTemplate: '/connected_apps/{id}/dvc/{taskId}/import',
            name: 'api_tecniplast_dvc_import',
            controller: \App\Controller\Api\TecniplastProxyController::class . '::importTaskResult',
            extraProperties: [
                'openapi_context' => [
                    'summary' => 'Import a completed Tecniplast extraction archive into experiment metadata',
                    'parameters' => [
                        [
                            'name' => 'taskId',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer'],
                        ],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['experimentIri'],
                                    'properties' => [
                                        'experimentIri' => [
                                            'type' => 'string',
                                            'example' => '/experiments/550e8400-e29b-41d4-a716-446655440000',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Import summary',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'taskId' => ['type' => 'integer'],
                                            'experimentIri' => ['type' => 'string'],
                                            'processedRows' => ['type' => 'integer'],
                                            'importedRows' => ['type' => 'integer'],
                                            'skippedRows' => ['type' => 'integer'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '400' => ['description' => 'Invalid request body'],
                        '403' => ['description' => 'Account mismatch'],
                        '404' => ['description' => 'Experiment not found'],
                        '502' => ['description' => 'Tecniplast download error'],
                    ],
                ],
            ],
            read: false,
            deserialize: false,
            formats: ['json' => ['application/json']],
        ),
    ]
)]
#[ApiFilter(BooleanFilter::class, properties: ['isActive'])]
class ConnectedApp implements AccountAwareInterface, UserAwareInterface
{
    private const array TOKEN_PARAMETER_KEYS = ['token', 'apiKey', 'accessToken'];

    #[ApiProperty(identifier: true, types: ['https://schema.org/identifier'])]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Id]
    #[Groups(['connected_app.read'])]
    private ?Uuid $id = null;

    #[ORM\Column(name: 'code', type: 'string', enumType: AppCode::class)]
    #[Assert\NotNull]
    #[Groups(['connected_app.read', 'connected_app.write', 'enum:read', 'nested.read'])]
    private AppCode $code;

    #[ApiProperty(types: 'https://schema.org/name')]
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['connected_app.read', 'connected_app.write'])]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['connected_app.read', 'connected_app.write'])]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'connectedApps')]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false, writable: false)]
    private User $user;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['connected_app.read', 'connected_app.write'])]
    #[SerializedName('active')]
    private bool $isActive = false;

    #[ORM\Column(type: 'text', nullable: true)]
    #[ApiProperty(readable: false)]
    #[Groups(['connected_app.write'])]
    private ?string $token = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[ApiProperty(readable: false)]
    #[Groups(['connected_app.write'])]
    private ?array $authenticationParameters = null;

    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    #[Groups(['connected_app.read', 'connected_app.write'])]
    private ?string $externalUrl = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['connected_app.read'])]
    private ?\DateTimeInterface $lastSyncAt = null;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'connectedApps')]
    #[ORM\JoinColumn(nullable: false)]
    #[ApiProperty(readable: false, writable: false)]
    private Account $account;

    public function __construct()
    {
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function setId(?Uuid $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getToken(): ?string
    {
        $tokenValue = $this->stringifyAuthenticationParameterValue($this->token);
        if (null !== $tokenValue) {
            return $tokenValue;
        }

        foreach (self::TOKEN_PARAMETER_KEYS as $parameterKey) {
            $parameterValue = $this->getAuthenticationParameterValue($parameterKey);
            if (null !== $parameterValue) {
                return $parameterValue;
            }
        }

        return null;
    }

    public function setToken(?string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getAuthenticationParameters(): ?array
    {
        return $this->authenticationParameters;
    }

    public function setAuthenticationParameters(?array $authenticationParameters): self
    {
        if (null === $authenticationParameters) {
            $this->authenticationParameters = null;

            return $this;
        }

        $normalizedParameters = [];
        foreach ($authenticationParameters as $key => $value) {
            if (!\is_string($key)) {
                $normalizedParameters[$key] = $value;

                continue;
            }

            $stringValue = $this->stringifyAuthenticationParameterValue($value);
            if (null !== $stringValue) {
                $normalizedParameters[$key] = $stringValue;
            }
        }

        $this->authenticationParameters = null === $this->authenticationParameters
            ? $normalizedParameters
            : array_replace($this->authenticationParameters, $normalizedParameters);

        return $this;
    }

    #[Groups(['connected_app.read'])]
    #[SerializedName('logoUrl')]
    public function getLogoUrl(): ?string
    {
        return match ($this->code) {
            AppCode::Elabftw => '/images/apps/elabftw.svg',
            AppCode::Osf => '/images/apps/osf.svg',
            AppCode::SoftMouse => '/images/apps/softmouse.svg',
            AppCode::Tecniplast => '/images/apps/tecniplast.svg',
            AppCode::Fair3r => '/images/apps/fair3r.svg',
            AppCode::PreclinicalTrials => '/images/apps/preclinicaltrials.svg',
            AppCode::ProtocolIo => '/images/apps/protocols_io.svg',
            AppCode::Cedar => '/images/apps/cedar.svg',
            AppCode::BioPortal => '/images/apps/bioportal.svg',
            AppCode::NihCde => null,
            AppCode::JaxPhenome => null,
            AppCode::Impc => null,
            AppCode::SensorAgent => null,
            AppCode::Mnms => null,
            AppCode::GuidelinesHub => null,
        };
    }

    #[Groups(['connected_app.read'])]
    #[SerializedName('tokenHint')]
    public function getTokenHint(): ?string
    {
        return self::maskSecret($this->getToken());
    }

    #[Groups(['connected_app.read'])]
    #[SerializedName('authenticationParameterHints')]
    public function getAuthenticationParameterHints(): ?array
    {
        if (null === $this->authenticationParameters) {
            return null;
        }

        $hints = [];
        foreach ($this->authenticationParameters as $key => $value) {
            if (!\is_string($key)) {
                continue;
            }

            $hint = self::maskAuthenticationParameter($key, $this->stringifyAuthenticationParameterValue($value));
            if (null !== $hint) {
                $hints[$key] = $hint;
            }
        }

        return [] === $hints ? null : $hints;
    }

    public function getCode(): AppCode
    {
        return $this->code;
    }

    public function setCode(AppCode $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $active): self
    {
        $this->isActive = $active;

        return $this;
    }

    /**
     * Backward compatibility alias for setActive().
     *
     * This method is kept for backward compatibility with existing code that may
     * use setIsActive(). New code should use setActive() instead.
     *
     * @deprecated Use setActive() instead. This method will be removed in a future version.
     */
    public function setIsActive(bool $active): self
    {
        return $this->setActive($active);
    }

    public function getLastSyncAt(): ?\DateTimeInterface
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeInterface $lastSyncAt): self
    {
        $this->lastSyncAt = $lastSyncAt;

        return $this;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): self
    {
        $this->account = $account;

        return $this;
    }

    #[Assert\Callback]
    public function validateAuthenticationParameters(ExecutionContextInterface $context): void
    {
        if (null === $this->authenticationParameters) {
            return;
        }

        if (!isset($this->code)) {
            return;
        }

        foreach ($this->authenticationParameters as $key => $value) {
            if (!\is_string($key) || '' === trim($key)) {
                $context->buildViolation('Authentication parameter names must be non-empty strings.')
                    ->atPath('authenticationParameters')
                    ->addViolation()
                ;

                return;
            }

            if (null !== $value && !\is_scalar($value)) {
                $context->buildViolation('Authentication parameter values must be scalar or null.')
                    ->atPath(\sprintf('authenticationParameters[%s]', $key))
                    ->addViolation()
                ;

                return;
            }
        }

        match ($this->code) {
            AppCode::SoftMouse => $this->validateRequiredAuthenticationParameters($context, ['username', 'password']),
            AppCode::Elabftw,
            AppCode::Osf,
            AppCode::Fair3r,
            AppCode::ProtocolIo,
            AppCode::Tecniplast,
            AppCode::Cedar,
            AppCode::BioPortal => $this->validateAnyAuthenticationParameter($context, ['apiKey', 'token', 'accessToken']),
            AppCode::PreclinicalTrials => null,
            AppCode::NihCde => null,
            AppCode::JaxPhenome => null,
            AppCode::Impc => null,
            AppCode::SensorAgent => null,
            AppCode::Mnms => null,
            AppCode::GuidelinesHub => null,
        };
    }

    private function validateRequiredAuthenticationParameters(ExecutionContextInterface $context, array $requiredKeys): void
    {
        $missingKeys = array_filter($requiredKeys, fn (string $key): bool => null === $this->getAuthenticationParameterValue($key));
        if ([] === $missingKeys) {
            return;
        }

        $context->buildViolation(\sprintf('Missing required authentication parameter(s): %s.', implode(', ', $missingKeys)))
            ->atPath('authenticationParameters')
            ->addViolation()
        ;
    }

    private function validateAnyAuthenticationParameter(ExecutionContextInterface $context, array $supportedKeys): void
    {
        foreach ($supportedKeys as $key) {
            if (null !== $this->getAuthenticationParameterValue($key)) {
                return;
            }
        }

        $context->buildViolation(\sprintf('Provide at least one authentication parameter for %s: %s.', $this->code->value, implode(', ', $supportedKeys)))
            ->atPath('authenticationParameters')
            ->addViolation()
        ;
    }

    private function getAuthenticationParameterValue(string $key): ?string
    {
        if (null === $this->authenticationParameters || !\array_key_exists($key, $this->authenticationParameters)) {
            return null;
        }

        return $this->stringifyAuthenticationParameterValue($this->authenticationParameters[$key]);
    }

    private function stringifyAuthenticationParameterValue(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_scalar($value)) {
            $stringValue = trim((string) $value);

            return '' === $stringValue ? null : $stringValue;
        }

        return null;
    }

    private static function maskSecret(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $length = \strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', 6) . substr($value, -3);
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): self
    {
        $this->externalUrl = $externalUrl;

        return $this;
    }

    private static function maskAuthenticationParameter(string $key, ?string $value): ?string
    {
        if (\in_array(strtolower($key), ['password'], true)) {
            return null === $value || '' === $value ? null : '******';
        }

        return self::maskSecret($value);
    }
}
